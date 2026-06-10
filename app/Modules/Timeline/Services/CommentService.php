<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Services;

use App\Models\User;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommentService
{
    public function __construct(
        private readonly TimelineHtmlSanitizer $html,
    ) {}

    /**
     * @param  array{body: string, body_format?: string}  $data
     */
    public function create(Model $subject, User $author, array $data): TimelineComment
    {
        return DB::transaction(function () use ($subject, $author, $data): TimelineComment {
            return TimelineComment::query()->create([
                'commentable_type' => $subject::class,
                'commentable_id' => $subject->getKey(),
                'user_id' => $author->id,
                ...$this->bodyPayload($data),
            ]);
        });
    }

    /**
     * @param  array{body: string, body_format?: string}  $data
     */
    public function reply(TimelineComment $parent, User $author, array $data): TimelineComment
    {
        return DB::transaction(function () use ($parent, $author, $data): TimelineComment {
            return TimelineComment::query()->create([
                'commentable_type' => $parent->commentable_type,
                'commentable_id' => $parent->commentable_id,
                'parent_id' => $parent->id,
                'user_id' => $author->id,
                ...$this->bodyPayload($data),
            ]);
        });
    }

    /**
     * @param  array{body: string, body_format?: string}  $data
     */
    public function update(TimelineComment $comment, array $data): TimelineComment
    {
        $comment->forceFill([
            ...$this->bodyPayload($data),
            'edited_at' => now(),
        ])->save();

        return $comment->refresh();
    }

    public function delete(TimelineComment $comment): bool
    {
        return (bool) $comment->delete();
    }

    /**
     * @param  array{body: string, body_format?: string}  $data
     * @return array{body: string, body_format: string}
     */
    private function bodyPayload(array $data): array
    {
        $format = ($data['body_format'] ?? 'plain') === 'html' ? 'html' : 'plain';
        $body = $format === 'html'
            ? $this->html->clean($data['body'])
            : trim($data['body']);

        return [
            'body' => $body,
            'body_format' => $body === '' ? 'plain' : $format,
        ];
    }
}
