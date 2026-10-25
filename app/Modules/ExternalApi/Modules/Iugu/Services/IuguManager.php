<?php

namespace App\Modules\ExternalApi\Modules\Iugu\Services;

use App\Modules\ExternalApi\Core\Abstracts\AbstractApiService;

class IuguManager extends AbstractApiService
{
    public function getName(): string
    {
        return 'Iugu';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function customers(): CustomerService
    {
        return new CustomerService($this->configuration, $this->httpClient);
    }

    public function payments(): PaymentService
    {
        return new PaymentService($this->configuration, $this->httpClient);
    }
}
