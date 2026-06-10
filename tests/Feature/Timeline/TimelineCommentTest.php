<?php

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_comment_for_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timeline/client/{$client->id}/comments", [
                'body' => 'Precisamos revisar este cliente.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'comment')
            ->assertJsonPath('data.body', 'Precisamos revisar este cliente.')
            ->assertJsonPath('data.author.id', $user->id)
            ->assertJsonPath('data.permissions.can_update', true)
            ->assertJsonPath('data.permissions.can_delete', true)
            ->assertJsonPath('data.permissions.can_reply', true);

        $this->assertDatabaseHas('timeline_comments', [
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $user->id,
            'body' => 'Precisamos revisar este cliente.',
            'body_format' => 'plain',
            'parent_id' => null,
        ]);
    }

    public function test_timeline_index_returns_client_comments(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $user->id,
            'body' => 'Comentario listado na timeline.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->id}")
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'comment',
                'id' => $comment->id,
                'body' => 'Comentario listado na timeline.',
            ])
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_authenticated_user_can_reply_to_root_comment(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Comentario raiz.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($replier, 'sanctum')
            ->postJson("/api/v1/timeline/comments/{$comment->id}/replies", [
                'body' => 'Resposta do comentario.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Resposta do comentario.')
            ->assertJsonPath('data.author.id', $replier->id)
            ->assertJsonPath('data.permissions.can_reply', false);

        $this->assertDatabaseHas('timeline_comments', [
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'parent_id' => $comment->id,
            'user_id' => $replier->id,
            'body' => 'Resposta do comentario.',
        ]);
    }

    public function test_reply_to_reply_is_forbidden(): void
    {
        $author = User::factory()->create();
        $client = Client::factory()->create();
        $root = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Comentario raiz.',
            'body_format' => 'plain',
        ]);
        $reply = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'parent_id' => $root->id,
            'user_id' => $author->id,
            'body' => 'Resposta.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/v1/timeline/comments/{$reply->id}/replies", [
                'body' => 'Resposta aninhada.',
            ])
            ->assertForbidden();
    }

    public function test_comment_author_can_update_comment(): void
    {
        $author = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Texto original.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($author, 'sanctum')
            ->putJson("/api/v1/timeline/comments/{$comment->id}", [
                'body' => 'Texto editado.',
            ])
            ->assertOk()
            ->assertJsonPath('data.body', 'Texto editado.');

        $comment->refresh();

        $this->assertSame('Texto editado.', $comment->body);
        $this->assertNotNull($comment->edited_at);
    }

    public function test_authenticated_user_can_create_html_comment_with_sanitized_body(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timeline/client/{$client->id}/comments", [
                'body_format' => 'html',
                'body' => '<h2>Importante</h2><p><strong>OK</strong><script>alert(1)</script><a href="javascript:alert(1)">bad</a></p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body_format', 'html')
            ->assertJsonPath('data.body', '<h2>Importante</h2><p><strong>OK</strong><a>bad</a></p>');

        $this->assertDatabaseHas('timeline_comments', [
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $user->id,
            'body_format' => 'html',
            'body' => '<h2>Importante</h2><p><strong>OK</strong><a>bad</a></p>',
        ]);
    }

    public function test_comment_author_can_update_comment_format_to_html(): void
    {
        $author = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Texto original.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($author, 'sanctum')
            ->putJson("/api/v1/timeline/comments/{$comment->id}", [
                'body_format' => 'html',
                'body' => '<p><u>Texto editado</u></p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.body_format', 'html')
            ->assertJsonPath('data.body', '<p><u>Texto editado</u></p>');

        $comment->refresh();

        $this->assertSame('html', $comment->body_format);
        $this->assertSame('<p><u>Texto editado</u></p>', $comment->body);
    }

    public function test_non_author_cannot_update_or_delete_comment(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Comentario protegido.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/v1/timeline/comments/{$comment->id}", [
                'body' => 'Tentativa externa.',
            ])
            ->assertForbidden();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/timeline/comments/{$comment->id}")
            ->assertForbidden();
    }

    public function test_comment_author_can_soft_delete_comment(): void
    {
        $author = User::factory()->create();
        $client = Client::factory()->create();
        $comment = TimelineComment::query()->create([
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => $author->id,
            'body' => 'Comentario removivel.',
            'body_format' => 'plain',
        ]);

        $this->actingAs($author, 'sanctum')
            ->deleteJson("/api/v1/timeline/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comentario removido com sucesso.');

        $this->assertSoftDeleted('timeline_comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_comment_body_is_required(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timeline/client/{$client->id}/comments", [
                'body' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_timeline_comment_upload_alias_is_registered(): void
    {
        $this->assertSame(TimelineComment::class, config('uploads.aliases.timeline-comment'));
    }
}
