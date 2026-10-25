<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Slack\Contracts\SlackServiceInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use RuntimeException;
use Tests\TestCase;

class ExceptionReportingTest extends TestCase
{
    public function test_slack_reporting_failure_is_not_reported_recursively(): void
    {
        $slack = $this->mock(SlackServiceInterface::class);

        $slack->shouldReceive('exception')
            ->once()
            ->andThrow(new RuntimeException('Slack is unavailable.'));

        $this->app->make(ExceptionHandler::class)->report(
            new RuntimeException('Original application exception.'),
        );

        $this->assertTrue(true);
    }
}
