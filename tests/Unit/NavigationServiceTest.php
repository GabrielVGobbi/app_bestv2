<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NavigationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(NavigationService::class)->flush();
    }

    public function test_modules_menu_position_can_be_header(): void
    {
        config()->set('navigation.modules_menu.position', 'header');

        $this->assertSame('header', app(NavigationService::class)->modulesMenuPosition());
    }

    public function test_shared_navigation_resolves_each_route_only_once_in_local_requests(): void
    {
        config()->set('navigation.global', [
            ['label' => 'Dashboard', 'href' => ['route' => 'app.dashboard']],
        ]);
        config()->set('navigation.modules', []);
        config()->set('navigation.footer', []);
        $resolutions = 0;
        URL::shouldReceive('route')->with('app.dashboard', [], false)
            ->andReturnUsing(function () use (&$resolutions): string {
                $resolutions++;

                return '/dashboard';
            });

        $this->app->detectEnvironment(fn (): string => 'local');
        try {
            $props = (new HandleInertiaRequests)->share(Request::create('/dashboard'));
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertSame('/dashboard', $props['nav']['global'][0]['href']);
        $this->assertSame([], $props['nav']['modules']);
        $this->assertSame([], $props['nav']['footer']);
        $this->assertSame(1, $resolutions);
    }

    public function test_modules_menu_position_falls_back_to_sidebar(): void
    {
        config()->set('navigation.modules_menu.position', 'invalid');

        $this->assertSame('sidebar', app(NavigationService::class)->modulesMenuPosition());
    }

    public function test_module_default_href_is_resolved(): void
    {
        config()->set('navigation.modules', [
            [
                'slug' => 'gestao',
                'label' => 'Gestão',
                'default' => false,
                'enabled' => true,
                'route_prefixes' => ['/users'],
                'default_href' => '/users',
                'items' => [],
            ],
        ]);

        $modules = app(NavigationService::class)->modules();

        $this->assertSame('/users', $modules[0]['defaultHref']);
    }

    public function test_section_items_are_preserved_for_frontend_grouping(): void
    {
        config()->set('navigation.global', [
            [
                'slug' => 'acl_section',
                'label' => 'ACL',
                'type' => 'section',
                'order' => 1,
                'enabled' => true,
                'visible' => true,
                'items' => [
                    [
                        'slug' => 'users',
                        'label' => 'Users',
                        'type' => 'link',
                        'href' => '/users',
                        'order' => 1,
                        'enabled' => true,
                        'visible' => true,
                    ],
                ],
            ],
        ]);

        $items = app(NavigationService::class)->global();

        $this->assertSame('section', $items[0]['type']);
        $this->assertSame('ACL', $items[0]['title']);
        $this->assertArrayNotHasKey('href', $items[0]);
        $this->assertSame('link', $items[0]['items'][0]['type']);
        $this->assertSame('/users', $items[0]['items'][0]['href']);
    }

    public function test_product_navigation_labels_are_in_portuguese(): void
    {
        $labels = [];
        $navigation = require base_path('config/navigation.php');
        array_walk_recursive(
            $navigation,
            function (mixed $value, string|int $key) use (&$labels): void {
                if ($key === 'label') {
                    $labels[] = $value;
                }
            },
        );

        $this->assertSame([], array_values(array_intersect(
            ['Activity Log', 'Diagnostico', 'Repository', 'Documentation', 'External APIs', 'Dev Tools'],
            $labels,
        )));
    }
}
