<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions\DeliveryTasks;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompletePlaylistDeliveryTask
{
    public function handle(PlaylistDeliveryTask $playlistDeliveryTask, User $user, ?string $completionNote = null): PlaylistDeliveryTask
    {
        return DB::transaction(function () use ($playlistDeliveryTask, $user, $completionNote): PlaylistDeliveryTask {
            $task = PlaylistDeliveryTask::query()
                ->whereKey($playlistDeliveryTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($task->status !== PlaylistDeliveryTaskStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => __('Apenas tarefas pendentes podem ser concluídas.'),
                ]);
            }

            $metadata = $task->metadata ?? [];

            if (filled($completionNote)) {
                $metadata['completion_note'] = $completionNote;
            }

            $task->forceFill([
                'status' => PlaylistDeliveryTaskStatus::Completed,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
                'metadata' => $metadata === [] ? null : $metadata,
            ])->save();

            return $task->refresh();
        });
    }
}
