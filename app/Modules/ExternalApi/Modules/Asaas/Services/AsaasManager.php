<?php

namespace App\Modules\ExternalApi\Modules\Asaas\Services;

use App\Modules\ExternalApi\Core\Abstracts\AbstractApiService;

class AsaasManager extends AbstractApiService
{
    private ?CustomerService $customerService = null;

    private ?PaymentService $paymentService = null;

    public function getName(): string
    {
        return 'Asaas';
    }

    public function getVersion(): string
    {
        return 'v3';
    }

    public function customers(): CustomerService
    {
        return $this->customerService ??= new CustomerService(
            $this->configuration,
            $this->httpClient
        );
    }

    public function payments(): PaymentService
    {
        return $this->paymentService ??= new PaymentService(
            $this->configuration,
            $this->httpClient
        );
    }
}
