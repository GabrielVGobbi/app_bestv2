<?php

namespace App\Modules\ExternalApi\Modules\Asaas\Http;

use App\Modules\ExternalApi\Core\Abstracts\AbstractHttpClient;

class AsaasHttpClient extends AbstractHttpClient
{
    protected function getHeaders(): array
    {
        return [
            'access_token' => $this->configuration->getApiKey(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-ExternalApi/1.0',
        ];
    }
}
