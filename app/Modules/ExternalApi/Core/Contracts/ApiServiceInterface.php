<?php

namespace App\Modules\ExternalApi\Core\Contracts;

interface ApiServiceInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function isHealthy(): bool;

    public function getConfiguration(): ConfigurationInterface;

    public function getHttpClient(): HttpClientInterface;
}
