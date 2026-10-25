<?php

namespace App\Modules\ExternalApi\Core\Enums;

enum ExternalApiRequestStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case RateLimited = 'rate_limited';
    case CacheHit = 'cache_hit';
}
