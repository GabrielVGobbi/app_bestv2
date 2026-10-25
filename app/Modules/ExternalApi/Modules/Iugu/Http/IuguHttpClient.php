<?php

namespace App\Modules\ExternalApi\Modules\Iugu\Http;

use App\Modules\ExternalApi\Core\Abstracts\AbstractHttpClient;

class IuguHttpClient extends AbstractHttpClient
{
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($this->configuration->getApiKey().':'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-ExternalApi/1.0',
        ];
    }
}
