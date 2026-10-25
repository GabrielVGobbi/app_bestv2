# HTTP Client Best Practices

## Always Set Explicit Timeouts

The default timeout is 30 seconds — too long for most API calls. Always set explicit `timeout` and `connectTimeout` to fail fast.

Incorrect:
```php
$response = Http::get('https://api.example.com/users');
```

Correct:
```php
$response = Http::timeout(5)
    ->connectTimeout(3)
    ->get('https://api.example.com/users');
```

For service-specific clients, define timeouts in a macro:

```php
Http::macro('github', function () {
    return Http::baseUrl('https://api.github.com')
        ->timeout(10)
        ->connectTimeout(3)
        ->withToken(config('services.github.token'));
});

$response = Http::github()->get('/repos/laravel/framework');
```

## Use Retry with Backoff for External APIs

Follow [AGENTS.md](../../../../AGENTS.md) and the existing ExternalApi `RetryPolicy`. Automatic retries are limited to GET requests, with a bounded attempt count, backoff, and explicit request/connection timeouts. Pass the actual operation to the policy; connection failures and retryable HTTP errors do not make writes safe to replay.

```php
use App\Modules\ExternalApi\Core\Support\RetryPolicy;
use Illuminate\Http\Client\PendingRequest;

$method = 'GET';
$retryPolicy = app(RetryPolicy::class);
$response = Http::timeout(10)
    ->connectTimeout(3)
    ->retry([100, 500], when: function (Throwable $exception, PendingRequest $request) use ($retryPolicy, $method): bool {
        return $retryPolicy->shouldRetry($exception, $method);
    })
    ->send($method, 'https://api.example.test/users');
```

Mutating requests and OAuth POSTs receive one attempt. If a response is lost, reconcile the operation's result through the provider's supported read/status flow before deciding on another explicit action. Do not infer deduplication from an external reference or assume that a provider's idempotency feature authorizes automatic write retries; the current project contract enables none.

## Handle Errors Explicitly

The HTTP Client does not throw on 4xx/5xx by default. Always check status or use `throw()`.

Incorrect:
```php
$response = Http::get('https://api.example.com/users/1');
$user = $response->json(); // Could be an error body
```

Correct:
```php
$response = Http::timeout(5)
    ->get('https://api.example.com/users/1')
    ->throw();

$user = $response->json();
```

For graceful degradation:

```php
$response = Http::get('https://api.example.com/users/1');

if ($response->successful()) {
    return $response->json();
}

if ($response->notFound()) {
    return null;
}

$response->throw();
```

## Use Request Pooling for Concurrent Requests

When making multiple independent API calls, use `Http::pool()` instead of sequential calls.

Incorrect:
```php
$users = Http::get('https://api.example.com/users')->json();
$posts = Http::get('https://api.example.com/posts')->json();
$comments = Http::get('https://api.example.com/comments')->json();
```

Correct:
```php
use Illuminate\Http\Client\Pool;

$responses = Http::pool(fn (Pool $pool) => [
    $pool->as('users')->get('https://api.example.com/users'),
    $pool->as('posts')->get('https://api.example.com/posts'),
    $pool->as('comments')->get('https://api.example.com/comments'),
]);

$users = $responses['users']->json();
$posts = $responses['posts']->json();
```

## Fake HTTP Calls in Tests

Never make real HTTP requests in tests. Use `Http::fake()` and `preventStrayRequests()`.

Incorrect:
```php
it('syncs user from API', function () {
    $service = new UserSyncService;
    $service->sync(1); // Hits the real API
});
```

Correct:
```php
it('syncs user from API', function () {
    Http::preventStrayRequests();

    Http::fake([
        'api.example.com/users/1' => Http::response([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]),
    ]);

    $service = new UserSyncService;
    $service->sync(1);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.example.com/users/1';
    });
});
```

Test failure scenarios too:

```php
Http::fake([
    'api.example.com/*' => Http::failedConnection(),
]);
```