<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Policies;

use App\Models\User;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Services\UploadService;

class UploadedPolicy
{
    public function __construct(private readonly UploadService $uploads) {}

    public function view(User $user, Uploaded $upload): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        $parentable = $upload->parentable;

        return $parentable !== null && $this->uploads->canViewParent($user, $parentable);
    }

    public function update(User $user, Uploaded $upload): bool
    {
        return $this->canManage($user, $upload);
    }

    public function delete(User $user, Uploaded $upload): bool
    {
        return $this->canManage($user, $upload);
    }

    public function setCover(User $user, Uploaded $upload): bool
    {
        if (! $this->view($user, $upload)) {
            return false;
        }

        $parentable = $upload->parentable;

        return $parentable !== null && $this->uploads->canCreateForParent($user, $parentable);
    }

    private function canManage(User $user, Uploaded $upload): bool
    {
        if (! $this->view($user, $upload)) {
            return false;
        }

        $parentable = $upload->parentable;

        return $parentable !== null && ($upload->uploader_id === $user->id
            || $upload->user_id === $user->id
            || $this->uploads->canCreateForParent($user, $parentable));
    }
}
