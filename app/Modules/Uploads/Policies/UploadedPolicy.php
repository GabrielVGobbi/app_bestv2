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
        return $this->isOwner($user, $upload);
    }

    public function update(User $user, Uploaded $upload): bool
    {
        return $this->isOwner($user, $upload);
    }

    public function delete(User $user, Uploaded $upload): bool
    {
        return $this->isOwner($user, $upload);
    }

    protected function isOwner(User $user, Uploaded $upload): bool
    {
        $parentable = $upload->parentable;
        if ($parentable !== null && $this->uploads->canViewParent($user, $parentable)) {
            return true;
        }

        return $upload->uploader_id === $user->id
            || $upload->user_id === $user->id;
    }
}
