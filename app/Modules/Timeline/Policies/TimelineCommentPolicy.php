<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Policies;

use App\Models\User;
use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Uploads\Services\UploadService;

class TimelineCommentPolicy
{
    public function __construct(private readonly UploadService $uploads) {}

    public function view(User $user, TimelineComment $comment): bool
    {
        $commentable = $comment->commentable;

        return $commentable !== null && $this->uploads->canViewParent($user, $commentable);
    }

    public function update(User $user, TimelineComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, TimelineComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function reply(User $user, TimelineComment $comment): bool
    {
        return (bool) config('timeline.comments.allow_replies', true)
            && ! $comment->isReply()
            && $this->view($user, $comment);
    }
}
