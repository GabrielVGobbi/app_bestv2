<?php

namespace App\Modules\ExternalApi\Core\Abstracts;

use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;
use App\Modules\ExternalApi\Core\DTOs\BaseResponse;
use App\Modules\ExternalApi\Core\Enums\ExternalApiRequestStatus;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use App\Modules\ExternalApi\Core\Exceptions\HttpException;
use App\Modules\ExternalApi\Core\Exceptions\RateLimitException;
use App\Modules\ExternalApi\Core\Support\ExternalApiRateLimiter;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Core\Support\ExternalApiResponseCache;
use App\Modules\ExternalApi\Core\Support\RetryPolicy;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class AbstractHttpClient implements HttpClientInterface
{
    protected ConfigurationInterface $configuration;

    public function __construct(
        ConfigurationInterface $configuration,
        protected ?ExternalApiRateLimiter $rateLimiter = null,
        protected ?ExternalApiResponseCache $responseCache = null,
        protected ?ExternalApiRequestLogger $requestLogger = null,
        protected ?RetryPolicy $retryPolicy = null,
    ) {
        $this->configuration = $configuration;
        $this->rateLimiter ??= app(ExternalApiRateLimiter::class);
        $this->responseCache ??= app(ExternalApiResponseCache::class);
        $this->requestLogger ??= app(ExternalApiRequestLogger::class);
        $this->retryPolicy ??= app(RetryPolicy::class);
    }

    public function get(string $endpoint, array $params = []): BaseResponse
    {
        return $this->makeRequest('GET', $endpoint, ['query' => $params]);
    }

    public function post(string $endpoint, array $data = []): BaseResponse
    {
        return $this->makeRequest('POST', $endpoint, ['json' => $data]);
    }

    public function put(string $endpoint, array $data = []): BaseResponse
    {
        return $this->makeRequest('PUT', $endpoint, ['json' => $data]);
    }

    public function delete(string $endpoint): BaseResponse
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    protected function makeRequest(string $method, string $endpoint, array $options = []): BaseResponse
    {
        if ($this->configuration->isFake()) {
            throw new ConfigurationException('Fake mode is not supported by this HTTP client.');
        }

        $url = $this->buildUrl($endpoint);
        $headers = $this->getHeaders();
        $optionsWithHeaders = [...$options, 'headers' => $headers];
        $startedAt = microtime(true);
        $retryCount = 0;

        $cached = $this->responseCache->get($this->configuration, $method, $url, $endpoint, $optionsWithHeaders);

        if ($cached !== null) {
            $this->logRequest($method, $url, $endpoint, $startedAt, ExternalApiRequestStatus::CacheHit, $cached->getStatusCode(), true, $retryCount);

            return $cached;
        }

        try {
            $this->rateLimiter->attempt($this->configuration, $method, $endpoint, $optionsWithHeaders);

            $response = Http::withHeaders($headers)
                ->timeout($this->configuration->getTimeout())
                ->connectTimeout($this->configuration->getConnectTimeout())
                ->retry(
                    max(1, $this->configuration->getRetryAttempts()),
                    function (int $attempt, Throwable $throwable) use (&$retryCount): int {
                        $retryCount++;

                        return $this->retryPolicy->delayMilliseconds($attempt, $throwable);
                    },
                    fn (Throwable $throwable): bool => $this->retryPolicy->shouldRetry($throwable, $method),
                    throw: false
                )
                ->send($method, $url, $options);

            $baseResponse = $this->handleResponse($response);
            $this->responseCache->put($this->configuration, $method, $url, $endpoint, $optionsWithHeaders, $baseResponse);
            $this->logRequest($method, $url, $endpoint, $startedAt, ExternalApiRequestStatus::Success, $baseResponse->getStatusCode(), false, $retryCount);

            return $baseResponse;
        } catch (RateLimitException $exception) {
            $this->logRequest($method, $url, $endpoint, $startedAt, ExternalApiRequestStatus::RateLimited, 429, false, $retryCount, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $statusCode = match (true) {
                $exception instanceof RequestException => $exception->response->status(),
                $exception instanceof HttpException => $exception->getCode(),
                default => 0,
            };
            $this->logRequest($method, $url, $endpoint, $startedAt, ExternalApiRequestStatus::Failed, $statusCode, false, $retryCount, $exception);

            throw $exception;
        }
    }

    protected function rawRequest(string $method, string $url, array $options = []): BaseResponse
    {
        if ($this->configuration->isFake()) {
            throw new ConfigurationException('Fake mode is not supported by this HTTP client.');
        }

        $retryCount = 0;

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->configuration->getTimeout())
            ->connectTimeout($this->configuration->getConnectTimeout())
            ->retry(
                max(1, $this->configuration->getRetryAttempts()),
                function (int $attempt, Throwable $throwable) use (&$retryCount): int {
                    $retryCount++;

                    return $this->retryPolicy->delayMilliseconds($attempt, $throwable);
                },
                fn (Throwable $throwable): bool => $this->retryPolicy->shouldRetry($throwable, $method),
                throw: false
            )
            ->send($method, $url, $options);

        return $this->handleResponse($response);
    }

    protected function buildUrl(string $endpoint): string
    {
        return rtrim($this->configuration->getBaseUrl(), '/').'/'.ltrim($endpoint, '/');
    }

    protected function handleResponse(Response $response): BaseResponse
    {
        if ($response->successful()) {
            $data = $response->json();

            return new BaseResponse(
                data: is_array($data) ? $data : [],
                statusCode: $response->status(),
                headers: $response->headers()
            );
        }

        if ($response->status() === 429) {
            throw new RateLimitException('Rate limit exceeded');
        }

        throw new HttpException(
            "External API request failed (HTTP {$response->status()}).",
            $response->status()
        );
    }

    protected function logRequest(
        string $method,
        string $url,
        string $endpoint,
        float $startedAt,
        ExternalApiRequestStatus $status,
        int $statusCode,
        bool $cacheHit,
        int $retryCount,
        ?Throwable $throwable = null,
    ): void {
        $this->requestLogger->record($this->configuration, [
            'method' => strtoupper($method),
            'url' => $url,
            'endpoint' => $endpoint,
            'status' => $status->value,
            'status_code' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'cache_hit' => $cacheHit,
            'retry_count' => $retryCount,
            'error' => $throwable?->getMessage(),
        ]);
    }

    abstract protected function getHeaders(): array;
}
