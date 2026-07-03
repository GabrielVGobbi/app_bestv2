<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlaylistSlotTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $playlistSlot = $this->route('playlistSlot');
        $user = $this->user();

        return $playlistSlot instanceof PlaylistSlot
            && $user instanceof User
            && $this->hasPermission($user, 'playlist-delivery-tasks.change-track');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'track_name' => ['required', 'string', 'max:255'],
            'track_spotify_url' => ['nullable', 'required_without:track_spotify_id', 'string', 'max:500'],
            'track_spotify_id' => ['nullable', 'required_without:track_spotify_url', 'string', 'max:100'],
            'track_artists' => ['nullable', 'array'],
            'track_album_name' => ['nullable', 'string', 'max:255'],
            'track_duration_ms' => ['nullable', 'integer', 'min:0'],
            'track_isrc' => ['nullable', 'string', 'max:50'],
            'track_image_url' => ['nullable', 'url', 'max:500'],
            'track_metadata' => ['nullable', 'array'],
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
