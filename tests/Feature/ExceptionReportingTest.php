<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Slack\Contracts\SlackServiceInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Defer\DeferredCallback;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use RuntimeException;
use Tests\TestCase;

class ExceptionReportingTest extends TestCase
{
    public function test_slack_reporting_failure_is_not_reported_recursively(): void
    {
        $this->withoutDefer();
        $slack = $this->mock(SlackServiceInterface::class);

        $slack->shouldReceive('exception')
            ->once()
            ->andThrow(new RuntimeException('Slack is unavailable.'));

        $this->app->make(ExceptionHandler::class)->report(
            new RuntimeException('Original application exception.'),
        );

        $this->assertTrue(true);
    }

    public function test_slack_delivery_is_deferred_and_runs_even_when_the_request_fails(): void
    {
        $delivered = 0;
        $this->mock(SlackServiceInterface::class)->shouldReceive('exception')
            ->andReturnUsing(function () use (&$delivered): bool {
                $delivered++;

                return true;
            });
        $deferred = $this->app->make(DeferredCallbackCollection::class);

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('Original application exception.'));

        $this->assertSame(0, $delivered, 'Reporting must not wait for the Slack webhook.');
        $this->assertCount(1, $deferred);
        $deferred->invokeWhen(fn (DeferredCallback $callback): bool => $callback->always);
        $this->assertSame(1, $delivered, 'The alert must also run after a 5xx response.');
    }
}
