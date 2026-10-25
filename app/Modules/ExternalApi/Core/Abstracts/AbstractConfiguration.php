<?php

namespace App\Modules\ExternalApi\Core\Abstracts;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;

abstract class AbstractConfiguration implements ConfigurationInterface
{
    public function getApiKey(): string
    {
        return (string) config($this->configKey('api_key'), '');
    }

    public function getTimeout(): int
    {
        return (int) config($this->configKey('timeout'), 30);
    }

    public function getConnectTimeout(): int
    {
        return (int) config($this->configKey('connect_timeout'), 10);
    }

    public function getRetryAttempts(): int
    {
        return (int) config($this->configKey('retry_attempts'), 3);
    }

    public function getRateLimitPerMinute(): int
    {
        return (int) config($this->configKey('rate_limit'), 100);
    }

    public function isFake(): bool
    {
        $moduleFake = config($this->configKey('fake'));

        if ($moduleFake === null) {
            return (bool) config('external-apis.fake.enabled', true);
        }

        return (bool) $moduleFake;
    }

    protected function configKey(string $key): string
    {
        return "external-apis.modules.{$this->getModuleKey()}.{$key}";
    }

    public function validate(): void
    {
        if (empty($this->getBaseUrl())) {
            throw new ConfigurationException('Base URL is required');
        }

        if (! $this->isFake() && empty($this->getApiKey())) {
            throw new ConfigurationException('API key is required');
        }

        if ($this->getTimeout() <= 0) {
            throw new ConfigurationException('Timeout must be greater than 0');
        }

        if ($this->getConnectTimeout() <= 0) {
            throw new ConfigurationException('Connect timeout must be greater than 0');
        }

        if ($this->getRetryAttempts() < 0) {
            throw new ConfigurationException('Retry attempts cannot be negative');
        }

        if ($this->getRateLimitPerMinute() <= 0) {
            throw new ConfigurationException('Rate limit must be greater than 0');
        }
    }
}
