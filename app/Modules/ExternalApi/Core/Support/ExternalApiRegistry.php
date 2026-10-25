<?php

namespace App\Modules\ExternalApi\Core\Support;

use App\Modules\ExternalApi\Core\DTOs\ExternalApiModule;
use App\Modules\ExternalApi\Core\Enums\ExternalApiMode;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class ExternalApiRegistry
{
    public function __construct(private Container $container) {}

    /**
     * @return array<string, ExternalApiModule>
     */
    public function modules(): array
    {
        return collect(config('external-apis.modules', []))
            ->mapWithKeys(fn (array $config, string $key): array => [$key => $this->module($key)])
            ->all();
    }

    public function module(string $key): ExternalApiModule
    {
        $config = config("external-apis.modules.{$key}");

        if (! is_array($config)) {
            throw new ConfigurationException("External API module [{$key}] is not configured.");
        }

        $fake = $config['fake'] ?? config('external-apis.fake.enabled', true);

        return new ExternalApiModule(
            key: $key,
            name: Str::headline($key),
            enabled: (bool) ($config['enabled'] ?? true),
            mode: (bool) $fake ? ExternalApiMode::Fake : ExternalApiMode::Real,
            provider: $config['provider'] ?? null,
            manager: $config['manager'] ?? null,
            configuration: $config['configuration'] ?? null,
            httpClient: $config['http_client'] ?? null,
            mockClient: $config['mock_client'] ?? null,
            config: $config,
        );
    }

    public function manager(string $key): object
    {
        $manager = $this->module($key)->manager;

        if (! is_string($manager) || $manager === '') {
            throw new ConfigurationException("External API module [{$key}] does not define a manager.");
        }

        return $this->container->make($manager);
    }

    /**
     * @return array<int, string>
     */
    public function enabledProviders(): array
    {
        return collect($this->modules())
            ->filter(fn (ExternalApiModule $module): bool => $module->enabled && is_string($module->provider))
            ->map(fn (ExternalApiModule $module): string => $module->provider)
            ->values()
            ->all();
    }
}
