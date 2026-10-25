<?php

namespace App\Modules\ExternalApi\Modules\Iugu\Services;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;

class CustomerService
{
    public function __construct(
        private ConfigurationInterface $configuration,
        private HttpClientInterface $httpClient
    ) {}

    public function list(array $params = []): array
    {
        return $this->httpClient->get('/customers', $params)->getData();
    }
}
