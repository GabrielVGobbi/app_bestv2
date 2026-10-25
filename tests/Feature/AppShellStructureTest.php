<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AppShellStructureTest extends TestCase
{
    public function test_layout_entrypoints_share_one_application_shell(): void
    {
        foreach (['layouts/app-layout.tsx', 'layouts/app/app-header-layout.tsx', 'layouts/app/app-sidebar-layout.tsx'] as $path) {
            $source = $this->frontendFile($path);
            $this->assertStringContainsString('app-dashboard-layout', $source);
            $this->assertStringNotContainsString('<header', $source);
            $this->assertStringNotContainsString('<aside', $source);
        }

        $layout = $this->frontendFile('layouts/app/app-dashboard-layout.tsx');
        $this->assertStringContainsString('<NavModuleProvider>', $layout);
        $this->assertStringContainsString('bestplay.sidebar.collapsed', $layout);
        $this->assertStringContainsString("router.on('navigate'", $layout);
        $this->assertStringContainsString('setIsMobileSidebarOpen(false)', $layout);
        $this->assertStringContainsString('href="#main-content"', $layout);
        $this->assertStringContainsString('id="main-content"', $layout);
        $this->assertStringContainsString('tabIndex={-1}', $layout);
        $this->assertStringContainsString('<ZipDownloadPanel />', $layout);
    }

    public function test_navigation_has_accessible_desktop_and_mobile_controls(): void
    {
        $sidebar = $this->frontendFile('components/dashboard/dashboard-sidebar.tsx');
        $header = $this->frontendFile('components/dashboard/dashboard-header.tsx');

        $this->assertStringContainsString('aria-label="Módulos"', $sidebar);
        $this->assertStringContainsString('aria-label="Expandir menu"', $sidebar);
        $this->assertStringContainsString('aria-label="Recolher menu"', $sidebar);
        $this->assertStringContainsString('aria-current=', $sidebar);
        $this->assertStringContainsString('<Sheet', $sidebar);
        $this->assertStringContainsString('open={mobileOpen}', $sidebar);
        $this->assertStringContainsString('<SheetTitle', $sidebar);
        $this->assertStringContainsString('<SheetDescription', $sidebar);
        $this->assertStringContainsString('onCloseAutoFocus=', $sidebar);
        $this->assertStringContainsString(".getElementById('mobile-navigation-trigger')", $sidebar);
        $this->assertStringContainsString('id="mobile-navigation-trigger"', $header);
        $this->assertStringContainsString('aria-expanded={mobileOpen}', $header);
        $this->assertStringContainsString('GlobalCommandPalette', $header);
        $this->assertStringContainsString('NotificationBell', $header);
        $this->assertStringNotContainsString('modules.map', $header);
    }

    public function test_dashboard_keeps_deferred_data_and_permission_scoped_sections(): void
    {
        $dashboard = $this->frontendFile('pages/dashboard.tsx');
        $this->assertStringContainsString('Visão geral', $dashboard);
        $this->assertStringContainsString('data="operationalDashboard"', $dashboard);
        $this->assertStringContainsString('DashboardSkeleton', $dashboard);
        foreach (['sales', 'playlists', 'finance'] as $section) {
            $this->assertStringContainsString("data.{$section} && (", $dashboard);
        }
        $this->assertStringContainsString('overdue_receivables', $dashboard);
        $this->assertStringContainsString('overdue_payables', $dashboard);
        $this->assertStringNotContainsString('PlaceholderPattern', $dashboard);
    }

    public function test_styles_separate_tokens_layout_and_utilities_and_support_reduced_motion(): void
    {
        $css = $this->stylesheetFile('app.css');
        foreach (['theme.css', 'workspace.css', 'utilities.css'] as $path) {
            $this->assertStringContainsString("@import './{$path}';", $css);
            $this->assertNotEmpty($this->stylesheetFile($path));
        }

        $theme = $this->stylesheetFile('theme.css');
        foreach (['brand-pink', 'inflow', 'outflow', 'canvas', 'sidebar'] as $token) {
            $this->assertStringContainsString("--color-{$token}: var(--{$token});", $theme);
        }
        $this->assertStringContainsString('.dark', $theme);
        $this->assertStringContainsString('color-scheme: light;', $css);
        $this->assertStringContainsString('color-scheme: dark;', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('&:focus-visible', $this->stylesheetFile('workspace.css'));
    }

    private function frontendFile(string $path): string
    {
        $absolutePath = resource_path("js/{$path}");
        $this->assertFileExists($absolutePath);

        return (string) file_get_contents($absolutePath);
    }

    private function stylesheetFile(string $path): string
    {
        $absolutePath = resource_path("css/{$path}");
        $this->assertFileExists($absolutePath);

        return (string) file_get_contents($absolutePath);
    }
}
