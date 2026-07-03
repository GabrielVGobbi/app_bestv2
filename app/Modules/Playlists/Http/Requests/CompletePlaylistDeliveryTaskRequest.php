<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests;

use App\Models\User;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use Illuminate\Foundation\Http\FormRequest;

class CompletePlaylistDeliveryTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('playlistDeliveryTask');
        $user = $this->user();

        return $task instanceof PlaylistDeliveryTask
            && $user instanceof User
            && $user->can('complete', $task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'completion_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
