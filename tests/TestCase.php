<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected ?TestEnvironment $testEnvironment = null;

    public function createApplication(): Application
    {
        TestEnvironment::assertSafeEnvironment();
        $this->testEnvironment = new TestEnvironment;

        try {
            $app = require dirname(__DIR__).'/bootstrap/app.php';
            $this->traitsUsedByTest = class_uses_recursive(static::class);
            $this->testEnvironment->prepare($app);
            $app->afterBootstrapping(LoadConfiguration::class, fn (Application $app) => $this->testEnvironment->configure($app));
            $app->afterBootstrapping(RegisterProviders::class, fn () => $this->testEnvironment->fakeIntegrations());
            $app->make(Kernel::class)->bootstrap();
            TestEnvironment::assertSafeDatabase($app->make('config'));

            return $app;
        } catch (\Throwable $exception) {
            $this->testEnvironment->cleanUp();
            throw $exception;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    protected function isolateProjectFiles(): void
    {
        $this->testEnvironment->isolateProjectFiles($this->app);
        $this->withoutVite();
    }

    protected function setUpTraits(): array
    {
        TestEnvironment::assertSafeDatabase($this->app->make('config'));

        return parent::setUpTraits();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->testEnvironment?->cleanUp();
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
