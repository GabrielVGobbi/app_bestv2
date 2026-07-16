<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests;

use App\Models\User;
use App\Modules\Playlists\Models\PlaylistSlot;
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
        return $user->hasAnyPermission($slug);
    }
}
