<?php

namespace App\Modules\ExternalApi\Core\DTOs;

abstract class BaseRequest
{
    abstract public function toArray(): array;

    public function validate(): void
    {
        // Override in child classes for validation
    }
}
