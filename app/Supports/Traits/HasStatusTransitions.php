<?php

declare(strict_types=1);

namespace App\Supports\Traits;

use App\Supports\Contracts\StatusableEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait HasStatusTransitions
{
    private bool $statusTransitionInProgress = false;

    protected static function bootHasStatusTransitions(): void
    {
        static::updating(function ($model): void {
            if ($model->isDirty('status') && ! $model->statusTransitionInProgress) {
                throw new \LogicException('Status must be changed through the status transition flow.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function updateStatus(StatusableEnum|string $newStatus, ?string $reason = null, array $metadata = []): bool
    {
        $currentStatus = $this->statusEnum($this->status);
        $resolvedStatus = $this->statusEnum($newStatus);

        if (! $currentStatus->canTransitionTo($resolvedStatus)) {
            throw ValidationException::withMessages([
                'status' => __('A transição de status informada não é permitida.'),
            ]);
        }

        return $this->performStatusUpdate($currentStatus, $resolvedStatus, $reason, $metadata);
    }

    public function canUpdateStatus(StatusableEnum|string $newStatus): bool
    {
        return $this->statusEnum($this->status)->canTransitionTo($this->statusEnum($newStatus));
    }

    /** @return array<int, StatusableEnum> */
    public function getAvailableStatusTransitions(): array
    {
        return $this->statusEnum($this->status)->getAvailableTransitions();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function applySystemStatus(StatusableEnum|string $newStatus, ?string $reason = null, array $metadata = []): bool
    {
        $currentStatus = $this->statusEnum($this->status);
        $resolvedStatus = $this->statusEnum($newStatus);

        return $this->performStatusUpdate($currentStatus, $resolvedStatus, $reason, [
            ...$metadata,
            'system_bypass' => true,
        ]);
    }

    public function statusEnum(StatusableEnum|string $status): StatusableEnum
    {
        if ($status instanceof StatusableEnum) {
            return $status;
        }

        $enumClass = $this->statusEnumClass();

        return $enumClass::from($status);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function performStatusUpdate(
        StatusableEnum $currentStatus,
        StatusableEnum $resolvedStatus,
        ?string $reason,
        array $metadata,
    ): bool {
        $this->statusTransitionInProgress = true;

        try {
            $this->status = $resolvedStatus;
            $this->applyStatusFields($resolvedStatus, $reason);

            $saved = $this->save();
        } finally {
            $this->statusTransitionInProgress = false;
        }

        if ($saved) {
            $this->logStatusTransition($currentStatus, $resolvedStatus, $reason, $metadata);
        }

        return $saved;
    }

    private function applyStatusFields(StatusableEnum $status, ?string $reason): void
    {
        foreach ($status->getStatusFields() as $field => $value) {
            if (! $this->hasStatusColumn($field)) {
                continue;
            }

            $this->{$field} = match ($value) {
                'now()' => now(),
                'user_id' => auth()->id(),
                default => $value,
            };
        }

        if ($status->requiresReason() && $this->hasStatusColumn('cancellation_reason')) {
            $this->cancellation_reason = $reason;
        }
    }

    private function hasStatusColumn(string $field): bool
    {
        return Schema::hasColumn($this->getTable(), $field);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logStatusTransition(
        StatusableEnum $fromStatus,
        StatusableEnum $toStatus,
        ?string $reason,
        array $metadata,
    ): void {
        if (! method_exists($this, 'logEvent')) {
            return;
        }

        $this->logEvent('status_changed', [
            'from_status' => $fromStatus->toPresenterArray(),
            'to_status' => $toStatus->toPresenterArray(),
            'status_field' => 'status',
            'domain' => $metadata['domain'] ?? 'sales',
            'reason' => $reason,
            'action' => $metadata['action'] ?? null,
            'user_id' => auth()->id(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
