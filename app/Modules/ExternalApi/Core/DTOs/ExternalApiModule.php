<?php

namespace App\Modules\ExternalApi\Core\DTOs;

use App\Modules\ExternalApi\Core\Enums\ExternalApiMode;

class ExternalApiModule
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly ExternalApiMode $mode,
        public readonly ?string $provider,
        public readonly ?string $manager,
        public readonly ?string $configuration,
        public readonly ?string $httpClient,
        public readonly ?string $mockClient,
        public readonly array $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'mode' => $this->mode->value,
            'provider' => $this->provider,
            'manager' => $this->manager,
            'configuration' => $this->configuration,
            'http_client' => $this->httpClient,
            'mock_client' => $this->mockClient,
        ];
    }
}
