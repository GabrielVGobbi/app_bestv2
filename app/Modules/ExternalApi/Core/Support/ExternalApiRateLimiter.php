<?php

namespace App\Modules\ExternalApi\Core\Support;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExternalApiRateLimiter
{
    public function attempt(ConfigurationInterface $configuration, string $method, string $endpoint, array $options = []): void
    {
        $limit = $this->limit($configuration, $endpoint);

        if ($limit <= 0) {
            return;
        }

        $key = $this->key($configuration, $method, $endpoint, $options);
        $decay = (int) config('external-apis.rate_limit.decay_seconds', 60);
        $lockSeconds = (int) config('external-apis.rate_limit.lock_seconds', 3);
        $lock = Cache::lock("{$key}:lock", $lockSeconds);

        $lock->block($lockSeconds, function () use ($key, $limit, $decay): void {
            Cache::add($key, 0, $decay);

            $current = Cache::increment($key);

            if ($current > $limit) {
                throw new RateLimitException("Rate limit of {$limit} requests per minute exceeded");
            }
        });
    }

    public function remaining(ConfigurationInterface $configuration, string $method, string $endpoint): int
    {
        $limit = $this->limit($configuration, $endpoint);

        return max(0, $limit - (int) Cache::get($this->key($configuration, $method, $endpoint), 0));
    }

    private function limit(ConfigurationInterface $configuration, string $endpoint): int
    {
        $endpointLimit = config("external-apis.modules.{$configuration->getModuleKey()}.endpoints.{$this->endpointKey($endpoint)}.rate_limit");

        return (int) ($endpointLimit ?? $configuration->getRateLimitPerMinute());
    }

    private function key(ConfigurationInterface $configuration, string $method, string $endpoint, array $options = []): string
    {
        $authContext = $this->authContext($options);

        return implode(':', [
            'external-api',
            'rate-limit',
            $configuration->getModuleKey(),
            Str::lower($method),
            $this->endpointKey($endpoint),
            $authContext,
            now()->format('YmdHi'),
        ]);
    }

    private function authContext(array $options): string
    {
        $headers = $options['headers'] ?? [];
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? 'public';

        return hash('xxh128', (string) $authorization);
    }

    private function endpointKey(string $endpoint): string
    {
        $normalized = trim(parse_url($endpoint, PHP_URL_PATH) ?: $endpoint, '/');

        if ($normalized === '') {
            return 'root';
        }

        return str_replace('/', '.', explode('/', $normalized)[0]);
    }
}
