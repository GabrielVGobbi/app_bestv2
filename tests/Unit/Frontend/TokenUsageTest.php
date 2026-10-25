<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenUsageTest extends TestCase
{
    private const FORBIDDEN = '~\b(?:bg|text|border|ring|from|to|via|fill|stroke|shadow|divide|outline|placeholder)-(?:slate|zinc|gray|neutral|stone|cyan|emerald|amber|sky|blue|teal|red|green|orange|rose|indigo|violet|purple|yellow|lime|pink|fuchsia)-\d{2,3}\b|text-\[10px\]|\btext-white\b|primary-dark|primary-light|runey|Runey|Poppins|Best Play|AppLogoIcon|molecule~';

    /** Arquivos já migrados. Cada task do plano acrescenta os seus. */
    private const MIGRATED = [
        'resources/css/app.css',
        'resources/views/app.blade.php',
        'resources/js/components/app-logo.tsx',
        'resources/js/components/brand/brand-mark.tsx',
        'resources/js/components/brand/brand-wordmark.tsx',
        'resources/js/layouts/app/app-dashboard-layout.tsx',
        'resources/js/layouts/auth/auth-card-layout.tsx',
        'resources/js/layouts/auth/auth-simple-layout.tsx',
        'resources/js/layouts/auth/auth-split-layout.tsx',
        'resources/js/pages/error.tsx',
        'resources/js/components/ui/button.tsx',
        'resources/js/components/ui/badge.tsx',
        'resources/js/components/ui/card.tsx',
        'resources/js/components/ui/input.tsx',
        'resources/js/components/ui/textarea.tsx',
        'resources/js/components/ui/alert.tsx',
        'resources/js/components/ui/sonner.tsx',
        'resources/js/components/page-header.tsx',
        'resources/js/components/empty-state.tsx',
        'resources/js/components/data-table/core/data-table.tsx',
        'resources/js/components/dashboard/dashboard-header.tsx',
        'resources/js/components/dashboard/dashboard-sidebar.tsx',
        'resources/js/components/dashboard/header.tsx',
        'resources/js/components/nav-module-switcher.tsx',
        'resources/js/components/user-menu-content.tsx',
        'resources/js/components/theme-toggle.tsx',
        'resources/js/components/notification-bell.tsx',
        'resources/js/components/breadcrumbs.tsx',
        'resources/js/components/nav-footer.tsx',
        'resources/js/pages/dashboard.tsx',
        'resources/js/components/data-table/shell/data-table-header.tsx',
        'resources/js/components/data-table/shell/data-table-body.tsx',
        'resources/js/components/data-table/shell/data-table-toolbar.tsx',
        'resources/js/components/data-table/shell/data-table-shell.tsx',
        'resources/js/components/data-table/shell/data-table-export-status.tsx',
        'resources/js/components/data-table/shell/data-table-bulk-actions-bar.tsx',
        'resources/js/pages/auth/login.tsx',
        'resources/js/pages/auth/register.tsx',
        'resources/js/pages/auth/forgot-password.tsx',
        'resources/js/pages/auth/reset-password.tsx',
        'resources/js/pages/auth/confirm-password.tsx',
        'resources/js/pages/auth/verify-email.tsx',
        'resources/js/pages/auth/two-factor-challenge.tsx',
        'resources/js/pages/auth/force-password-change.tsx',
        'resources/js/components/text-link.tsx',
        'resources/js/components/input-error.tsx',
        'resources/js/components/password-input.tsx',
        'resources/js/layouts/settings/layout.tsx',
        'resources/js/pages/settings/profile.tsx',
        'resources/js/pages/settings/security.tsx',
        'resources/js/pages/settings/appearance.tsx',
        'resources/js/components/appearance-tabs.tsx',
        'resources/js/components/delete-user.tsx',
        'resources/js/components/heading.tsx',
        'resources/js/components/two-factor-setup-modal.tsx',
        'resources/js/components/two-factor-recovery-codes.tsx',
        'resources/js/pages/app/users/index.tsx',
        'resources/js/pages/app/users/create.tsx',
        'resources/js/pages/app/users/edit.tsx',
        'resources/js/pages/app/users/show.tsx',
        'resources/js/pages/app/users/roles.tsx',
        'resources/js/pages/app/users/permissions.tsx',
        'resources/js/pages/app/users/partials/user-form-fields.tsx',
        'resources/js/pages/app/users/components/user-sub-nav.tsx',
        'resources/js/components/temporary-password-dialog.tsx',
        'resources/js/components/delete-confirm-modal.tsx',
        'resources/js/pages/app/roles/index.tsx',
        'resources/js/pages/app/roles/edit.tsx',
        'resources/js/pages/app/roles/show.tsx',
        'resources/js/pages/app/roles/permissions.tsx',
        'resources/js/pages/app/roles/components/role-sub-nav.tsx',
        'resources/js/pages/app/permissions/index.tsx',
        'resources/js/pages/app/permissions/edit.tsx',
        'resources/js/pages/app/permissions/show.tsx',
        'resources/js/pages/app/clients/index.tsx',
        'resources/js/pages/app/clients/create.tsx',
        'resources/js/pages/app/clients/edit.tsx',
        'resources/js/pages/app/clients/show.tsx',
        'resources/js/pages/app/clients/components/client-profile-header.tsx',
        'resources/js/pages/app/clients/components/client-profile-sidebar.tsx',
        'resources/js/pages/app/clients/components/client-notes-dialog.tsx',
        'resources/js/components/global-command-palette.tsx',
        'resources/js/components/money-amount.tsx',
        'resources/js/components/direction-badge.tsx',
        'resources/js/components/ui/panel-table.tsx',
        'resources/js/pages/app/financial-entries/index.tsx',
        'resources/js/pages/app/financial-entries/show.tsx',
        'resources/js/pages/app/financial-entries/config.tsx',
        'resources/js/pages/app/financial-entries/components/amount-adjustments-dialog.tsx',
        'resources/js/pages/app/financial-entries/components/entry-cancel-dialog.tsx',
        'resources/js/pages/app/financial-reports/index.tsx',
        'resources/js/pages/app/playlists/index.tsx',
        'resources/js/pages/app/playlists/create.tsx',
        'resources/js/pages/app/playlists/components/playlist-row-actions.tsx',
        'resources/js/pages/app/playlists/components/spotify-playlist-importer.tsx',
        'resources/js/pages/app/sales/show.tsx',
        'resources/js/pages/app/sales/partials/playlist-item-dialog.tsx',
        'resources/js/pages/app/sales/components/spotify-presence-control.tsx',
        'resources/js/pages/app/clients/partials/client-form-fields.tsx',
        'resources/js/pages/app/clients/partials/client-delete-dialog.tsx',
        'resources/js/pages/notifications/index.tsx',
        'resources/js/components/notification-item.tsx',
        'resources/js/pages/app/exports/index.tsx',
        'resources/js/pages/app/activity-log/index.tsx',
        'resources/js/pages/app/email-logs/index.tsx',
        'resources/js/pages/admin/dev/design-system.tsx',
        'resources/js/pages/admin/dev/tools.tsx',
        'resources/js/pages/admin/dev/crud-generator.tsx',
        'resources/js/pages/admin/dev/seeder-generator.tsx',
        'resources/js/pages/admin/dev/components/index.tsx',
        'resources/js/pages/admin/dev/components/email.tsx',
        'resources/js/pages/admin/dev/components/activity-actions.tsx',
        'resources/js/pages/admin/dev/components/activity-timeline.tsx',
        'resources/js/pages/admin/dev/components/toasts.tsx',
        'resources/js/pages/admin/dev/components/notifications.tsx',
        'resources/js/pages/admin/dev/components/rich-text-editor.tsx',
        'resources/js/pages/admin/dev/components/file-attachments.tsx',
        'resources/js/pages/admin/dev/components/form-playground.tsx',
        'resources/js/pages/admin/dev/components/async-select.tsx',
        'resources/js/components/async-select/async-select.tsx',
        'resources/js/components/rich-text-editor/rich-text-editor.tsx',
        'resources/js/modules/timeline/components/activity-timeline.tsx',
        'resources/js/modules/timeline/components/comment-actions-menu.tsx',
        'resources/js/modules/timeline/components/comment-composer.tsx',
        'resources/js/modules/timeline/components/timeline-activity-item.tsx',
        'resources/js/modules/timeline/components/timeline-comment-attachments.tsx',
        'resources/js/modules/timeline/components/timeline-comment-item.tsx',
        'resources/js/modules/timeline/components/timeline-day-group.tsx',
        'resources/js/modules/timeline/components/timeline-empty-state.tsx',
        'resources/js/modules/timeline/components/timeline-item.tsx',
        'resources/js/modules/timeline/components/timeline-skeleton.tsx',
        'resources/js/modules/uploads/components/bulk-action-bar.tsx',
        'resources/js/modules/uploads/components/drop-overlay.tsx',
        'resources/js/modules/uploads/components/file-attachments.tsx',
        'resources/js/modules/uploads/components/tag-combobox.tsx',
        'resources/js/modules/uploads/components/upload-dropzone.tsx',
        'resources/js/modules/uploads/components/upload-item.tsx',
        'resources/js/modules/uploads/components/upload-queue-item.tsx',
        'resources/js/modules/uploads/components/upload-quick-preview.tsx',
        'resources/js/modules/uploads/components/zip-download-panel.tsx',
        'resources/js/components/database-export/database-export-dialog.tsx',
        'resources/js/components/database-export/database-export-status.tsx',
        'resources/js/pages/admin/backup/index.tsx',
        'resources/js/pages/admin/slack/index.tsx',
        'resources/js/pages/app/external-api/diagnostics.tsx',
        'resources/js/pages/app/external-api/spotify/artists.tsx',
        'resources/js/pages/app/external-api/spotify/playlists.tsx',
        'resources/js/pages/welcome.tsx',
        'resources/js/components/data-table/shell/data-table-actions-menu.tsx',
        'resources/js/components/data-table/shell/data-table-empty-state.tsx',
        'resources/js/components/data-table/shell/data-table-export-dialog.tsx',
        'resources/js/components/data-table/shell/data-table-pagination.tsx',
        'resources/js/components/data-table/shell/data-table-result-summary.tsx',
        'resources/js/components/alert-error.tsx',
        'resources/js/components/app-content.tsx',
        'resources/js/components/app-header.tsx',
        'resources/js/components/app-shell.tsx',
        'resources/js/components/app-sidebar-header.tsx',
        'resources/js/components/app-sidebar.tsx',
        'resources/js/components/delete-button.tsx',
        'resources/js/components/nav-main.tsx',
        'resources/js/components/nav-user.tsx',
        'resources/js/components/user-info.tsx',
        'resources/js/layouts/app-dashboard-layout.tsx',
        'resources/js/layouts/app-layout.tsx',
        'resources/js/layouts/app/app-header-layout.tsx',
        'resources/js/layouts/app/app-sidebar-layout.tsx',
        'resources/js/layouts/auth-layout.tsx',
        'resources/js/components/data-table/shell/advanced-filter-panel.tsx',
        'resources/js/components/data-table/shell/filter-date-section.tsx',
        'resources/js/components/data-table/shell/filter-category-content.tsx',
        'resources/js/components/data-table/shell/quick-filter-select.tsx',
        'resources/js/components/data-table/shell/data-table-truncated-cell.tsx',
        'resources/js/pages/admin/dev/components/inline-cell-edit.tsx',
        'resources/js/components/ui/quick-filter-pill-classes.ts',
    ];

    /** Telas com cabeçalho único: arquivo => título esperado. */
    private const PAGE_HEADERS = [
        'resources/js/pages/app/users/index.tsx' => 'Usuários',
        'resources/js/pages/app/users/create.tsx' => 'Novo usuário',
        'resources/js/pages/app/roles/index.tsx' => 'Papéis',
        'resources/js/pages/app/permissions/index.tsx' => 'Permissões',
        'resources/js/pages/app/clients/index.tsx' => 'Clientes',
        'resources/js/pages/notifications/index.tsx' => 'Notificações',
        'resources/js/pages/app/exports/index.tsx' => 'Exportações',
        'resources/js/pages/app/activity-log/index.tsx' => 'Registro de atividades',
        'resources/js/pages/app/email-logs/index.tsx' => 'E-mails',
        'resources/js/pages/admin/dev/tools.tsx' => 'Ferramentas de desenvolvimento',
        'resources/js/pages/admin/backup/index.tsx' => 'Backups e dumps',
        'resources/js/pages/admin/slack/index.tsx' => 'Integração com o Slack',
        'resources/js/pages/app/external-api/diagnostics.tsx' => 'Diagnóstico das APIs externas',
        'resources/js/pages/app/external-api/spotify/artists.tsx' => 'Artistas do Spotify',
        'resources/js/pages/app/external-api/spotify/playlists.tsx' => 'Playlists do Spotify',
    ];

    /** Rótulos em inglês que não podem sobrar nas telas migradas. */
    private const ENGLISH_COPY = '~>\s*(?:Users|New User|Create user|Creating\.\.\.|Cancel|Save|Delete|Edit|View|User Information|Email address|Full name|Log out|Settings)\s*<|[\'"](?:Users|New User|Create user|Creating\.\.\.|Manage user accounts|View|Edit|Delete|Cancel|Email address|Full name|First access with temporary password)[\'"]~';

    private const REQUIRED_TOKENS = [
        '--brand-hue', '--primary-hover', '--primary-soft', '--primary-soft-foreground',
        '--success-soft', '--success-soft-foreground', '--warning-soft', '--warning-soft-foreground',
        '--destructive-soft', '--destructive-soft-foreground', '--info', '--info-foreground',
        '--info-soft', '--info-soft-foreground', '--sidebar-muted-foreground', '--separator',
        '--text-2xs',
    ];

    private const REMOVED_TOKENS = [
        '--brand-teal', '--brand-blue', '--color-runey-orange', '--table-background', '--table-text', '--primary-light',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3).'/';
    }

    public function test_app_css_defines_the_semantic_tokens(): void
    {
        $css = file_get_contents(self::root().'resources/css/app.css');

        foreach (self::REQUIRED_TOKENS as $token) {
            $this->assertStringContainsString($token.':', $css, "Token {$token} ausente em app.css");
        }

        foreach (self::REMOVED_TOKENS as $token) {
            $this->assertStringNotContainsString($token, $css, "Token removido {$token} ainda presente em app.css");
        }

        $this->assertMatchesRegularExpression('~--primary:\s*oklch\(0\.48 0\.11 var\(--brand-hue\)\)~', $css);
    }

    public function test_foreign_brand_assets_are_gone(): void
    {
        foreach ([
            'resources/js/components/app-logo-icon.tsx',
            'resources/js/components/layout/molecule-background.tsx',
            'public/src/imagens/logo-icon-dark.svg',
            'public/src/imagens/logo-icon-white.svg',
            'public/src/assets/imagens/molecule',
        ] as $path) {
            $this->assertFileDoesNotExist(self::root().$path);
        }
    }

    public function test_select_is_the_fluid_select_with_the_field_border(): void
    {
        $this->assertFileDoesNotExist(self::root().'resources/js/components/ui/select.tsx', 'Um Select só: o do Fluid');
        $select = file_get_contents(self::root().'resources/js/components/ui/fluid/select.tsx');

        $this->assertStringContainsString('border-input', $select, 'Borda de campo (3:1 no escuro) no Select do Fluid');
    }

    public function test_field_errors_use_small_soft_destructive_text_with_a_decorative_icon(): void
    {
        $error = file_get_contents(self::root().'resources/js/components/input-error.tsx');
        $field = file_get_contents(self::root().'resources/js/components/ui/forms/form-field.tsx');

        $this->assertStringContainsString('text-xs', $error, 'Erro de campo tem 12px (spec 5)');
        $this->assertStringContainsString('text-destructive-soft-foreground', $error);
        $this->assertMatchesRegularExpression('~<AlertCircle\s[^>]*aria-hidden="true"~', $error);
        $this->assertMatchesRegularExpression('~if \(error\) \{\s*return <InputError id=\{errorId\} message=\{error\} />;~', $field, 'FormField reutiliza InputError');
    }

    /** @return iterable<string, array{string}> */
    public static function filesWithStandaloneFieldErrors(): iterable
    {
        foreach ([
            ...glob(self::root().'resources/js/pages/auth/*.tsx'),
            ...glob(self::root().'resources/js/pages/settings/*.tsx'),
            self::root().'resources/js/components/delete-user.tsx',
            self::root().'resources/js/components/two-factor-setup-modal.tsx',
        ] as $path) {
            if (str_contains(file_get_contents($path), '<InputError')) {
                $file = str_replace(self::root(), '', $path);

                yield $file => [$file];
            }
        }
    }

    #[DataProvider('filesWithStandaloneFieldErrors')]
    public function test_standalone_field_errors_are_tied_to_their_control(string $file): void
    {
        $source = file_get_contents(self::root().$file);
        $errors = preg_match_all('~<InputError\b~', $source);

        $this->assertSame($errors, preg_match_all('~<InputError\b[^>]*\bfield=~s', $source), "{$file}: todo InputError precisa de field");
        $this->assertSame($errors, preg_match_all('~\{\.\.\.errorProps\(~', $source), "{$file}: cada campo com erro precisa de errorProps");
    }

    public function test_form_field_marks_the_control_itself_as_invalid(): void
    {
        $field = file_get_contents(self::root().'resources/js/components/ui/forms/form-field.tsx');

        $this->assertDoesNotMatchRegularExpression('~<div[^>]*aria-invalid~', $field, 'aria-invalid em div não chega ao campo');
        $this->assertStringContainsString("'aria-invalid': true", $field);
        $this->assertStringContainsString("'aria-describedby': errorId", $field);
    }

    public function test_app_logo_keeps_an_accessible_name_without_the_wordmark(): void
    {
        $source = file_get_contents(self::root().'resources/js/components/app-logo.tsx');

        $this->assertMatchesRegularExpression('~!showText &&\s*\(?\s*<span className="sr-only">\{branding\.name\}</span>~', $source);
    }

    public function test_env_example_does_not_suggest_the_monochrome_kit_symbol_as_a_file_logo(): void
    {
        // <img> does not inherit currentColor, so the kit symbol would render black.
        $this->assertStringNotContainsString('BRAND_LOGO_MARK=brand/mark.svg', file_get_contents(self::root().'.env.example'));
    }

    public function test_data_table_renders_icon_only_destructive_actions_as_ghost(): void
    {
        $source = file_get_contents(self::root().'resources/js/components/data-table/core/data-table.tsx');

        $this->assertStringContainsString('function rowActionVariant(', $source);
        $this->assertStringContainsString('hover:bg-destructive-soft', $source);
    }

    public function test_lot_one_pages_use_page_header_and_portuguese_copy(): void
    {
        foreach (self::PAGE_HEADERS as $file => $title) {
            $source = file_get_contents(self::root().$file);

            // Header (dashboard/header) só envolve o PageHeader: os dois valem como cabeçalho único.
            $this->assertMatchesRegularExpression('~<PageHeader|<Header\s+title=~', $source, "{$file} sem PageHeader");
            $this->assertStringContainsString($title, $source, "{$file} sem o título {$title}");
        }

        foreach (self::MIGRATED as $file) {
            if (str_ends_with($file, '.tsx')) {
                $this->assertDoesNotMatchRegularExpression(self::ENGLISH_COPY, file_get_contents(self::root().$file), "Copy em inglês em {$file}");
            }
        }
    }

    public function test_lot_one_pages_have_no_native_form_controls(): void
    {
        foreach (self::MIGRATED as $file) {
            // components/ui embrulha os nativos; a regra vale para as telas.
            if (! str_ends_with($file, '.tsx') || str_starts_with($file, 'resources/js/components/ui/')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('~<select\b|<textarea\b|type="(?:date|time|datetime-local|month)"~', file_get_contents(self::root().$file), "Controle nativo em {$file}");
        }
    }

    public function test_lot_one_lists_use_the_shared_empty_state(): void
    {
        foreach (['resources/js/pages/notifications/index.tsx', 'resources/js/pages/app/exports/index.tsx', 'resources/js/pages/app/email-logs/index.tsx'] as $file) {
            $this->assertStringContainsString('<EmptyState', file_get_contents(self::root().$file), "{$file} sem EmptyState");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function migratedFiles(): iterable
    {
        foreach (self::MIGRATED as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('migratedFiles')]
    public function test_migrated_file_uses_only_semantic_tokens(string $file): void
    {
        $contents = file_get_contents(self::root().$file);

        $this->assertNotFalse($contents, "Arquivo migrado ausente: {$file}");
        preg_match_all(self::FORBIDDEN, $contents, $matches);
        $this->assertSame([], $matches[0], "Paleta fixa ou resto de marca em {$file}: ".implode(', ', array_unique($matches[0])));
    }
}
