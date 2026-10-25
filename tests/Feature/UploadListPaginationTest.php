<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Client $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['must_change_password' => false]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'view-clients'], ['name' => 'View Clients', 'group' => 'clients']);
        $this->actor->permissions()->syncWithoutDetaching([$permission->id]);
        $this->parent = Client::factory()->create();
        $this->actingAs($this->actor, 'web');
    }

    public function test_more_than_sixty_tied_rows_have_stable_disjoint_pages_and_collection_totals(): void
    {
        $ids = [];
        for ($index = 1; $index <= 63; $index++) {
            $ids[] = $this->upload(['name' => 'Document '.$index])->id;
        }
        $this->upload(['collection' => 'receipts']);
        $this->upload(['parentable_id' => Client::factory()->create()->id]);
        $this->upload()->delete();
        rsort($ids);

        $first = $this->listing(['collection' => 'documents'])->assertOk()
            ->assertJsonPath('meta.total', 63)->assertJsonPath('meta.last_page', 2)->assertJsonCount(50, 'data');
        $second = $this->listing(['collection' => 'documents', 'page' => 2])->assertOk()->assertJsonCount(13, 'data');
        $this->assertSame(array_slice($ids, 0, 50), $first->json('data.*.id'));
        $this->assertSame(array_slice($ids, 50), $second->json('data.*.id'));
        $this->assertSame([], array_intersect($first->json('data.*.id'), $second->json('data.*.id')));
        $this->assertSame($first->json('data.*.id'), $this->listing(['collection' => 'documents'])->assertOk()->json('data.*.id'));
    }

    #[DataProvider('searchableFields')]
    public function test_remote_search_covers_each_current_field_without_or_leaking_parent_or_filters(string $field): void
    {
        $target = $this->upload([$field => 'needle', 'collection' => 'documents']);
        $this->upload([$field => 'needle', 'collection' => 'receipts']);
        $this->upload([$field => 'needle', 'parentable_id' => Client::factory()->create()->id]);
        $this->upload(['name' => 'ordinary']);

        $response = $this->listing(['collection' => 'documents', 'search' => 'needle'])->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        $this->assertSame([$target->id], $response->json('data.*.id'));
    }

    /** @return array<string, array{string}> */
    public static function searchableFields(): array
    {
        return array_combine(['name', 'original_name', 'file_name', 'tag', 'extension'], array_map(fn (string $field): array => [$field], ['name', 'original_name', 'file_name', 'tag', 'extension']));
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_sql_pattern_characters_as_literal_text(string $needle, string $decoy): void
    {
        $target = $this->upload(['name' => 'before '.$needle.' after']);
        $this->upload(['name' => 'before '.$decoy.' after']);
        $this->listing(['search' => $needle])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    /** @return array<string, array{string, string}> */
    public static function literalSearches(): array
    {
        return ['percent' => ['100%', '1000'], 'underscore' => ['a_b', 'axb'], 'escape' => ['a!b', 'ab'], 'backslash' => ['a\\b', 'ab']];
    }

    public function test_null_list_filters_remain_unfiltered_and_tag_still_constrains_search(): void
    {
        $target = $this->upload(['name' => 'needle', 'tag' => 'evidence']);
        $this->upload(['name' => 'needle', 'tag' => 'other', 'collection' => null]);
        $this->listing(['collection' => null, 'tag' => null])->assertOk()->assertJsonPath('meta.total', 2);
        $this->listing(['tag' => 'evidence', 'search' => 'needle'])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    #[DataProvider('zeroFilters')]
    public function test_literal_zero_filter_is_not_discarded(string $field): void
    {
        $attributes = $field === 'search' ? ['name' => '0'] : [$field => '0'];
        $target = $this->upload($attributes);
        $this->upload(['name' => 'other', 'tag' => 'other', 'collection' => 'other']);
        $this->listing([$field => '0'])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    /** @return array<string, array{string}> */
    public static function zeroFilters(): array
    {
        return ['search' => ['search'], 'tag' => ['tag'], 'collection' => ['collection']];
    }

    /** @param array<string, mixed> $filters */
    private function listing(array $filters = []): TestResponse
    {
        return $this->getJson('/api/v1/uploads?'.http_build_query(['parentable_type' => 'client', 'parentable_id' => $this->parent->id, ...$filters]));
    }

    /** @param array<string, mixed> $attributes */
    private function upload(array $attributes = []): Uploaded
    {
        return Uploaded::query()->create([
            'user_id' => $this->actor->id, 'uploader_id' => $this->actor->id,
            'parentable_type' => Client::class, 'parentable_id' => $this->parent->id,
            'name' => 'Manual', 'file_name' => 'manual.pdf', 'original_name' => 'manual.pdf',
            'mime_type' => 'application/pdf', 'path' => 'ui002/manual.pdf', 'extension' => 'pdf',
            'disk' => 'local', 'size' => 3, 'collection' => 'documents', 'tag' => null,
            'created_at' => '2026-01-01 12:00:00', 'updated_at' => '2026-01-01 12:00:00', ...$attributes,
        ])->refresh();
    }
}
