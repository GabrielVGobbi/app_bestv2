<?php

namespace App\Modules\ExternalApi\Modules\Iugu\Configuration;

use App\Modules\ExternalApi\Core\Abstracts\AbstractConfiguration;

class IuguConfiguration extends AbstractConfiguration
{
    public function getModuleKey(): string
    {
        return 'iugu';
    }

    public function getBaseUrl(): string
    {
        return (string) config('external-apis.modules.iugu.base_url', 'https://api.iugu.com/v1');
    }
}
