<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AppShellStructureTest extends TestCase
{
    public function test_dashboard_shell_matches_the_reference_structure(): void
    {
        $layout = $this->frontendFile('layouts/app/app-dashboard-layout.tsx');
        $sidebar = $this->frontendFile('components/dashboard/dashboard-sidebar.tsx');
        $header = $this->frontendFile('components/dashboard/dashboard-header.tsx');
        $pageHeader = $this->frontendFile('components/dashboard/header.tsx');
        $tableHeader = $this->frontendFile('components/data-table/shell/data-table-header.tsx');

        $this->assertStringContainsString("router.on('navigate'", $layout);
        $this->assertStringContainsString('isMobileSidebarOpen', $layout);
        $this->assertStringContainsString('isCollapsed={isSidebarCollapsed}', $layout);
        $this->assertStringContainsString('mobileOpen={isMobileSidebarOpen}', $layout);
        $this->assertStringContainsString('app-workspace flex h-screen overflow-hidden', $layout);

        $this->assertStringContainsString('mobileOpen?: boolean', $sidebar);
        $this->assertStringContainsString('fixed inset-0 z-40 bg-black/50', $sidebar);
        $this->assertStringContainsString('md:static md:z-auto md:shrink-0 md:translate-x-0', $sidebar);
        $this->assertStringContainsString("isCollapsed ? 'md:w-16' : 'md:w-60'", $sidebar);
        $this->assertStringContainsString('bg-secondary text-foreground', $sidebar);

        $this->assertStringContainsString('onMobileToggle?: () => void', $header);
        $this->assertStringContainsString('aria-label="Abrir menu"', $header);
        $this->assertStringContainsString('sticky top-0 z-30 flex h-header', $header);

        $this->assertStringContainsString('sticky top-0 z-10 flex h-header', $pageHeader);
        $this->assertStringContainsString('border-b border-header-border bg-background/80', $pageHeader);

        $this->assertStringContainsString('bg-table-background', $tableHeader);
        $this->assertStringContainsString('text-table-text', $tableHeader);
    }

    public function test_shared_frontend_surfaces_follow_the_professional_visual_system(): void
    {
        $dashboard = $this->frontendFile('pages/dashboard.tsx');
        $authLayout = $this->frontendFile('layouts/auth/auth-card-layout.tsx');
        $card = $this->frontendFile('components/ui/card.tsx');
        $button = $this->frontendFile('components/ui/button.tsx');
        $logo = $this->frontendFile('components/app-logo.tsx');
        $table = $this->frontendFile('components/data-table/shell/data-table-shell.tsx');

        $this->assertStringContainsString('Central de operações', $dashboard);
        $this->assertStringContainsString('resolveDashboardModules', $dashboard);
        $this->assertStringNotContainsString('PlaceholderPattern', $dashboard);

        $this->assertStringContainsString('auth-workspace', $authLayout);
        $this->assertStringContainsString('Gestão musical, comercial e operacional', $authLayout);

        $this->assertStringContainsString('shadow-[var(--shadow-app-panel)]', $card);
        $this->assertStringContainsString('transition-[background,color,box-shadow,opacity,transform]', $button);
        $this->assertStringContainsString("text ?? 'BestPlay'", $logo);
        $this->assertStringContainsString('rounded-xl border-border/80', $table);
    }

    public function test_app_css_uses_bestplay_css_first_theme_tokens(): void
    {
        $css = $this->stylesheetFile('app.css');

        $this->assertStringContainsString('--color-brand-pink: var(--brand-pink);', $css);
        $this->assertStringContainsString('--color-brand-red: var(--brand-red);', $css);
        $this->assertStringContainsString('--color-brand-surface: var(--brand-surface);', $css);
        $this->assertStringContainsString('--color-brand-focus: var(--brand-focus);', $css);
        $this->assertStringContainsString('--color-app-surface: var(--app-surface);', $css);
        $this->assertStringContainsString('color-scheme: light;', $css);
        $this->assertStringContainsString('color-scheme: dark;', $css);
        $this->assertStringContainsString('@utility app-surface', $css);
        $this->assertStringContainsString('@utility app-glass', $css);
        $this->assertStringContainsString('@utility brand-focus-ring', $css);
        $this->assertStringContainsString('.app-workspace', $css);
        $this->assertStringContainsString('.auth-workspace', $css);
        $this->assertStringContainsString('--shadow-app-panel:', $css);
        $this->assertStringContainsString('font-feature-settings:', $css);
        $this->assertStringNotContainsString('background: #4a90e2;', $css);
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
