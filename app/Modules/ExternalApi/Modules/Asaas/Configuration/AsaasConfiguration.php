<?php

namespace App\Modules\ExternalApi\Modules\Asaas\Configuration;

use App\Modules\ExternalApi\Core\Abstracts\AbstractConfiguration;

class AsaasConfiguration extends AbstractConfiguration
{
    public function getModuleKey(): string
    {
        return 'asaas';
    }

    public function getBaseUrl(): string
    {
        return (string) config('external-apis.modules.asaas.base_url', 'https://api.asaas.com/v3');
    }
}
