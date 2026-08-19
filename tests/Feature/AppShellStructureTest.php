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
        $this->assertStringContainsString("window.localStorage.getItem('bestplay.sidebar.collapsed')", $layout);
        $this->assertStringContainsString('app-workspace flex h-screen flex-col overflow-hidden', $layout);

        $this->assertStringContainsString('mobileOpen?: boolean', $sidebar);
        $this->assertStringContainsString('fixed inset-0 z-40 bg-black/50', $sidebar);
        $this->assertStringContainsString('md:static md:z-auto md:shrink-0 md:translate-x-0', $sidebar);
        $this->assertStringContainsString("isCollapsed ? 'md:w-16' : 'md:w-[250px]'", $sidebar);
        $this->assertStringContainsString("event.key === 'Escape'", $sidebar);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden'", $sidebar);

        $this->assertStringContainsString('onMobileToggle?: () => void', $header);
        $this->assertStringContainsString('aria-label="Abrir navegação"', $header);
        $this->assertStringContainsString('GlobalCommandPalette', $header);
        $this->assertStringContainsString('h-[60px]', $header);

        $this->assertStringContainsString('min-h-16', $pageHeader);
        $this->assertStringContainsString('border-b border-border bg-background', $pageHeader);

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

        $this->assertStringContainsString('Visão geral', $dashboard);
        $this->assertStringContainsString('data="operationalDashboard"', $dashboard);
        $this->assertStringContainsString('DashboardSkeleton', $dashboard);
        $this->assertStringContainsString('SummaryGroup', $dashboard);
        $this->assertStringNotContainsString('Módulos disponíveis', $dashboard);
        $this->assertStringNotContainsString('PlaceholderPattern', $dashboard);

        $this->assertStringContainsString('auth-workspace', $authLayout);
        $this->assertStringContainsString('max-w-[25rem]', $authLayout);
        $this->assertStringNotContainsString('Área segura', $authLayout);

        $this->assertStringContainsString('shadow-[var(--shadow-app-panel)]', $card);
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

    public function test_sidebar_uses_an_adaptive_pink_palette_with_readable_content(): void
    {
        $css = $this->stylesheetFile('app.css');
        $sidebar = $this->frontendFile('components/app-sidebar.tsx');
        $footer = $this->frontendFile('components/nav-footer.tsx');

        $this->assertStringContainsString('--sidebar: #c0084d;', $css);
        $this->assertStringContainsString('--sidebar: #3b0b21;', $css);
        $this->assertStringContainsString('--sidebar-foreground: #fff7fb;', $css);
        $this->assertStringContainsString('--sidebar-foreground: #ffe8f1;', $css);
        $this->assertStringContainsString('iconClassName="text-sidebar-foreground"', $sidebar);
        $this->assertStringContainsString('text-sidebar-foreground/75', $footer);
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
