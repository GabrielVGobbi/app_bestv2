<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UploadDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    #[DataProvider('identityPairs')]
    public function test_deduplication_uses_parent_collection_tag_and_hash_with_explicit_nulls(
        ?string $firstTag,
        ?string $firstCollection,
        ?string $nextTag,
        ?string $nextCollection,
        bool $same,
    ): void {
        $parent = Client::factory()->create();
        $service = app(UploadService::class);
        $first = $service->upload($parent, $this->file(), name: 'Original name', tag: $firstTag, collection: $firstCollection);
        $before = $first->fresh()->getAttributes();
        $next = $service->upload($parent, $this->file(), name: 'Second request name', tag: $nextTag, collection: $nextCollection, isCover: true);

        if ($same) {
            $this->assertSame($first->id, $next->id);
            $this->assertSame($before, $first->fresh()->getAttributes(), 'Deduplication must not replay metadata or cover changes.');
        } else {
            $this->assertNotSame($first->id, $next->id);
            $this->assertSame($nextTag, $next->tag);
            $this->assertSame($nextCollection, $next->collection);
        }

        $this->assertSame($same ? 1 : 2, Uploaded::query()->count());
        $this->assertCount($same ? 1 : 2, Storage::disk('local')->allFiles('uploads'));
        $this->assertSame('UPL001 identical fixture bytes', Storage::disk('local')->get($next->path));
    }

    /** @return array<string, array{?string, ?string, ?string, ?string, bool}> */
    public static function identityPairs(): array
    {
        return [
            'same null identity' => [null, null, null, null, true],
            'same explicit identity' => ['receipt', 'documents', 'receipt', 'documents', true],
            'same empty identity' => ['', '', '', '', true],
            'null tag does not capture tagged row' => ['receipt', 'documents', null, 'documents', false],
            'tagged does not capture null tag' => [null, 'documents', 'receipt', 'documents', false],
            'different tags' => ['receipt', 'documents', 'invoice', 'documents', false],
            'different collections' => ['receipt', 'documents', 'receipt', 'receipts', false],
            'null collection does not capture named collection' => ['receipt', 'documents', 'receipt', null, false],
            'named collection does not capture null collection' => ['receipt', null, 'receipt', 'documents', false],
            'null tag is distinct from empty tag' => ['', null, null, null, false],
            'empty tag is distinct from null tag' => [null, null, '', null, false],
            'empty collection is distinct from null collection' => [null, null, null, '', false],
        ];
    }

    public function test_different_parent_ids_types_and_content_do_not_share_an_upload(): void
    {
        $service = app(UploadService::class);
        $firstParent = Client::factory()->create();
        $secondParent = Client::factory()->create();
        $otherType = User::query()->findOrFail(auth()->id());
        $this->assertSame($firstParent->getKey(), $otherType->getKey(), 'The parent-type fixture must reuse the numeric ID.');
        $uploads = [
            $service->upload($firstParent, $this->file()),
            $service->upload($secondParent, $this->file()),
            $service->upload($otherType, $this->file()),
            $service->upload($firstParent, $this->file('UPL001 different bytes')),
        ];

        $this->assertCount(4, array_unique(array_column($uploads, 'id')));
        $this->assertSame(4, Uploaded::query()->count());
        $this->assertCount(4, Storage::disk('local')->allFiles('uploads'));
    }

    public function test_soft_deleted_match_is_not_returned_and_its_existing_file_is_preserved(): void
    {
        $parent = Client::factory()->create();
        $service = app(UploadService::class);
        $first = $service->upload($parent, $this->file());
        $first->delete();
        $next = $service->upload($parent, $this->file());

        $this->assertNotSame($first->id, $next->id);
        $this->assertSame(1, Uploaded::query()->count());
        $this->assertSame(2, Uploaded::withTrashed()->count());
        $this->assertCount(2, Storage::disk('local')->allFiles('uploads'));
    }

    public function test_explicit_default_parent_connection_keeps_uploaded_persistence_connection(): void
    {
        $parent = Client::factory()->create();
        $parent->setConnection(config('database.default'));
        $upload = app(UploadService::class)->upload($parent, $this->file());

        $this->assertSame((new Uploaded)->getConnection(), $upload->getConnection());
        $this->assertDatabaseHas('uploadeds', ['id' => $upload->id, 'parentable_id' => $parent->id]);
    }

    public function test_distinct_parent_connection_is_refused_before_files_or_rows_are_written(): void
    {
        $parent = Client::factory()->create();
        config()->set('database.connections.upl_other_session', config('database.connections.sqlite'));
        $parent->setConnection('upl_other_session');

        try {
            app(UploadService::class)->upload($parent, $this->file());
            $this->fail('A different parent connection/session must be refused.');
        } catch (LogicException) {
            $this->assertSame(0, Uploaded::query()->count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_failed_storage_write_is_not_replayed_and_a_later_explicit_request_recovers(): void
    {
        $real = Storage::disk('local');
        $disk = new class($real) extends FilesystemAdapter
        {
            public int $writes = 0;

            public function __construct(FilesystemAdapter $real)
            {
                parent::__construct($real->getDriver(), $real->getAdapter(), $real->getConfig());
            }

            public function putFileAs(mixed $path, mixed $file, mixed $name = null, mixed $options = []): string|false
            {
                $this->writes++;

                return $this->writes === 1 ? false : parent::putFileAs($path, $file, $name, $options);
            }
        };
        Storage::set('local', $disk);
        $parent = Client::factory()->create();
        $service = app(UploadService::class);

        try {
            $service->upload($parent, $this->file());
            $this->fail('A false filesystem write must not create an upload row.');
        } catch (RuntimeException) {
            $this->assertSame(1, $disk->writes, 'The service must not implicitly retry file writes.');
            $this->assertSame(0, Uploaded::query()->count());
            $this->assertSame([], $real->allFiles());
        }

        $recovered = $service->upload($parent, $this->file());
        $this->assertSame(2, $disk->writes);
        $this->assertSame(1, Uploaded::query()->count());
        $this->assertSame([$recovered->path], $real->allFiles('uploads'));
    }

    public function test_pre_commit_creation_failure_rolls_back_cover_cleans_new_file_and_recovers_once(): void
    {
        $parent = Client::factory()->create();
        $service = app(UploadService::class);
        $old = $service->upload($parent, $this->file('UPL001 old cover'), isCover: true);
        $state = new class
        {
            public bool $fail = true;

            public int $attempts = 0;
        };
        Uploaded::creating(static function () use ($state): void {
            $state->attempts++;

            if ($state->fail) {
                throw new RuntimeException('UPL001 controlled pre-commit create failure.');
            }
        });

        try {
            $service->upload($parent, $this->file(), isCover: true);
            $this->fail('The controlled pre-commit failure must reach the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('UPL001 controlled pre-commit create failure.', $exception->getMessage());
        }

        $this->assertSame(1, $state->attempts, 'Transaction/file operations must not be automatically replayed.');
        $this->assertTrue($old->fresh()->is_cover, 'A failed replacement must preserve the committed cover.');
        $this->assertSame(1, Uploaded::query()->count());
        $this->assertSame([$old->path], Storage::disk('local')->allFiles('uploads'));
        $state->fail = false;
        $recovered = $service->upload($parent, $this->file(), isCover: true);
        $this->assertSame(2, $state->attempts);
        $this->assertTrue($recovered->is_cover);
        $this->assertFalse($old->fresh()->is_cover);
        $this->assertSame(2, Uploaded::query()->count());
        $this->assertCount(2, Storage::disk('local')->allFiles('uploads'));
    }

    private function file(string $bytes = 'UPL001 identical fixture bytes'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('synthetic.txt', $bytes);
    }
}
