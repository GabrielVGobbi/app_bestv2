<?php

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TimelineIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_index_returns_comments_and_activity_logs_in_chronological_order(): void
    {
        $user = User::factory()->create(['name' => 'Admin Timeline']);
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
}
