<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\TestEnvironment;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
    }

    public function test_name_falls_back_to_app_name(): void
    {
        config(['branding.name' => null, 'app.name' => 'Start kit']);

        $this->assertSame('Start kit', app(BrandingService::class)->name());
    }

    public function test_short_name_is_uppercased_and_cut_to_three_characters(): void
    {
        config(['branding.short_name' => 'start']);

        $this->assertSame('STA', app(BrandingService::class)->shortName());
    }

    public function test_hue_outside_range_or_non_numeric_falls_back_to_default(): void
    {
        $service = app(BrandingService::class);

        config(['branding.hue' => 'teal']);
        $this->assertSame(BrandingService::DEFAULT_HUE, $service->hue());

        config(['branding.hue' => 420]);
        $this->assertSame(BrandingService::DEFAULT_HUE, $service->hue());

        config(['branding.hue' => '30']);
        $this->assertSame(30, $service->hue());
    }

    public function test_missing_logo_file_falls_back_to_kit_mark(): void
    {
        Log::spy();
        config(['branding.logo.light' => 'brand/does-not-exist.svg']);

        $this->assertNull(app(BrandingService::class)->logoPath('light'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_logo_path_escaping_public_is_rejected(): void
    {
        Log::spy();
        config([
            'branding.logo.light' => '../composer.json',
            'branding.logo.dark' => 'brand/..\\..\\composer.json',
        ]);

        $service = app(BrandingService::class);

        $this->assertNull($service->logoPath('light'));
        $this->assertNull($service->logoPath('dark'));
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_existing_logo_file_is_exposed_as_absolute_public_path(): void
    {
        config(['branding.logo.mark' => 'brand/mark.svg']);

        $this->assertSame('/brand/mark.svg', app(BrandingService::class)->logoPath('mark'));
    }

    public function test_error_page_outside_web_routes_receives_branding(): void
    {
        config(['branding.name' => 'Minha Marca']);

        $this->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('error')
                    ->where('status', 404)
                    ->where('branding.name', 'Minha Marca')
                    ->where('branding.logo.mark', null),
            );
    }

    public function test_dashboard_still_renders_when_the_configured_logo_is_missing(): void
    {
        Log::spy();
        config(['branding.logo.light' => 'brand/missing.svg', 'branding.logo.mark' => 'brand/missing-mark.svg']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('branding.logo.light', null)
                    ->where('branding.logo.mark', null),
            );
    }

    public function test_branding_is_shared_with_inertia(): void
    {
        config(['branding.name' => 'Minha Marca', 'branding.hue' => 30]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('branding.name', 'Minha Marca')
                    ->where('branding.hue', 30)
                    ->where('branding.logo.mark', null)
                    ->where('name', 'Minha Marca'),
            );
    }
}
