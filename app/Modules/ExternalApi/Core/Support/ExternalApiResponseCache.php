<?php

namespace App\Modules\ExternalApi\Core\Support;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\DTOs\BaseResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExternalApiResponseCache
{
    public function get(ConfigurationInterface $configuration, string $method, string $url, string $endpoint, array $options = []): ?BaseResponse
    {
        if (! $this->shouldCache($configuration, $method, $endpoint)) {
            return null;
        }

        $payload = Cache::get($this->key($configuration, $method, $url, $options));

        if (! is_array($payload)) {
            return null;
        }

        return new BaseResponse(
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            statusCode: (int) ($payload['status_code'] ?? 200),
            headers: is_array($payload['headers'] ?? null) ? $payload['headers'] : [],
        );
    }

    public function put(ConfigurationInterface $configuration, string $method, string $url, string $endpoint, array $options, BaseResponse $response): void
    {
        if (! $response->isSuccessful() || ! $this->shouldCache($configuration, $method, $endpoint)) {
            return;
        }

        Cache::put(
            $this->key($configuration, $method, $url, $options),
            $response->toArray(),
            $this->ttl($configuration, $endpoint)
        );
    }

    public function shouldCache(ConfigurationInterface $configuration, string $method, string $endpoint): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }

        if (! (bool) config('external-apis.cache.enabled', false)) {
            return false;
        }

        if ($configuration->isFake() && ! (bool) config("external-apis.modules.{$configuration->getModuleKey()}.cache_fake", false)) {
            return false;
        }

        return (bool) config("external-apis.modules.{$configuration->getModuleKey()}.endpoints.{$this->endpointKey($endpoint)}.cache.enabled", false);
    }

    private function ttl(ConfigurationInterface $configuration, string $endpoint): int
    {
        return (int) config(
            "external-apis.modules.{$configuration->getModuleKey()}.endpoints.{$this->endpointKey($endpoint)}.cache.ttl",
            config('external-apis.cache.default_ttl', 3600)
        );
    }

    private function key(ConfigurationInterface $configuration, string $method, string $url, array $options): string
    {
        $context = [
            'method' => strtoupper($method),
            'url' => $url,
            'query' => $options['query'] ?? [],
            'auth' => $this->authContext($options),
        ];

        return 'external-api:cache:'.$configuration->getModuleKey().':'.hash('xxh128', json_encode($context, JSON_THROW_ON_ERROR));
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

        return Str::lower(str_replace('/', '.', explode('/', $normalized)[0]));
    }
}
