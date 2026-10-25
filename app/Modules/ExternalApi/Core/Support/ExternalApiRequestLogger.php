<?php

namespace App\Modules\ExternalApi\Core\Support;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Enums\ExternalApiRequestStatus;
use App\Supports\SensitiveDataRedactor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalApiRequestLogger
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    /** @param array<string, mixed> $context */
    public function record(ConfigurationInterface $configuration, array $context): void
    {
        if (! (bool) config('external-apis.logging.enabled', true)) {
            return;
        }
        $entry = $this->project([
            ...$context,
            'id' => (string) Str::uuid(),
            'module' => $configuration->getModuleKey(),
            'mode' => $configuration->isFake() ? 'fake' : 'real',
            'user_id' => Auth::id(),
            'recorded_at' => now()->toIso8601String(),
        ]);
        $this->writeLog($entry);
        $this->remember($entry);
    }

    /** @return list<array<string, mixed>> */
    public function recent(): array
    {
        $cached = Cache::get($this->recentKey(), []);
        if (! is_array($cached)) {
            return [];
        }
        $items = [];
        foreach (array_slice($cached, 0, $this->limit()) as $entry) {
            if (is_array($entry)) {
                $items[] = $this->project($entry);
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $entry */
    private function writeLog(array $entry): void
    {
        $level = ($entry['status'] ?? null) === ExternalApiRequestStatus::Failed->value ? 'warning' : 'info';
        $channel = config('external-apis.logging.channel');
        $logger = is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::getFacadeRoot();
        $logger->{$level}('External API request', $entry);
    }

    /** @param array<string, mixed> $entry */
    private function remember(array $entry): void
    {
        $items = $this->recent();
        array_unshift($items, $entry);
        Cache::put($this->recentKey(), array_slice($items, 0, $this->limit()), now()->addDay());
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function project(array $entry): array
    {
        $projected = [];
        foreach (['id', 'module', 'mode', 'recorded_at', 'method', 'status'] as $key) {
            if (isset($entry[$key]) && is_string($entry[$key])) {
                $projected[$key] = $this->redactor->text($entry[$key]);
            }
        }
        foreach (['url', 'endpoint'] as $key) {
            if (isset($entry[$key]) && is_string($entry[$key])) {
                $projected[$key] = $this->redactor->url($entry[$key]);
            }
        }
        foreach (['user_id', 'status_code', 'duration_ms', 'retry_count'] as $key) {
            if (array_key_exists($key, $entry) && (is_int($entry[$key]) || ($key === 'user_id' && $entry[$key] === null))) {
                $projected[$key] = $entry[$key];
            }
        }
        if (isset($entry['cache_hit']) && is_bool($entry['cache_hit'])) {
            $projected['cache_hit'] = $entry['cache_hit'];
        }
        if (array_key_exists('error', $entry)) {
            $projected['error'] = $entry['error'] === null ? null : 'External request failed.';
        }

        return $projected;
    }

    private function limit(): int
    {
        return max(0, min(100, (int) config('external-apis.cache.recent_requests_limit', 50)));
    }

    private function recentKey(): string
    {
        return 'external-api:diagnostics:recent-requests';
    }
}
