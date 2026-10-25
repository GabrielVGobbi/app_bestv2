<?php

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TimelineCursorTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Client $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['must_change_password' => false]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'view-clients'], ['name' => 'View Clients', 'group' => 'clients']);
        $this->actor->permissions()->syncWithoutDetaching([$permission->getKey()]);
        $this->subject = Client::factory()->create();
        Activity::query()->delete();
        $this->actingAs($this->actor, 'web');
    }

    public function test_107_mixed_roots_with_identical_timestamps_are_complete_in_50_50_7_pages(): void
    {
        $ids = $this->seedMixed();
        $this->assertContains(9, $ids['comments']);
        $this->assertContains(10, $ids['comments']);
        $this->assertContains(9, $ids['activities']);
        $this->assertContains(10, $ids['activities']);
        $pages = $this->walk(50);

        $this->assertSame([50, 50, 7], array_map('count', $pages));
        $this->assertSame($this->expectedMixed($ids), $this->chronological($pages));
        $this->assertSame(107, count(array_unique($this->chronological($pages))));
    }

    public function test_limit_one_uses_numeric_ids_and_source_rank_even_when_source_ids_collide(): void
    {
        $ids = $this->seedMixed();
        $pages = $this->walk(1);

        $this->assertCount(107, $pages);
        $this->assertSame($this->expectedMixed($ids), $this->chronological($pages));
        $this->assertContains('comment:9', $this->chronological($pages));
        $this->assertContains('activity:9', $this->chronological($pages));
    }

    public function test_more_than_fifty_null_date_roots_of_both_sources_keep_the_same_complete_numeric_order(): void
    {
        $ids = $this->seedMixed(53, 54, null);
        $pages = $this->walk(50);

        $this->assertSame([50, 50, 7], array_map('count', $pages));
        $this->assertSame($this->expectedMixed($ids), $this->chronological($pages));
    }

    public function test_the_real_authenticated_cursor_round_trips_beyond_the_old_255_character_request_limit(): void
    {
        $this->seedMixed(3, 3);
        $first = $this->index(['limit' => 2])->assertOk();
        $cursor = $this->cursor($first);
        $this->assertGreaterThan(255, strlen($cursor));
        $this->assertLessThanOrEqual(2048, strlen($cursor));
        $second = $this->index(['limit' => 2, 'cursor' => $cursor])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([], array_intersect($this->keys($first->json('data')), $this->keys($second->json('data'))));
    }

    #[DataProvider('endBoundaries')]
    public function test_next_cursor_is_present_only_while_an_actual_older_root_remains(int $count, array $sizes): void
    {
        $ids = $this->seedMixed($count, 0);
        $pages = $this->walk(50);

        $this->assertSame($sizes, array_map('count', $pages));
        $this->assertSame($this->expectedMixed($ids), $this->chronological($pages));
    }

    /** @return array<string, array{int, list<int>}> */
    public static function endBoundaries(): array
    {
        return ['empty' => [0, [0]], 'exactly fifty' => [50, [50]], 'fifty one' => [51, [50, 1]]];
    }

    public function test_each_source_hydrates_at_most_limit_plus_one_roots_for_the_lookahead(): void
    {
        $this->seedMixed();
        $observed = new class
        {
            public int $comments = 0;

            public int $activities = 0;
        };
        TimelineComment::retrieved(static function (TimelineComment $comment) use ($observed): void {
            if ($comment->parent_id === null) {
                $observed->comments++;
            }
        });
        Activity::retrieved(static function () use ($observed): void {
            $observed->activities++;
        });

        $response = $this->index(['limit' => 50])->assertOk()->assertJsonCount(50, 'data');
        $this->cursor($response);
        $this->assertLessThanOrEqual(51, $observed->comments);
        $this->assertLessThanOrEqual(51, $observed->activities);
    }

    public function test_short_long_zero_microseconds_and_null_legacy_dates_share_one_precise_total_order(): void
    {
        $commentNull = $this->comment(null);
        $activityNull = $this->activity(null);
        $commentZeroLong = $this->comment('2026-06-09 10:00:00.000000');
        $commentZeroShort = $this->comment('2026-06-09 10:00:00');
        $activityZeroLong = $this->activity('2026-06-09 10:00:00.000000');
        $activityZeroShort = $this->activity('2026-06-09 10:00:00');
        $microOne = $this->comment('2026-06-09 10:00:00.000001');
        $microTwo = $this->activity('2026-06-09 10:00:00.000002');
        $commentTenthLong = $this->comment('2026-06-09 10:00:00.100000');
        $commentTenthMedium = $this->comment('2026-06-09 10:00:00.10');
        $commentTenthShort = $this->comment('2026-06-09 10:00:00.1');
        $activityTenthLong = $this->activity('2026-06-09 10:00:00.100000');
        $activityTenthMedium = $this->activity('2026-06-09 10:00:00.10');
        $activityTenthShort = $this->activity('2026-06-09 10:00:00.1');
        $adjacent = $this->comment('2026-06-09 10:00:00.100001');
        $secondNine = $this->comment('2026-06-09 10:00:09.999999');
        $secondTen = $this->activity('2026-06-09 10:00:10');

        $pages = $this->walk(1);
        $this->assertSame([
            'comment:'.$commentNull->id, 'activity:'.$activityNull->id,
            'comment:'.$commentZeroLong->id, 'comment:'.$commentZeroShort->id,
            'activity:'.$activityZeroLong->id, 'activity:'.$activityZeroShort->id,
            'comment:'.$microOne->id, 'activity:'.$microTwo->id,
            'comment:'.$commentTenthLong->id, 'comment:'.$commentTenthMedium->id, 'comment:'.$commentTenthShort->id,
            'activity:'.$activityTenthLong->id, 'activity:'.$activityTenthMedium->id, 'activity:'.$activityTenthShort->id,
            'comment:'.$adjacent->id, 'comment:'.$secondNine->id, 'activity:'.$secondTen->id,
        ], $this->chronological($pages));
        $nulls = $this->index(['limit' => 100])->assertOk()->json('data');
        $this->assertNull($nulls[0]['created_at']);
        $this->assertNull($nulls[1]['created_at']);
    }

    public function test_legacy_before_excludes_nulls_and_its_cutoff_survives_every_cursor_page(): void
    {
        $this->comment(null);
        $this->activity(null);
        $oldest = $this->comment('2026-06-09 09:58:00');
        $older = $this->activity('2026-06-09 09:59:00');
        $this->comment('2026-06-09 10:00:00');
        $this->activity('2026-06-09 11:00:00');

        $pages = $this->walk(1, ['before' => '2026-06-09 10:00:00']);
        $this->assertSame([1, 1], array_map('count', $pages));
        $this->assertSame(['comment:'.$oldest->id, 'activity:'.$older->id], $this->chronological($pages));
    }

    public function test_before_offsets_are_normalized_to_the_existing_sao_paulo_storage_timezone_with_microseconds(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->comment(null);
        $target = $this->comment('2026-06-09 10:00:00.000001');
        $this->activity('2026-06-09 10:00:00.000002');
        $this->comment('2026-06-09 12:00:00');

        foreach (['2026-06-09 10:00:00.000002', '2026-06-09T10:00:00.000002-03:00', '2026-06-09T13:00:00.000002Z'] as $before) {
            $response = $this->index(['before' => $before])->assertOk()->assertJsonCount(1, 'data');
            $this->assertSame(['comment:'.$target->id], $this->keys($response->json('data')));
            $response->assertJsonPath('meta.next_cursor', null);
        }
    }

    public function test_selected_types_are_bound_to_the_cursor_and_system_only_stays_empty(): void
    {
        $ids = $this->seedMixed(4, 4);
        $commentPages = $this->walk(2, ['types' => ['comment']]);
        $activityPages = $this->walk(2, ['types' => ['activity']]);
        $this->assertSame(array_map(fn (int $id): string => 'comment:'.$id, $ids['comments']), $this->chronological($commentPages));
        $this->assertSame(array_map(fn (int $id): string => 'activity:'.$id, $ids['activities']), $this->chronological($activityPages));
        $this->index(['types' => ['system']])->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.next_cursor', null);

        $cursor = $this->cursor($this->index(['limit' => 2, 'types' => ['comment']])->assertOk());
        $this->index(['cursor' => $cursor, 'types' => ['activity']])->assertUnprocessable()->assertJsonValidationErrors('cursor');
        $this->index(['cursor' => $cursor])->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_equivalent_type_order_and_page_size_changes_keep_the_same_boundary(): void
    {
        $ids = $this->seedMixed(3, 3);
        $first = $this->index(['limit' => 1, 'types' => ['comment', 'activity']])->assertOk();
        $cursor = $this->cursor($first);
        $second = $this->index(['cursor' => $cursor, 'limit' => 3, 'types' => ['activity', 'comment', 'comment']])->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(array_slice($this->expectedMixed($ids), -1), $this->keys($first->json('data')));
        $this->assertSame(array_slice($this->expectedMixed($ids), -4, 3), $this->keys($second->json('data')));
        $this->assertSame([], array_intersect($this->keys($first->json('data')), $this->keys($second->json('data'))));
    }

    public function test_cursor_and_before_are_mutually_exclusive_and_tampered_cursor_is_rejected(): void
    {
        $this->seedMixed(3, 3);
        $cursor = $this->cursor($this->index(['limit' => 2])->assertOk());
        $this->index(['cursor' => $cursor, 'before' => '2026-06-09 12:00:00'])->assertUnprocessable();
        $cursor[0] = $cursor[0] === 'A' ? 'B' : 'A';
        $this->index(['cursor' => $cursor])->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_cursor_shape_and_request_filters_are_rejected(string $kind): void
    {
        $payload = match ($kind) {
            'malformed' => ['cursor' => 'not-a-valid-cursor!'],
            'long' => ['cursor' => str_repeat('a', 2049)],
            'array' => ['cursor' => ['not-a-string']],
            'encrypted-invalid-json' => ['cursor' => Crypt::encryptString('{invalid-json')],
            'encrypted-array' => ['cursor' => Crypt::encryptString('[]')],
            'encrypted-scalar' => ['cursor' => Crypt::encryptString('null')],
            'before' => ['before' => 'not-a-date'],
            'limit' => ['limit' => 101],
            'types' => ['types' => ['private']],
            default => throw new \InvalidArgumentException('Unknown cursor validation fixture: '.$kind),
        };
        $this->index($payload)->assertUnprocessable();
    }

    /** @return array<string, array{string}> */
    public static function invalidInputs(): array
    {
        $kinds = ['malformed', 'long', 'array', 'encrypted-invalid-json', 'encrypted-array', 'encrypted-scalar', 'before', 'limit', 'types'];

        return array_combine($kinds, array_map(fn (string $kind): array => [$kind], $kinds));
    }

    #[DataProvider('invalidAuthenticatedFields')]
    public function test_each_authenticated_cursor_field_is_validated_independently(string $field, mixed $invalidValue): void
    {
        $this->seedMixed(3, 3);
        $cursor = $this->cursor($this->index(['limit' => 2])->assertOk());
        $payload = json_decode(Crypt::decryptString($cursor), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey($field, $payload);
        $original = $payload;
        $payload[$field] = $invalidValue;
        $this->assertSame([$field], array_keys(array_filter($payload, fn (mixed $value, string $key): bool => $value !== $original[$key], ARRAY_FILTER_USE_BOTH)));

        $modifiedCursor = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $this->index(['cursor' => $modifiedCursor])->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidAuthenticatedFields(): array
    {
        return [
            'version' => ['v', 99],
            'timestamp' => ['timestamp', '2026-99-99 25:61:61.000000'],
            'rank' => ['rank', 99],
            'id' => ['id', -1],
            'scope' => ['scope', str_repeat('0', 64)],
            'types' => ['types', ['system']],
        ];
    }

    public function test_deleting_the_boundary_and_inserting_a_newer_row_does_not_replay_or_skip_the_remaining_history(): void
    {
        $ids = $this->seedMixed(3, 3);
        $first = $this->index(['limit' => 2])->assertOk();
        $cursor = $this->cursor($first);
        $anchorId = (int) substr($first->json('data.0.id'), strlen('activity:'));
        Activity::query()->findOrFail($anchorId)->delete();
        $newer = $this->activity('2026-06-09 10:00:00');
        $remaining = $this->walk(2, ['cursor' => $cursor]);
        $combined = [...$this->chronological($remaining), ...$this->keys($first->json('data'))];

        $this->assertSame($this->expectedMixed($ids), $combined);
        $this->assertNotContains('activity:'.$newer->id, $combined);
        $this->assertCount(6, array_unique($combined));
    }

    public function test_other_parents_replies_and_soft_deleted_roots_cannot_leak_into_any_page_and_old_html_is_sanitized(): void
    {
        $ids = $this->seedMixed(3, 3);
        $old = '<div><img src="/__timeline_missing" onerror="window.__ui003Old=1"><p><strong>UI003 safe root</strong></p></div>';
        $root = $this->comment('2026-06-09 09:00:00', ['body' => $old, 'body_format' => 'html']);
        $reply = $this->comment('2026-06-09 10:00:00', ['parent_id' => $root->id, 'body' => '<section><a href="javascript:window.__ui003Reply=1">UI003 reply</a></section>', 'body_format' => 'html']);
        $this->comment('2026-06-09 10:00:00')->delete();
        $other = Client::factory()->create();
        $this->comment('2026-06-09 10:00:00', ['commentable_id' => $other->id]);
        $this->activity('2026-06-09 10:00:00', ['subject_id' => $other->id]);
        $before = DB::table('timeline_comments')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        $pages = [];
        $parameters = ['limit' => 2];
        $foundOldRoot = false;
        for ($iteration = 0; $iteration < 10; $iteration++) {
            $response = $this->index($parameters)->assertOk();
            $items = $response->json('data');
            $pages[] = $this->keys($items);
            foreach ($items as $item) {
                if ($item['type'] !== 'comment' || $item['id'] !== $root->id) {
                    continue;
                }
                $this->assertArrayHasKey('cursor', $parameters, 'The legacy HTML root must be checked on its actual continuation page.');
                $this->assertNotSame('', $parameters['cursor']);
                $this->assertSame('<p><strong>UI003 safe root</strong></p>', $item['body']);
                $this->assertSame($reply->id, $item['replies'][0]['id']);
                $this->assertSame('<a>UI003 reply</a>', $item['replies'][0]['body']);
                $foundOldRoot = true;
            }
            if ($response->json('meta.next_cursor') === null) {
                break;
            }
            $parameters['cursor'] = $this->cursor($response);
        }

        $this->assertTrue($foundOldRoot, 'A continuation response must include and sanitize the oldest legacy HTML root and its reply.');
        $this->assertSame(['comment:'.$root->id, ...$this->expectedMixed($ids)], $this->chronological($pages));
        $this->assertSame($before, DB::table('timeline_comments')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all());
    }

    /** @param array<string, mixed> $parameters */
    private function index(array $parameters = [], int|string|null $subject = null): TestResponse
    {
        return $this->getJson('/api/v1/timeline/client/'.($subject ?? $this->subject->id).'?'.http_build_query($parameters));
    }

    /** @return array{comments: list<int>, activities: list<int>} */
    private function seedMixed(int $comments = 53, int $activities = 54, ?string $timestamp = '2026-06-09 10:00:00'): array
    {
        $ids = ['comments' => [], 'activities' => []];
        for ($index = 0; $index < $comments; $index++) {
            $ids['comments'][] = (int) $this->comment($timestamp)->id;
        }
        for ($index = 0; $index < $activities; $index++) {
            $ids['activities'][] = (int) $this->activity($timestamp)->id;
        }

        return $ids;
    }

    /** @param array{comments: list<int>, activities: list<int>} $ids @return list<string> */
    private function expectedMixed(array $ids): array
    {
        return [...array_map(fn (int $id): string => 'comment:'.$id, $ids['comments']), ...array_map(fn (int $id): string => 'activity:'.$id, $ids['activities'])];
    }

    /** @param array<string, mixed> $filters @return list<list<string>> */
    private function walk(int $limit, array $filters = []): array
    {
        $pages = [];
        $cursors = [];
        $seen = [];
        $parameters = ['limit' => $limit, ...$filters];
        for ($iteration = 0; $iteration < 200; $iteration++) {
            $response = $this->index($parameters)->assertOk();
            $keys = $this->keys($response->json('data'));
            $this->assertLessThanOrEqual($limit, count($keys));
            $this->assertSame([], array_intersect($seen, $keys), 'A cursor page repeated a previously returned root.');
            $seen = [...$seen, ...$keys];
            $pages[] = $keys;
            $next = $response->json('meta.next_cursor');
            if ($next === null) {
                return $pages;
            }
            $this->assertIsString($next);
            $this->assertNotSame('', $next);
            $this->assertLessThanOrEqual(2048, strlen($next));
            $this->assertNotContains($next, $cursors, 'The cursor must advance even when timestamps are identical.');
            $this->assertNotSame([], $keys, 'An empty page cannot advertise another cursor.');
            $cursors[] = $next;
            unset($parameters['before']);
            $parameters['cursor'] = $next;
        }
        $this->fail('The finite timeline cursor fixture did not terminate.');
    }

    /** @param list<list<string>> $pages @return list<string> */
    private function chronological(array $pages): array
    {
        return array_merge(...array_reverse($pages));
    }

    /** @param list<array{type: string, id: int|string}> $items @return list<string> */
    private function keys(array $items): array
    {
        return array_map(fn (array $item): string => $item['type'] === 'comment' ? 'comment:'.$item['id'] : (string) $item['id'], $items);
    }

    private function cursor(TestResponse $response): string
    {
        $cursor = $response->json('meta.next_cursor');
        $this->assertIsString($cursor, 'A truncated nonfinal timeline must provide an opaque cursor.');
        $this->assertNotSame('', $cursor);

        return $cursor;
    }

    /** @param array<string, mixed> $attributes */
    private function comment(?string $createdAt, array $attributes = []): TimelineComment
    {
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class, 'commentable_id' => $this->subject->id,
            'user_id' => $this->actor->id, 'body' => 'UI003 cursor root', 'body_format' => 'plain', ...$attributes,
        ]);
        DB::table('timeline_comments')->where('id', $comment->id)->update(['created_at' => $createdAt]);

        return $comment->refresh();
    }

    /** @param array<string, mixed> $attributes */
    private function activity(?string $createdAt, array $attributes = []): Activity
    {
        $activity = Activity::query()->create([
            'log_name' => 'clients', 'description' => 'UI003 cursor activity', 'subject_type' => Client::class,
            'subject_id' => $this->subject->id, 'causer_type' => User::class, 'causer_id' => $this->actor->id,
            'event' => 'updated', 'properties' => [], ...$attributes,
        ]);
        DB::table($activity->getTable())->where('id', $activity->id)->update(['created_at' => $createdAt]);

        return $activity->refresh();
    }
}
