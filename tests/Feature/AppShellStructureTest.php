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
        $pageHeader = $this->frontendFile('components/content-header.tsx');
        $tableHeader = $this->frontendFile('components/data-table/shell/data-table-header.tsx');

        $this->assertStringContainsString("router.on('navigate'", $layout);
        $this->assertStringContainsString('isMobileSidebarOpen', $layout);
        $this->assertStringContainsString('isCollapsed={isSidebarCollapsed}', $layout);
        $this->assertStringContainsString('mobileOpen={isMobileSidebarOpen}', $layout);
        $this->assertStringContainsString("window.localStorage.getItem('bestplay.sidebar.collapsed')", $layout);
        $this->assertStringContainsString('app-workspace flex h-screen flex-col overflow-hidden', $layout);

        $this->assertStringContainsString('mobileOpen?: boolean', $sidebar);
        $this->assertStringContainsString('fixed inset-0 z-40 bg-black/50', $sidebar);
        $this->assertStringContainsString('md:static md:z-auto md:shrink-0 md:translate-x-0', $sidebar);
        $this->assertStringContainsString("aria-label={isCollapsed ? 'Expandir menu' : 'Recolher menu'}", $sidebar);
        $this->assertStringContainsString("event.key === 'Escape'", $sidebar);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden'", $sidebar);

        $this->assertStringContainsString('onMobileToggle?: () => void', $header);
        $this->assertStringContainsString('aria-label="Abrir navegação"', $header);
        $this->assertStringContainsString('GlobalCommandPalette', $header);
        $this->assertStringContainsString('bg-header-background', $header);

        $this->assertStringContainsString('min-h-16', $pageHeader);
        $this->assertStringContainsString('border-b border-border bg-background', $pageHeader);

        $this->assertStringContainsString('bg-data-table-head', $tableHeader);
        $this->assertStringContainsString('text-sidebar-foreground', $tableHeader);
    }

    public function test_shared_frontend_surfaces_follow_the_professional_visual_system(): void
    {
        $dashboard = $this->frontendFile('pages/dashboard.tsx');
        $authLayout = $this->frontendFile('layouts/auth/auth-card-layout.tsx');
        $card = $this->frontendFile('components/ui/card.tsx');
        $button = $this->frontendFile('components/ui/button.tsx');
        $logo = $this->frontendFile('components/app-logo.tsx');
        $table = $this->frontendFile('components/data-table/shell/data-table-shell.tsx');

        $this->assertStringContainsString('Visão geral', $dashboard);
        $this->assertStringContainsString('data="operationalDashboard"', $dashboard);
        $this->assertStringContainsString('text-destructive', $dashboard);
        $this->assertStringContainsString('text-inflow', $dashboard);
        $this->assertStringContainsString('DashboardSkeleton', $dashboard);
        $this->assertStringContainsString('SummaryGroup', $dashboard);
        $this->assertStringContainsString("from '@/components/ui/card'", $dashboard);
        $this->assertStringNotContainsString('Módulos disponíveis', $dashboard);
        $this->assertStringNotContainsString('PlaceholderPattern', $dashboard);

        $this->assertStringContainsString('auth-workspace', $authLayout);
        $this->assertStringContainsString('max-w-[25rem]', $authLayout);
        $this->assertStringNotContainsString('Área segura', $authLayout);

        $this->assertStringContainsString('border-app-line', $card);
        $this->assertStringContainsString('transition-[background,color,box-shadow,opacity,transform]', $button);
        $this->assertStringContainsString("text ?? 'BestPlay'", $logo);
        $this->assertStringContainsString('border-app-line', $table);
    }

    public function test_app_css_uses_bestplay_css_first_theme_tokens(): void
    {
        $css = $this->stylesheetFile('app.css');

        $this->assertStringContainsString('--color-brand-pink: var(--brand-pink);', $css);
        $this->assertStringContainsString('--color-brand-red: var(--brand-red);', $css);
        $this->assertStringContainsString('--color-brand-surface: var(--brand-surface);', $css);
        $this->assertStringContainsString('--color-brand-focus: var(--brand-focus);', $css);
        $this->assertStringContainsString('--color-app-surface: var(--app-surface);', $css);
        $this->assertStringContainsString('--color-inflow: var(--inflow);', $css);
        $this->assertStringContainsString('--color-outflow: var(--outflow);', $css);
        $this->assertStringContainsString('--color-info: var(--info);', $css);
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

    public function test_sidebar_uses_ink_chrome_with_pink_as_accent_only(): void
    {
        $css = $this->stylesheetFile('app.css');
        $sidebar = $this->frontendFile('components/app-sidebar.tsx');
        $footer = $this->frontendFile('components/nav-footer.tsx');
        $header = $this->frontendFile('components/dashboard/dashboard-header.tsx');

        $this->assertStringContainsString('--sidebar: #171717;', $css);
        $this->assertStringContainsString('--sidebar: #202020;', $css);
        $this->assertStringContainsString('--sidebar-accent: #52283a;', $css);
        $this->assertStringContainsString('--sidebar-accent-foreground: #ffe4ee;', $css);
        $this->assertStringContainsString('--sidebar-muted: #a1a1aa;', $css);
        $this->assertStringContainsString('--canvas: #f4f4f5;', $css);
        $this->assertStringContainsString('--canvas: #101010;', $css);
        $this->assertStringContainsString('--header-background: #171717;', $css);
        $this->assertStringContainsString('--primary: #eb0459;', $css);
        $this->assertStringContainsString('--inflow: #008f5f;', $css);
        $this->assertStringContainsString('--outflow: #e60023;', $css);
        $this->assertStringContainsString('--info: #3654d4;', $css);
        $this->assertStringContainsString('--color-inflow: var(--inflow);', $css);
        $this->assertStringContainsString('--color-canvas: var(--canvas);', $css);
        $this->assertStringNotContainsString('--sidebar: #c0084d;', $css);
        $this->assertStringNotContainsString('--sidebar-accent: #fff0f6;', $css);
        $this->assertStringContainsString('iconClassName="text-sidebar-foreground"', $sidebar);
        $this->assertStringContainsString('text-sidebar-foreground/75', $footer);
        $this->assertStringContainsString('bg-header-background', $header);
        $this->assertStringNotContainsString('before:w-[3px]', $this->frontendFile('components/dashboard/dashboard-sidebar.tsx'));
        $this->assertStringNotContainsString('before:w-[3px]', $this->frontendFile('components/nav-module-items.tsx'));
        $this->assertStringNotContainsString('before:w-[3px]', $this->frontendFile('components/nav-module-switcher.tsx'));
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
