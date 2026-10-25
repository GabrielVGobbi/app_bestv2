<?php

namespace App\Modules\ExternalApi\Core\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Throwable;

class RetryPolicy
{
    /**
     * @return array<int, int>
     */
    public function retryableStatuses(): array
    {
        return array_map('intval', config('external-apis.retry.retryable_statuses', [408, 423, 425, 429, 500, 502, 503, 504]));
    }

    public function shouldRetry(Throwable $throwable, string $method): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }

        if ($throwable instanceof ConnectionException) {
            return true;
        }

        if (! $throwable instanceof RequestException) {
            return false;
        }

        return in_array($throwable->response->status(), $this->retryableStatuses(), true);
    }

    public function delayMilliseconds(int $attempt, Throwable $throwable): int
    {
        $retryAfter = $this->retryAfterMilliseconds($throwable);

        if ($retryAfter !== null) {
            return $retryAfter;
        }

        $base = (int) config('external-apis.retry.base_delay_ms', 250);
        $max = (int) config('external-apis.retry.max_delay_ms', 5000);
        $jitter = (int) config('external-apis.retry.jitter_ms', 100);
        $delay = min($max, $base * (2 ** max(0, $attempt - 1)));

        if ($jitter > 0) {
            $delay += random_int(0, $jitter);
        }

        return $delay;
    }

    private function retryAfterMilliseconds(Throwable $throwable): ?int
    {
        if (! $throwable instanceof RequestException) {
            return null;
        }

        $header = $throwable->response->header('Retry-After');

        if ($header === null || $header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) $header * 1000);
        }

        $retryAt = Carbon::parse($header);

        return max(0, now()->diffInMilliseconds($retryAt, false));
    }
}
