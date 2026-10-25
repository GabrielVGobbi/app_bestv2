<?php

namespace App\Modules\ExternalApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExternalApi\Core\DTOs\ExternalApiModule;
use App\Modules\ExternalApi\Core\Support\ExternalApiRegistry;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Models\ExternalApiConnection;
use Inertia\Inertia;
use Inertia\Response;

class ExternalApiDiagnosticsController extends Controller
{
    public function __invoke(ExternalApiRegistry $registry, ExternalApiRequestLogger $logger): Response
    {
        abort_unless((bool) config('external-apis.diagnostics.enabled', true), 404);

        $connections = ExternalApiConnection::query()
            ->selectRaw('provider, count(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return Inertia::render('app/external-api/diagnostics', [
            'modules' => collect($registry->modules())
                ->map(fn (ExternalApiModule $module): array => [
                    ...$module->toArray(),
                    'credentials' => $this->credentials($module),
                    'settings' => $this->settings($module),
                    'connections_count' => $connections[$module->key] ?? 0,
                ])
                ->values()
                ->all(),
            'recentRequests' => $logger->recent(),
            'global' => [
                'fake' => $this->boolean(config('external-apis.fake.enabled'), true),
                'cache' => [
                    'enabled' => $this->boolean(config('external-apis.cache.enabled'), false),
                    'default_ttl' => $this->integer(config('external-apis.cache.default_ttl'), 3600),
                    'driver' => $this->configuredName(config('external-apis.cache.driver'), config('cache.stores'), 'array'),
                    'recent_requests_limit' => $this->integer(config('external-apis.cache.recent_requests_limit'), 50),
                ],
                'retry' => [
                    'base_delay_ms' => $this->integer(config('external-apis.retry.base_delay_ms'), 250),
                    'max_delay_ms' => $this->integer(config('external-apis.retry.max_delay_ms'), 5000),
                    'jitter_ms' => $this->integer(config('external-apis.retry.jitter_ms'), 100),
                    'retryable_statuses' => $this->statuses(config('external-apis.retry.retryable_statuses')),
                ],
                'rate_limit' => [
                    'decay_seconds' => $this->integer(config('external-apis.rate_limit.decay_seconds'), 60),
                    'lock_seconds' => $this->integer(config('external-apis.rate_limit.lock_seconds'), 3),
                ],
                'logging' => [
                    'enabled' => $this->boolean(config('external-apis.logging.enabled'), true),
                    'channel' => $this->configuredName(config('external-apis.logging.channel'), config('logging.channels'), 'default'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function credentials(ExternalApiModule $module): array
    {
        return collect(['api_key', 'client_id', 'client_secret', 'redirect_uri'])
            ->filter(fn (string $key): bool => array_key_exists($key, $module->config))
            ->mapWithKeys(fn (string $key): array => [$key => filled($module->config[$key] ?? null)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function settings(ExternalApiModule $module): array
    {
        return [
            'timeout' => $this->integer($module->config['timeout'] ?? null, 30),
            'connect_timeout' => $this->integer($module->config['connect_timeout'] ?? null, 10),
            'retry_attempts' => $this->integer($module->config['retry_attempts'] ?? null, 3),
            'rate_limit' => $this->integer($module->config['rate_limit'] ?? null, 100),
            'endpoints' => $this->endpoints($module->config['endpoints'] ?? null),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function endpoints(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }
        $endpoints = [];
        foreach ($settings as $name => $options) {
            if (! is_string($name) || ! preg_match('/\A[a-zA-Z0-9_.-]{1,64}\z/', $name) || ! is_array($options)) {
                continue;
            }
            $endpoint = [];
            if (isset($options['cache']) && is_array($options['cache'])) {
                $endpoint['cache'] = [
                    'enabled' => $this->boolean($options['cache']['enabled'] ?? null, false),
                    'ttl' => $this->integer($options['cache']['ttl'] ?? null, 3600),
                ];
            }
            if (array_key_exists('rate_limit', $options)) {
                $endpoint['rate_limit'] = $this->integer($options['rate_limit'], 100);
            }
            if ($endpoint !== []) {
                $endpoints[$name] = $endpoint;
            }
        }

        return $endpoints;
    }

    private function integer(mixed $value, int $fallback): int
    {
        if (! is_int($value) && ! is_string($value)) {
            return $fallback;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $integer === false ? $fallback : $integer;
    }

    private function boolean(mixed $value, bool $fallback): bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => $fallback,
        };
    }

    private function configuredName(mixed $value, mixed $names, string $fallback): string
    {
        return is_string($value) && preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $value)
            && is_array($names) && array_key_exists($value, $names)
            ? $value : $fallback;
    }

    /** @return list<int> */
    private function statuses(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $statuses = [];
        foreach ($values as $value) {
            $status = $this->integer($value, 0);
            if ($status >= 100 && $status <= 599) {
                $statuses[] = $status;
            }
        }

        return array_values(array_unique($statuses));
    }
}
