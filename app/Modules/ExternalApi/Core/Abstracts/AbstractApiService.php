<?php

namespace App\Modules\ExternalApi\Core\Abstracts;

use App\Modules\ExternalApi\Core\Contracts\ApiServiceInterface;
use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;

abstract class AbstractApiService implements ApiServiceInterface
{
    protected ConfigurationInterface $configuration;

    protected HttpClientInterface $httpClient;

    public function __construct(
        ConfigurationInterface $configuration,
        HttpClientInterface $httpClient
    ) {
        $this->configuration = $configuration;
        $this->httpClient = $httpClient;
        $this->configuration->validate();
    }

    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function isHealthy(): bool
    {
        try {
            $response = $this->httpClient->get('/health');

            return $response->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    abstract public function getName(): string;

    abstract public function getVersion(): string;
}
