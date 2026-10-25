<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('app.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('app.dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_shares_configured_layout_style(): void
    {
        config(['layout.style' => 'header-logo']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard')
                ->where('layout.style', 'header-logo'),
        );
    }

    public function test_config_derived_props_are_sent_once_and_skipped_when_the_client_has_them(): void
    {
        $user = User::factory()->create();
        $onceProps = ['name', 'branding', 'layout'];

        $first = $this->actingAs($user)->get(route('app.dashboard'))->assertOk();
        $first->assertInertia(fn (Assert $page) => $page->has('nav.modules')->has('branding.hue')->has('auth.user'));
        $this->assertEqualsCanonicalizing($onceProps, array_keys($first->viewData('page')['onceProps']));

        $visit = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $first->viewData('page')['version'],
            'X-Inertia-Except-Once-Props' => implode(',', $onceProps),
        ])->get(route('app.dashboard'))->assertOk();

        $props = $visit->json('props');
        foreach ($onceProps as $prop) {
            $this->assertArrayNotHasKey($prop, $props);
        }
        $this->assertSame($user->id, $props['auth']['user']['id']);
        // Role-filtered navigation is resent on every visit so it follows the current user.
        $this->assertArrayHasKey('nav', $props);
    }
}
