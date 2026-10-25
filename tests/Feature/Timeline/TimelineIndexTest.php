<?php

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\TestEnvironment;

class TimelineIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_index_returns_comments_and_activity_logs_in_chronological_order(): void
    {
        $user = User::factory()->create(['name' => 'Admin Timeline']);
        $this->grantClientRead($user);
        $client = Client::factory()->create(['name' => 'Cliente Timeline']);
        Activity::query()->delete();

        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $user->id,
            'body' => '<p><strong>Comentario antes do log.</strong></p>',
            'body_format' => 'html',
        ]);
        $comment->forceFill([
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'updated_at' => Carbon::parse('2026-06-09 10:00:00'),
        ])->saveQuietly();

        $reply = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'parent_id' => $comment->id,
            'user_id' => $user->id,
            'body' => 'Reply carregada junto.',
            'body_format' => 'plain',
        ]);

        Activity::query()->create([
            'log_name' => 'clients',
            'description' => 'Admin Timeline editou o cliente Cliente Timeline.',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'updated',
            'properties' => [
                'attributes' => ['email' => 'novo@example.com'],
                'old' => ['email' => 'antigo@example.com'],
            ],
            'created_at' => Carbon::parse('2026-06-09 11:00:00'),
            'updated_at' => Carbon::parse('2026-06-09 11:00:00'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->id}?limit=10")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'comment')
            ->assertJsonPath('data.0.id', $comment->id)
            ->assertJsonPath('data.0.body', '<p><strong>Comentario antes do log.</strong></p>')
            ->assertJsonPath('data.0.body_format', 'html')
            ->assertJsonPath('data.0.replies.0.id', $reply->id)
            ->assertJsonPath('data.1.type', 'activity')
            ->assertJsonPath('data.1.event', 'updated')
            ->assertJsonPath('data.1.label', 'Editou cliente')
            ->assertJsonPath('data.1.causer.name', 'Admin Timeline')
            ->assertJsonPath('data.1.changes.0.field', 'email')
            ->assertJsonPath('data.1.changes.0.label', 'Email')
            ->assertJsonPath('data.1.changes.0.old', 'antigo@example.com')
            ->assertJsonPath('data.1.changes.0.new', 'novo@example.com');
    }

    public function test_timeline_index_can_filter_item_types(): void
    {
        $user = User::factory()->create();
        $this->grantClientRead($user);
        $client = Client::factory()->create();
        Activity::query()->delete();

        TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $user->id,
            'body' => 'Comentario filtrado.',
            'body_format' => 'plain',
        ]);

        Activity::query()->create([
            'log_name' => 'clients',
            'description' => 'Log filtrado.',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'updated',
            'properties' => [],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->id}?types[]=comment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'comment')
            ->assertJsonPath('data.0.body', 'Comentario filtrado.');
    }

    public function test_old_html_root_and_reply_are_sanitized_on_read_without_rewriting_persisted_rows(): void
    {
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $user = User::factory()->create();
        $this->grantClientRead($user);
        $client = Client::factory()->create();
        $oldRoot = '<div><img src="/__timeline_missing" onerror="window.__sec010Executed=(window.__sec010Executed||0)+1"><p><strong>SEC010 safe</strong></p></div>';
        $oldReply = '<section><div><a href="javascript:window.__sec010Executed=(window.__sec010Executed||0)+1">SEC010 link</a><p><em>SEC010 emphasis</em></p></div></section>';
        $attributes = [
            'commentable_type' => Client::class, 'commentable_id' => $client->id, 'user_id' => $user->id,
            'body_format' => 'html', 'created_at' => now(), 'updated_at' => now(),
        ];
        $rootId = DB::table('timeline_comments')->insertGetId($attributes + [
            'uuid' => '30000000-0000-4000-8000-000000000001', 'body' => $oldRoot, 'parent_id' => null,
        ]);
        $replyId = DB::table('timeline_comments')->insertGetId($attributes + [
            'uuid' => '30000000-0000-4000-8000-000000000002', 'body' => $oldReply, 'parent_id' => $rootId,
        ]);
        $before = DB::table('timeline_comments')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->assertSame($oldRoot, DB::table('timeline_comments')->where('id', $rootId)->value('body'));
        $this->assertSame($oldReply, DB::table('timeline_comments')->where('id', $replyId)->value('body'));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->uuid}?types[]=comment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rootId)
            ->assertJsonPath('data.0.body_format', 'html')
            ->assertJsonPath('data.0.body', '<p><strong>SEC010 safe</strong></p>')
            ->assertJsonPath('data.0.replies.0.id', $replyId)
            ->assertJsonPath('data.0.replies.0.body_format', 'html')
            ->assertJsonPath('data.0.replies.0.body', '<a>SEC010 link</a><p><em>SEC010 emphasis</em></p>');

        $this->assertSame($before, DB::table('timeline_comments')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
    }

    public function test_old_plain_body_remains_literal_on_read_without_database_changes(): void
    {
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $user = User::factory()->create();
        $this->grantClientRead($user);
        $client = Client::factory()->create();
        $literal = '<strong>SEC010 literal plain</strong><img src=x onerror="window.__sec010Plain=1">';
        $id = DB::table('timeline_comments')->insertGetId([
            'uuid' => '30000000-0000-4000-8000-000000000003',
            'commentable_type' => Client::class, 'commentable_id' => $client->id, 'user_id' => $user->id,
            'parent_id' => null, 'body' => $literal, 'body_format' => 'plain',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = (array) DB::table('timeline_comments')->where('id', $id)->first();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->uuid}?types[]=comment")
            ->assertOk()
            ->assertJsonPath('data.0.body_format', 'plain')
            ->assertJsonPath('data.0.body', $literal);

        $this->assertSame($before, (array) DB::table('timeline_comments')->where('id', $id)->first());
    }

    private function grantClientRead(User $user): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'view-clients'],
            ['name' => 'View Clients', 'group' => 'clients'],
        );
        $user->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
}
