<?php

declare(strict_types=1);

namespace App\Supports\StatusTransitions;

use App\Supports\Contracts\StatusableEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StatusTransitionService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Model $model,
        StatusableEnum|string $newStatus,
        ?string $reason = null,
        array $metadata = [],
        bool $confirmed = false,
    ): StatusTransitionResult {
        if (! method_exists($model, 'updateStatus') || ! method_exists($model, 'statusEnum')) {
            throw new \InvalidArgumentException('Model does not support status transitions.');
        }

        /** @var StatusableEnum $resolvedStatus */
        $resolvedStatus = $model->statusEnum($newStatus);

        if ($resolvedStatus->requiresConfirmation() && ! $confirmed) {
            throw ValidationException::withMessages([
                'status_confirmed' => __('Confirme a alteração de status antes de continuar.'),
            ]);
        }

        if ($resolvedStatus->requiresReason() && blank($reason)) {
            throw ValidationException::withMessages([
                'status_reason' => __('Informe o motivo da alteração de status.'),
            ]);
        }

        /** @var StatusableEnum $fromStatus */
        $fromStatus = $model->status;

        $model->updateStatus($resolvedStatus, $reason, $metadata);

        return new StatusTransitionResult(
            model: $model->refresh(),
            fromStatus: $fromStatus->getValue(),
            toStatus: $resolvedStatus->getValue(),
            reason: $reason,
            metadata: $metadata,
        );
    }
}
