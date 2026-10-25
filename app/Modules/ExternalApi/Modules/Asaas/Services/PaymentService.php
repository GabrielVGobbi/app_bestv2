<?php

namespace App\Modules\ExternalApi\Modules\Asaas\Services;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;

class PaymentService
{
    private ConfigurationInterface $configuration;

    private HttpClientInterface $httpClient;

    public function __construct(
        ConfigurationInterface $configuration,
        HttpClientInterface $httpClient
    ) {
        $this->configuration = $configuration;
        $this->httpClient = $httpClient;
    }

    public function create(array $data): array
    {
        $response = $this->httpClient->post('/payments', $data);

        return $response->getData();
    }

    public function find(string $paymentId): array
    {
        $response = $this->httpClient->get("/payments/{$paymentId}");

        return $response->getData();
    }

    public function list(array $params = []): array
    {
        $response = $this->httpClient->get('/payments', $params);

        return $response->getData();
    }
}
