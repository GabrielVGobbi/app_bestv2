<?php

declare(strict_types=1);

namespace App\Supports\StatusTransitions;

class StatusTransitionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly mixed $model,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}
}
