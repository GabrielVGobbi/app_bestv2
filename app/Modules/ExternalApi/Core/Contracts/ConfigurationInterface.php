<?php

namespace App\Modules\ExternalApi\Core\Contracts;

interface ConfigurationInterface
{
    public function getModuleKey(): string;

    public function getBaseUrl(): string;

    public function getApiKey(): string;

    public function getTimeout(): int;

    public function getConnectTimeout(): int;

    public function getRetryAttempts(): int;

    public function getRateLimitPerMinute(): int;

    public function isFake(): bool;

    public function validate(): void;
}
