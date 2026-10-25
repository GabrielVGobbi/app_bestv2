<?php

namespace App\Modules\ExternalApi\Core\Traits;

use App\Modules\ExternalApi\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Cache;

trait HandlesRateLimit
{
    protected function checkRateLimit(): void
    {
        $key = $this->getRateLimitKey();
        $limit = $this->configuration->getRateLimitPerMinute();

        Cache::add($key, 0, 60);

        $current = Cache::increment($key);

        if ($current > $limit) {
            throw new RateLimitException("Rate limit of {$limit} requests per minute exceeded");
        }
    }

    protected function recordRequest(): void
    {
        //
    }

    protected function getRateLimitKey(): string
    {
        return 'rate_limit:'.get_class($this).':'.now()->format('Y-m-d-H-i');
    }
}
