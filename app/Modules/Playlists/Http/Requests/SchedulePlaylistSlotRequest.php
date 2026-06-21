<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class SchedulePlaylistSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $playlistSlot = $this->route('playlistSlot');
        $user = $this->user();

        return $playlistSlot instanceof PlaylistSlot
            && $user instanceof User
            && $this->hasPermission($user, 'playlist-slots.schedule');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    private function hasPermission(User $user, string $slug): bool
    {
        $superRole = config('permissions.super_role', 'developer');

        if ($user->roles()->where('slug', $superRole)->exists()) {
            return true;
        }

        if ($user->permissions()->where('slug', $slug)->exists()) {
            return true;
        }

        return Permission::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
