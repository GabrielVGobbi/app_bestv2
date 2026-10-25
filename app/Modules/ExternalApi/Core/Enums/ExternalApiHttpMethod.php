<?php

namespace App\Modules\ExternalApi\Core\Enums;

enum ExternalApiHttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';
}
