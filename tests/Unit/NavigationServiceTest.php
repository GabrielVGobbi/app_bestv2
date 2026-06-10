<?php

namespace Tests\Unit;

use App\Services\NavigationService;
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
}
