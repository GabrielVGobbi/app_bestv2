<?php

declare(strict_types=1);

namespace App\Supports\Contracts;

interface StatusableEnum extends BadgeableEnum
{
    public function getActionLabel(): string;

    public function canTransitionTo(self $newStatus): bool;

    /** @return array<int, self> */
    public function getAvailableTransitions(): array;

    /** @return array<string, mixed> */
    public function getStatusFields(): array;

    /** @return array<string, mixed> */
    public function getLogMetadata(): array;

    public function requiresReason(): bool;

    public function requiresConfirmation(): bool;

    /** @return array<string, mixed> */
    public function toTransitionArray(): array;
}
