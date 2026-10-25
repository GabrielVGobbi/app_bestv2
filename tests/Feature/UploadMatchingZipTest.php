<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;
use ZipArchive;

class UploadMatchingZipTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Client $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $this->testEnvironment->path.'/z', 'throw' => true, 'visibility' => 'private']]);
        Storage::forgetDisk('local');
        Bus::fake([GenerateZipJob::class]);
        $this->actor = User::factory()->create(['must_change_password' => false]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'view-clients'], ['name' => 'View Clients', 'group' => 'clients']);
        $this->actor->permissions()->syncWithoutDetaching([$permission->id]);
        $this->parent = Client::factory()->create();
        $this->actingAs($this->actor, 'web');
    }

    public function test_all_matching_materializes_more_than_one_page_and_real_archive_contains_only_the_snapshot(): void
    {
        $expected = [];
        $ids = [];
        for ($index = 1; $index <= 63; $index++) {
            $upload = $this->upload('match-'.$index, ['name' => 'needle '.$index]);
            $expected[$upload->original_name] = Storage::disk('local')->get($upload->path);
            $ids[] = $upload->id;
        }
        $this->upload('other-collection', ['name' => 'needle', 'collection' => 'receipts']);
        $this->upload('other-tag', ['name' => 'needle', 'tag' => 'other']);
        $this->upload('other-parent', ['name' => 'needle', 'parentable_id' => Client::factory()->create()->id]);
        $this->upload('not-matching');
        $this->upload('deleted', ['name' => 'needle'])->delete();

        $this->postJson('/api/v1/uploads/zip-requests', ['all_matching' => $this->filters(), 'label' => 'UI002 documents'])
            ->assertAccepted()->assertJsonPath('data.file_count', 63);
        $zip = ZipRequest::query()->sole();
        $this->assertEqualsCanonicalizing($ids, $zip->file_ids);
        $this->assertSame(63, count(array_unique($zip->file_ids)));
        Bus::assertDispatchedTimes(GenerateZipJob::class, 1);
        $this->upload('created-after-request', ['name' => 'needle']);
        $reported = [];
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $exception) use (&$reported): void {
            $reported[] = $exception::class.': '.$exception->getMessage();
        });
        (new GenerateZipJob($zip))->handle();
        $zip->refresh();
        $this->assertSame('ready', $zip->status, json_encode($reported, JSON_THROW_ON_ERROR));
        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('local')->path($zip->zip_path)));
        try {
            $this->assertSame(63, $archive->numFiles);
            $actual = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $actual[$archive->getNameIndex($index)] = $archive->getFromIndex($index);
            }
            ksort($expected);
            ksort($actual);
            $this->assertSame($expected, $actual);
        } finally {
            $archive->close();
        }
        $this->getJson('/api/v1/uploads/zip-requests/'.$zip->uuid)->assertOk()->assertJsonPath('data.status', 'ready');
        $this->get('/api/v1/uploads/zip-requests/'.$zip->uuid.'/download')->assertOk()->assertHeader('content-type', 'application/zip');
    }

    public function test_two_hundred_matches_are_accepted_and_two_hundred_one_are_rejected_without_truncation(): void
    {
        for ($index = 1; $index <= 200; $index++) {
            $this->upload('bounded-'.$index, ['name' => 'needle']);
        }
        $this->postJson('/api/v1/uploads/zip-requests', ['all_matching' => $this->filters()])->assertAccepted()->assertJsonPath('data.file_count', 200);
        $this->assertSame(200, count(ZipRequest::query()->sole()->file_ids));
        $this->upload('overflow', ['name' => 'needle']);
        $this->postJson('/api/v1/uploads/zip-requests', ['all_matching' => $this->filters()])->assertUnprocessable();
        $this->assertSame(1, ZipRequest::query()->count());
        Bus::assertDispatchedTimes(GenerateZipJob::class, 1);
    }

    #[DataProvider('invalidSelections')]
    public function test_selection_modes_and_filter_shape_are_validated(string $kind): void
    {
        $upload = $this->upload('validation', ['name' => 'needle']);
        $payload = match ($kind) {
            'both' => ['ids' => [$upload->id], 'all_matching' => $this->filters()],
            'both-empty' => ['ids' => [$upload->id], 'all_matching' => []],
            'none' => [],
            'alias' => ['all_matching' => [...$this->filters(), 'parentable_type' => Client::class]],
            'parent' => ['all_matching' => [...$this->filters(), 'parentable_id' => 0]],
            'unknown' => ['all_matching' => [...$this->filters(), 'unbounded' => true]],
            'search' => ['all_matching' => [...$this->filters(), 'search' => ['bad']]],
            'long-search' => ['all_matching' => [...$this->filters(), 'search' => str_repeat('x', 256)]],
            'ids-limit' => ['ids' => array_fill(0, 201, $upload->id)],
            default => throw new \InvalidArgumentException('Unknown ZIP selection fixture case: '.$kind),
        };
        $this->postJson('/api/v1/uploads/zip-requests', $payload)->assertUnprocessable();
        $this->assertSame(0, ZipRequest::query()->count());
        Bus::assertNotDispatched(GenerateZipJob::class);
    }

    /** @return array<string, array{string}> */
    public static function invalidSelections(): array
    {
        return array_combine(['both', 'both-empty', 'none', 'alias', 'parent', 'unknown', 'search', 'long-search', 'ids-limit'], array_map(fn (string $kind): array => [$kind], ['both', 'both-empty', 'none', 'alias', 'parent', 'unknown', 'search', 'long-search', 'ids-limit']));
    }

    public function test_matching_search_is_literal_and_existing_explicit_ids_flow_remains_authorized(): void
    {
        $target = $this->upload('literal', ['name' => '100% a_b']);
        $this->upload('decoy', ['name' => '1000 axb']);
        $this->postJson('/api/v1/uploads/zip-requests', ['all_matching' => [...$this->filters(), 'search' => '100% a_b']])->assertAccepted()->assertJsonPath('data.file_count', 1);
        $this->assertSame([$target->id], ZipRequest::query()->sole()->file_ids);
        $this->postJson('/api/v1/uploads/zip-requests', ['ids' => [$target->id]])->assertAccepted()->assertJsonPath('data.file_count', 1);
        $this->assertSame(2, ZipRequest::query()->count());
    }

    #[DataProvider('zeroFilters')]
    public function test_literal_zero_filter_is_not_discarded(string $field): void
    {
        $target = $this->upload('zero', $field === 'search' ? ['name' => '0'] : [$field => '0']);
        $this->upload('other', ['name' => 'other', 'tag' => 'other', 'collection' => 'other']);
        $filters = ['parentable_type' => 'client', 'parentable_id' => $this->parent->id, $field => '0'];
        $this->postJson('/api/v1/uploads/zip-requests', ['all_matching' => $filters])->assertAccepted()->assertJsonPath('data.file_count', 1);
        $this->assertSame([$target->id], ZipRequest::query()->sole()->file_ids);
    }

    /** @return array<string, array{string}> */
    public static function zeroFilters(): array
    {
        return ['search' => ['search'], 'tag' => ['tag'], 'collection' => ['collection']];
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return ['parentable_type' => 'client', 'parentable_id' => $this->parent->id, 'collection' => 'documents', 'tag' => 'evidence', 'search' => 'needle'];
    }

    /** @param array<string, mixed> $attributes */
    private function upload(string $name, array $attributes = []): Uploaded
    {
        $bytes = 'UI002 private bytes '.$name;
        $path = 'ui002/'.$name.'.txt';
        $this->assertTrue(Storage::disk('local')->put($path, $bytes));

        return Uploaded::query()->create([
            'user_id' => $this->actor->id, 'uploader_id' => $this->actor->id,
            'parentable_type' => Client::class, 'parentable_id' => $this->parent->id,
            'name' => 'Manual', 'file_name' => $name.'.txt', 'original_name' => $name.'.txt',
            'mime_type' => 'text/plain', 'path' => $path, 'extension' => 'txt', 'disk' => 'local',
            'size' => strlen($bytes), 'collection' => 'documents', 'tag' => 'evidence', ...$attributes,
        ])->refresh();
    }
}
