<?php

namespace App\Supports;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Throwable;

class AtomicTwoFactorAuthenticationProvider extends TwoFactorAuthenticationProvider
{
    /**
     * @param  mixed  $secret  Untrusted decrypted value, validated before invoking Fortify.
     * @param  mixed  $code  Untrusted submitted value, validated before invoking Fortify.
     */
    public function verify(mixed $secret, mixed $code): bool
    {
        if (! is_string($secret) || $secret === '' || ! is_string($code) || $code === '') {
            return false;
        }
        if ($this->cache === null) {
            Log::warning('Two-factor verification requires a configured replay cache.');

            return false;
        }
        try {
            $cache = $this->cache;
            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                Log::warning('Two-factor replay cache does not support atomic locks.', ['store' => $store::class]);

                return false;
            }
            $configuredWindow = config('fortify-options.two-factor-authentication.window');
            if (is_int($configuredWindow)) {
                $this->engine->setWindow($configuredWindow);
            }
            $window = $this->engine->getWindow();
            $period = $this->engine->getKeyRegeneration();
            if ($window < 0 || $period < 1) {
                Log::warning('Two-factor replay protection has invalid clock configuration.');

                return false;
            }
            $lease = ($window * 2 + 2) * $period;
            $dataKey = 'fortify.2fa_codes.'.md5($code);

            return $store->lock('fortify.2fa_code_locks.'.md5($code), $lease)
                ->get(function () use ($cache, $dataKey, $secret, $code, $lease): bool {
                    $beforeValue = $cache->get($dataKey);
                    $before = $this->replayTimestamp($beforeValue);
                    if ($beforeValue !== null && $before === null) {
                        Log::warning('Two-factor replay cache contains an invalid timestamp.');

                        return false;
                    }
                    if ($beforeValue === null) {
                        if ($cache->put($dataKey, 0, $lease) !== true || $this->replayTimestamp($cache->get($dataKey)) !== 0) {
                            Log::warning('Two-factor replay initialization was not retained.');

                            return false;
                        }
                        $before = 0;
                    }
                    if (! parent::verify($secret, $code)) {
                        return false;
                    }
                    $after = $this->replayTimestamp($cache->get($dataKey));
                    if ($after === null || $after <= ($before ?? 0)) {
                        Log::warning('Two-factor replay consumption was not retained.');

                        return false;
                    }
                    if ($cache->put($dataKey, $after, $lease) !== true || $this->replayTimestamp($cache->get($dataKey)) !== $after) {
                        Log::warning('Two-factor replay retention could not be renewed.');

                        return false;
                    }

                    return true;
                }) === true;
        } catch (Throwable $exception) {
            Log::warning('Two-factor replay verification failed.', ['exception' => $exception::class]);

            return false;
        }
    }

    private function replayTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (! is_string($value) || strlen($value) > strlen((string) PHP_INT_MAX) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
            return null;
        }
        $timestamp = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $timestamp === false ? null : $timestamp;
    }
}
