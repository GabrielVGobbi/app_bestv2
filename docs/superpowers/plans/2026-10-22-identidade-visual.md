# Identidade visual tematizável: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao Start Kit uma identidade visual própria, acessível e tematizável por configuração, migrando shell, dashboard, auth, settings, CRUD e listagens para tokens semânticos com copy em pt-BR.

**Architecture:** Os tokens semânticos shadcn já usados no código ganham valores novos derivados de `--brand-hue` (oklch) e os papéis que faltavam (tintas suaves, info, hover); `config/branding.php` + `BrandingService` alimentam Blade e Inertia; componentes novos (`BrandMark`, `BrandWordmark`, `PageHeader`, `EmptyState`) e primitivos revisados fazem as telas herdarem o sistema; cada tela do lote migra por substituição de classes fixas por tokens e tem catraca em `TokenUsageTest`.

**Tech Stack:** Laravel 13, Inertia 3, React 19, Tailwind 4 (`@theme`), PHPUnit 12, Playwright via `tests/Fixtures/Frontend/design-parity-browser.mjs`, Vite 8, Geist local.

**Spec:** `docs/superpowers/specs/2026-10-22-identidade-visual-design.md`

## Global Constraints

- Nunca escrever o travessão U+2014 em arquivo, commit ou resposta (regra global do usuário).
- Nunca usar controle nativo (`<select>`, `<textarea>`, `type="date"`) nas telas; usar `components/ui/select.tsx` e `components/ui/textarea.tsx`.
- Copy da interface em pt-BR em todo arquivo tocado; sentence case; sem "!" em mensagens de sistema.
- Proibido nos arquivos do lote: classes de paleta Tailwind fixa (`slate-500`, `emerald-50`, `red-600`...), `text-[10px]`, `text-white`, `AppLogoIcon`, `molecule`, `Poppins`, `Runey`, `Best Play`, `runey-orange`, `primary-dark`.
- Um acento por tela (`primary`); status só para status; dois níveis de texto (`foreground`, `muted-foreground`).
- Não alterar hooks, estado, filtros, persistência ou exportação da DataTable; só classes do shell e a regra de variante das ações de linha.
- Não alterar módulos, rotas, dados, permissões, UUIDs, idempotência, notificações, exports.
- Formatação: Prettier (`npm run format`), ESLint (`npm run lint:check`), TypeScript (`npm run types:check`), Pint nos PHP tocados (`vendor/bin/pint <arquivos>`).
- Commits locais na branch `claude/platform-visual-identity-1bb740`; sem push, sem merge, sem deploy.
- Testes PHPUnit rodam só no ambiente isolado (`phpunit.xml`: sqlite `:memory:`); nunca apontar testes ao banco local.

## Review Focus

1. **`BRAND_HUE` com valor não numérico ("teal") ou fora de 0 a 360** deve cair em 215 sem lançar exceção; teste em Task 1 (`test_hue_outside_range_or_non_numeric_falls_back_to_default`).
2. **Logo configurada apontando para arquivo inexistente** deve cair no símbolo do kit e registrar um aviso, não quebrar a página; teste em Task 1 (`test_missing_logo_file_falls_back_to_kit_mark`).
3. **Nome de marca longo (60 caracteres)** não pode estourar a sidebar de 240px nem o cabeçalho; o wordmark trunca com reticências e mantém `title`; teste no harness em Task 15 (`long brand name does not overflow the sidebar`).
4. **Ação destrutiva de linha com rótulo oculto** deve renderizar como ícone neutro e só ficar vermelha em hover/foco; teste em Task 4 (`TokenUsageTest::test_data_table_renders_icon_only_destructive_actions_as_ghost`) e visual em Task 15.
5. **Primeira pintura no tema escuro** não pode piscar branco: `html.dark` e `#boot-shell` usam as fórmulas oklch dos tokens; teste no harness em Task 15 (`dark boot shell matches the dark canvas`).

## Mapa de substituição de classes

Aplicar em todos os arquivos migrados (Tasks 5 a 14). Coluna da esquerda é regex de busca; direita é o token. Quando a classe tiver prefixo `dark:`, apagar a variante `dark:` inteira: o token já cobre os dois temas.

| Antes (claro) | Depois |
| --- | --- |
| `bg-white`, `bg-white/95`, `bg-slate-50`, `bg-slate-50/..` | `bg-card` |
| `bg-slate-100`, `bg-slate-200` (chips, trilhos) | `bg-muted` |
| `bg-slate-950`, `bg-slate-900` (seleção) | `bg-secondary` + `text-foreground` |
| `text-slate-950`, `text-slate-900`, `text-slate-800`, `text-slate-700` | `text-foreground` |
| `text-slate-600`, `text-slate-500`, `text-slate-400` | `text-muted-foreground` |
| `border-slate-200`, `border-slate-100` | `border-border` (linhas internas: `border-separator`) |
| `hover:bg-slate-50`, `hover:bg-slate-100` | `hover:bg-accent` |
| `shadow-slate-200/50` | remover (ficar só `shadow-xs`) |
| `bg-amber-50/..`, `border-amber-200`, `bg-amber-100 text-amber-600` | fundo do card: nenhum; chip: `bg-warning-soft text-warning-soft-foreground` |
| `bg-emerald-50/..`, `border-emerald-200`, `bg-emerald-100 text-emerald-600`, `text-emerald-500`, `bg-emerald-400` | chip: `bg-success-soft text-success-soft-foreground`; marcador: `bg-success`; ícone: `text-success` |
| `bg-cyan-50 text-cyan-700`, `bg-cyan-400`, `ring-cyan-400` | `bg-primary-soft text-primary-soft-foreground`, `bg-chart-1`, `ring-ring` |
| `text-red-600`, `text-red-500`, `bg-red-50`, `border-red-200`, `text-green-600`, `bg-green-50`, `text-yellow-700`, `bg-yellow-50`, `text-sky-600`, `bg-sky-50`, `text-blue-600`, `bg-blue-50` | respectivo `*-soft` / `*-soft-foreground` (`destructive`, `success`, `warning`, `info`) |
| `bg-red-500`, `bg-red-600` (sólido) | `bg-destructive text-destructive-foreground` |
| `bg-green-500` (sólido) | `bg-success text-success-foreground` |
| `text-[10px]` | `text-2xs` |
| `text-white` em avatar, badge ou botão | `text-primary-foreground` (sobre `primary`) ou `*-soft-foreground` (sobre tinta) |
| `bg-runey-orange` | `bg-primary-soft text-primary-soft-foreground` |
| `text-white/95`, `text-white/70` (sidebar) | `text-sidebar-foreground`, `text-sidebar-muted-foreground` |
| `border-white/10 bg-white/5` (sidebar) | `border-sidebar-border bg-sidebar-accent/60` |
| `hover:bg-white hover:text-black` (nav) | `hover:bg-sidebar-accent hover:text-sidebar-foreground` |
| `bg-primary-dark`, `hover:bg-primary-dark/90` | `bg-primary`, `hover:bg-primary-hover` |

Comando de conferência depois de cada arquivo:

```bash
grep -nE "\b(bg|text|border|ring|from|to|via|fill|stroke|shadow|divide|outline|placeholder)-(slate|zinc|gray|neutral|stone|cyan|emerald|amber|sky|blue|teal|red|green|orange|rose|indigo|violet|purple|yellow|lime)-[0-9]{2,3}\b|text-\[10px\]|text-white\b|primary-dark|runey|AppLogoIcon|molecule" <arquivo>
```

Esperado: nenhuma linha.

## Estrutura de arquivos

Criar:
- `config/branding.php`: parâmetros de marca lidos do `.env`.
- `app/Services/BrandingService.php`: resolve nome, sigla, hue e logos com fallbacks.
- `public/brand/mark.svg`, `public/brand/favicon.svg`, `public/brand/apple-touch-icon.svg`: símbolo do kit.
- `resources/js/components/brand/brand-mark.tsx`: símbolo em React (`currentColor`).
- `resources/js/components/brand/brand-wordmark.tsx`: nome da marca em Geist.
- `resources/js/components/page-header.tsx`: cabeçalho de página único.
- `resources/js/components/empty-state.tsx`: estado vazio único.
- `tests/Feature/BrandingTest.php`: config, fallbacks e prop compartilhado.
- `tests/Unit/Frontend/TokenUsageTest.php`: catraca de tokens por arquivo.
- `PRODUCT.md`: contexto do produto (Impeccable).
- `docs/design/bionexis-contract-2026-10-20.md`: histórico do DESIGN.md atual.

Modificar (principais): `resources/css/app.css`, `resources/views/app.blade.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`, `resources/js/components/app-logo.tsx`, `components/ui/{button,badge,card,input,textarea,alert,sonner}.tsx`, `components/dashboard/*`, `components/nav-module-items.tsx`, `components/user-menu-content.tsx`, `pages/dashboard.tsx`, `components/data-table/{core/data-table.tsx,shell/*}`, `pages/auth/*`, `layouts/auth/*`, `layouts/settings/layout.tsx`, `pages/settings/*`, `pages/app/{users,roles,permissions,clients,notifications,exports,activity-log,email-logs}/*`, `pages/admin/dev/design-system.tsx`, `tests/Fixtures/Frontend/design-parity-browser.mjs`, `tests/Unit/Frontend/DesignParityTest.php`, `DESIGN.md`, `.env.example`.

Remover: `resources/js/components/app-logo-icon.tsx`, `resources/js/components/layout/molecule-background.tsx`, `public/src/imagens/logo-icon-{dark,white}.svg`, `public/src/assets/imagens/molecule/*.png`, `public/favicon.svg` (substituído).

---

### Task 0: Runtime do worktree

O worktree não tem `.env`, `vendor`, `node_modules` nem `public/build`. Sem isso não há build, Wayfinder, PHPUnit nem navegador.

**Files:**
- Create (ignorado pelo git): `.env`
- Modify: `.claude/launch.json`

- [ ] **Step 1: Copiar o `.env` do checkout principal e confirmar que é ignorado**

```bash
cp "D:/laragon/www/Projetos/.a____LARAVEL/start_kit/start_kit/.env" .env
git check-ignore .env
```

Esperado: a saída imprime `.env` (ignorado). Se não imprimir, parar e não commitar o arquivo.

- [ ] **Step 2: Instalar dependências e gerar o build**

```bash
composer install --no-interaction --prefer-dist
npm ci
npm run build
```

Esperado: `vendor/`, `node_modules/` e `public/build/manifest.json` existem; zero erros.

- [ ] **Step 3: Apontar o preview para o worktree**

Substituir o conteúdo de `.claude/launch.json` por:

```json
{
  "version": "0.0.1",
  "configurations": [
    {
      "name": "worktree-serve",
      "runtimeExecutable": "php",
      "runtimeArgs": ["artisan", "serve", "--host=127.0.0.1", "--port=8000"],
      "port": 8000,
      "url": "http://127.0.0.1:8000"
    }
  ]
}
```

`127.0.0.1:8000` está na lista padrão stateful do Sanctum; `localhost:<outra porta>` devolve 401 na API.

- [ ] **Step 4: Confirmar a suíte base**

```bash
php artisan test --compact tests/Feature/DashboardTest.php
npm run types:check
```

Esperado: testes passam; `tsc` sem erros. Nada a commitar (`launch.json` fica fora do git).

---

### Task 1: Configuração de marca no backend

**Files:**
- Create: `config/branding.php`
- Create: `app/Services/BrandingService.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:39-57`
- Modify: `resources/js/types/global.d.ts:5-21`
- Modify: `.env.example` (fim do arquivo)
- Test: `tests/Feature/BrandingTest.php`

**Interfaces:**
- Produces: `App\Services\BrandingService::toArray(): array{name: string, shortName: string, hue: int, logo: array{mark: ?string, light: ?string, dark: ?string}}`, `name()`, `shortName()`, `hue()`, `logoPath(string $kind): ?string`; prop Inertia compartilhado `branding` com a mesma forma; constante `BrandingService::DEFAULT_HUE = 215`.

- [ ] **Step 1: Escrever o teste que falha**

```php
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

    public function test_existing_logo_file_is_exposed_as_absolute_public_path(): void
    {
        config(['branding.logo.mark' => 'brand/mark.svg']);

        $this->assertSame('/brand/mark.svg', app(BrandingService::class)->logoPath('mark'));
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
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
php artisan test --compact tests/Feature/BrandingTest.php
```

Esperado: FAIL com `Class "App\Services\BrandingService" not found`.

- [ ] **Step 3: Criar o config**

`config/branding.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marca tematizável
    |--------------------------------------------------------------------------
    |
    | Cada projeto derivado troca nome, sigla, acento e logos pelo .env.
    | `name` nulo usa app.name. `hue` fora de 0 a 360 cai em 215 (petróleo).
    | Logos são caminhos relativos a public/; arquivo ausente usa o símbolo do kit.
    |
    */

    'name' => env('BRAND_NAME'),
    'short_name' => env('BRAND_SHORT_NAME', 'SK'),
    'hue' => env('BRAND_HUE', 215),
    'logo' => [
        'mark' => env('BRAND_LOGO_MARK'),
        'light' => env('BRAND_LOGO_LIGHT'),
        'dark' => env('BRAND_LOGO_DARK'),
    ],

];
```

- [ ] **Step 4: Criar o serviço**

`app/Services/BrandingService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class BrandingService
{
    public const DEFAULT_HUE = 215;

    /**
     * @return array{name: string, shortName: string, hue: int, logo: array{mark: ?string, light: ?string, dark: ?string}}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'shortName' => $this->shortName(),
            'hue' => $this->hue(),
            'logo' => [
                'mark' => $this->logoPath('mark'),
                'light' => $this->logoPath('light'),
                'dark' => $this->logoPath('dark'),
            ],
        ];
    }

    public function name(): string
    {
        $name = trim((string) config('branding.name', ''));

        return $name !== '' ? $name : (string) config('app.name', 'Laravel');
    }

    public function shortName(): string
    {
        $short = trim((string) config('branding.short_name', ''));

        if ($short === '') {
            $short = mb_substr($this->name(), 0, 2);
        }

        return mb_strtoupper(mb_substr($short, 0, 3));
    }

    public function hue(): int
    {
        $hue = config('branding.hue');

        if (! is_numeric($hue)) {
            return self::DEFAULT_HUE;
        }

        $hue = (int) $hue;

        return $hue >= 0 && $hue <= 360 ? $hue : self::DEFAULT_HUE;
    }

    public function logoPath(string $kind): ?string
    {
        $path = config("branding.logo.{$kind}");

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $relative = '/'.ltrim(trim($path), '/');

        if (! is_file(public_path($relative))) {
            Log::warning('Logo de marca não encontrada; usando o símbolo do kit.', [
                'kind' => $kind,
                'path' => $path,
            ]);

            return null;
        }

        return $relative;
    }
}
```

- [ ] **Step 5: Compartilhar com o Inertia**

Em `app/Http/Middleware/HandleInertiaRequests.php`, adicionar `use App\Services\BrandingService;` e trocar o início do array retornado por `share()`:

```php
        $nav = app(NavigationService::class);
        $branding = app(BrandingService::class)->toArray();

        return [
            ...parent::share($request),
            'name' => $branding['name'],
            'branding' => $branding,
            'auth' => [
```

O resto do array não muda.

- [ ] **Step 6: Tipar o prop**

Em `resources/js/types/global.d.ts`, logo após `name: string;` dentro de `sharedPageProps`:

```ts
            branding: {
                name: string;
                shortName: string;
                hue: number;
                logo: {
                    mark: string | null;
                    light: string | null;
                    dark: string | null;
                };
            };
```

- [ ] **Step 7: Documentar no `.env.example`**

Acrescentar ao final de `.env.example`:

```dotenv

# Marca (config/branding.php). Hue em graus oklch, 0 a 360; padrao 215.
# BRAND_NAME="Start kit"
# BRAND_SHORT_NAME=SK
# BRAND_HUE=215
# BRAND_LOGO_MARK=brand/mark.svg
# BRAND_LOGO_LIGHT=
# BRAND_LOGO_DARK=
```

- [ ] **Step 8: Criar o símbolo para o teste de caminho existente**

`public/brand/mark.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
  <rect x="9" y="2" width="13" height="13" rx="3.5" fill="currentColor" opacity="0.55"/>
  <rect x="2" y="9" width="13" height="13" rx="3.5" fill="currentColor"/>
</svg>
```

- [ ] **Step 9: Rodar e ver passar**

```bash
vendor/bin/pint config/branding.php app/Services/BrandingService.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/BrandingTest.php
php artisan test --compact tests/Feature/BrandingTest.php tests/Feature/DashboardTest.php
npm run types:check
```

Esperado: 6 testes de Branding + Dashboard passam; `tsc` limpo.

- [ ] **Step 10: Commit**

```bash
git add config/branding.php app/Services/BrandingService.php app/Http/Middleware/HandleInertiaRequests.php resources/js/types/global.d.ts .env.example public/brand/mark.svg tests/Feature/BrandingTest.php
git commit -m "feat(branding): config de marca, servico com fallbacks e prop compartilhado"
```

---

### Task 2: Tokens semânticos em `app.css` e boot shell

**Files:**
- Modify: `resources/css/app.css` (blocos `@theme`, `:root`, `.dark`, `::selection`)
- Modify: `resources/views/app.blade.php` (`<html>`, `<style>`, `#boot-shell`, favicon, `<title>`)
- Modify: `public/favicon.svg` (substituir), `public/apple-touch-icon.png` (regenerar)
- Create: `public/brand/favicon.svg`, `public/brand/apple-touch-icon.svg`
- Test: `tests/Unit/Frontend/TokenUsageTest.php`

**Interfaces:**
- Produces: tokens CSS `--primary-hover`, `--primary-soft`, `--primary-soft-foreground`, `--success-soft(-foreground)`, `--warning-soft(-foreground)`, `--destructive-soft(-foreground)`, `--info`, `--info-foreground`, `--info-soft(-foreground)`, `--sidebar-muted-foreground`; utilitários Tailwind correspondentes (`bg-primary-soft`, `text-info-soft-foreground`, `text-sidebar-muted-foreground`...), `text-2xs`; alias temporário `--primary-dark` (removido na Task 4).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Unit/Frontend/TokenUsageTest.php`:

```php
<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenUsageTest extends TestCase
{
    private const FORBIDDEN = '~\b(?:bg|text|border|ring|from|to|via|fill|stroke|shadow|divide|outline|placeholder)-(?:slate|zinc|gray|neutral|stone|cyan|emerald|amber|sky|blue|teal|red|green|orange|rose|indigo|violet|purple|yellow|lime)-\d{2,3}\b|text-\[10px\]|\btext-white\b|primary-dark|primary-light|runey|Runey|Poppins|Best Play|AppLogoIcon|molecule~';

    /** Arquivos já migrados. Cada task do plano acrescenta os seus. */
    private const MIGRATED = [
        'resources/css/app.css',
        'resources/views/app.blade.php',
    ];

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
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
```

Esperado: FAIL (tokens ausentes; `app.blade.php` contém `Runey`/`Poppins`).

- [ ] **Step 3: Reescrever `@theme` em `app.css`**

No bloco `@theme`, manter fontes, raios e containers. Substituir o bloco de aliases de cor (de `--color-background` até `--color-table-text`) por:

```css
    --text-2xs: 0.6875rem;
    --text-2xs--line-height: 1rem;

    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);
    --color-primary: var(--primary);
    --color-primary-hover: var(--primary-hover);
    --color-primary-foreground: var(--primary-foreground);
    --color-primary-soft: var(--primary-soft);
    --color-primary-soft-foreground: var(--primary-soft-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);
    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);
    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);
    --color-destructive: var(--destructive);
    --color-destructive-foreground: var(--destructive-foreground);
    --color-destructive-soft: var(--destructive-soft);
    --color-destructive-soft-foreground: var(--destructive-soft-foreground);
    --color-success: var(--success);
    --color-success-foreground: var(--success-foreground);
    --color-success-soft: var(--success-soft);
    --color-success-soft-foreground: var(--success-soft-foreground);
    --color-warning: var(--warning);
    --color-warning-foreground: var(--warning-foreground);
    --color-warning-soft: var(--warning-soft);
    --color-warning-soft-foreground: var(--warning-soft-foreground);
    --color-info: var(--info);
    --color-info-foreground: var(--info-foreground);
    --color-info-soft: var(--info-soft);
    --color-info-soft-foreground: var(--info-soft-foreground);
    --color-border: var(--border);
    --color-separator: var(--separator);
    --color-input: var(--input);
    --color-input-surface: var(--input-surface);
    --color-ring: var(--ring);
    --color-chart-1: var(--chart-1);
    --color-chart-2: var(--chart-2);
    --color-chart-3: var(--chart-3);
    --color-chart-4: var(--chart-4);
    --color-chart-5: var(--chart-5);
    --color-sidebar: var(--sidebar);
    --color-sidebar-foreground: var(--sidebar-foreground);
    --color-sidebar-muted-foreground: var(--sidebar-muted-foreground);
    --color-sidebar-primary: var(--sidebar-primary);
    --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
    --color-sidebar-accent: var(--sidebar-accent);
    --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
    --color-sidebar-border: var(--sidebar-border);
    --color-sidebar-ring: var(--sidebar-ring);
    --color-header-border: var(--header-border);
    --spacing-header: 4rem;
    --container-8xl: 88rem;
    --container-9xl: 96rem;
    --container-10xl: 100rem;
```

- [ ] **Step 3b: Tirar `primary-dark` dos dois consumidores**

`--primary-dark` deixa de existir nesta task (a catraca `TokenUsageTest` proíbe o nome). Em `resources/js/components/ui/button.tsx:14`, trocar `bg-primary-dark` por `bg-primary`, `hover:bg-primary-dark/90` por `hover:bg-primary-hover` e `active:bg-primary-dark/85` por `active:bg-primary-hover`. Em `resources/js/components/ui/input.tsx:11`, trocar `selection:bg-primary-dark` por `selection:bg-primary`. Conferir: `grep -rn "primary-dark" resources/` não retorna nada.

- [ ] **Step 4: Substituir `:root` (tema claro)**

```css
:root {
    color-scheme: light;
    --brand-hue: 215;

    --background: oklch(0.975 0.004 var(--brand-hue));
    --foreground: oklch(0.22 0.02 var(--brand-hue));
    --card: oklch(1 0 0);
    --card-foreground: var(--foreground);
    --popover: oklch(1 0 0);
    --popover-foreground: var(--foreground);
    --muted: oklch(0.955 0.005 var(--brand-hue));
    --muted-foreground: oklch(0.46 0.02 var(--brand-hue));
    --secondary: oklch(0.93 0.008 var(--brand-hue));
    --secondary-foreground: var(--foreground);
    --accent: oklch(0.91 0.01 var(--brand-hue));
    --accent-foreground: var(--foreground);

    --primary: oklch(0.48 0.11 var(--brand-hue));
    --primary-hover: oklch(0.43 0.11 var(--brand-hue));
    --primary-foreground: oklch(1 0 0);
    --primary-soft: oklch(0.95 0.03 var(--brand-hue));
    --primary-soft-foreground: oklch(0.38 0.1 var(--brand-hue));

    --success: oklch(0.46 0.12 150);
    --success-foreground: oklch(1 0 0);
    --success-soft: oklch(0.95 0.035 150);
    --success-soft-foreground: oklch(0.36 0.1 150);
    --warning: oklch(0.8 0.15 75);
    --warning-foreground: oklch(0.28 0.06 60);
    --warning-soft: oklch(0.96 0.04 80);
    --warning-soft-foreground: oklch(0.4 0.09 60);
    --destructive: oklch(0.48 0.17 25);
    --destructive-foreground: oklch(1 0 0);
    --destructive-soft: oklch(0.95 0.03 20);
    --destructive-soft-foreground: oklch(0.38 0.14 25);
    --info: oklch(0.48 0.1 245);
    --info-foreground: oklch(1 0 0);
    --info-soft: oklch(0.95 0.025 245);
    --info-soft-foreground: oklch(0.38 0.1 245);

    --border: oklch(0.9 0.008 var(--brand-hue));
    --separator: oklch(0.93 0.006 var(--brand-hue));
    --input: oklch(0.58 0.02 var(--brand-hue));
    --input-surface: var(--card);
    --ring: var(--primary);

    --chart-1: var(--primary);
    --chart-2: oklch(0.6 0.02 var(--brand-hue));
    --chart-3: var(--info);
    --chart-4: var(--success);
    --chart-5: var(--warning);

    --sidebar: oklch(0.24 0.03 var(--brand-hue));
    --sidebar-foreground: oklch(0.88 0.01 var(--brand-hue));
    --sidebar-muted-foreground: oklch(0.7 0.02 var(--brand-hue));
    --sidebar-primary: oklch(0.93 0.008 var(--brand-hue));
    --sidebar-primary-foreground: var(--foreground);
    --sidebar-accent: oklch(0.29 0.03 var(--brand-hue));
    --sidebar-accent-foreground: var(--sidebar-foreground);
    --sidebar-border: oklch(0.29 0.02 var(--brand-hue));
    --sidebar-ring: var(--ring);
    --header-border: transparent;
}
```

- [ ] **Step 5: Substituir `.dark`**

```css
.dark {
    color-scheme: dark;

    --background: oklch(0.19 0.01 var(--brand-hue));
    --foreground: oklch(0.94 0.005 var(--brand-hue));
    --card: oklch(0.23 0.01 var(--brand-hue));
    --card-foreground: var(--foreground);
    --popover: oklch(0.27 0.012 var(--brand-hue));
    --popover-foreground: var(--foreground);
    --muted: oklch(0.21 0.01 var(--brand-hue));
    --muted-foreground: oklch(0.72 0.015 var(--brand-hue));
    --secondary: oklch(0.31 0.015 var(--brand-hue));
    --secondary-foreground: var(--foreground);
    --accent: oklch(0.3 0.012 var(--brand-hue));
    --accent-foreground: var(--foreground);

    --primary: oklch(0.78 0.1 var(--brand-hue));
    --primary-hover: oklch(0.83 0.09 var(--brand-hue));
    --primary-foreground: oklch(0.2 0.04 var(--brand-hue));
    --primary-soft: oklch(0.3 0.045 var(--brand-hue));
    --primary-soft-foreground: oklch(0.85 0.08 var(--brand-hue));

    --success: oklch(0.8 0.14 150);
    --success-foreground: oklch(0.22 0.06 150);
    --success-soft: oklch(0.3 0.05 150);
    --success-soft-foreground: oklch(0.85 0.12 150);
    --warning: oklch(0.85 0.14 80);
    --warning-foreground: oklch(0.25 0.06 70);
    --warning-soft: oklch(0.31 0.05 75);
    --warning-soft-foreground: oklch(0.88 0.12 85);
    --destructive: oklch(0.75 0.14 20);
    --destructive-foreground: oklch(0.22 0.08 20);
    --destructive-soft: oklch(0.3 0.06 20);
    --destructive-soft-foreground: oklch(0.86 0.1 20);
    --info: oklch(0.78 0.1 245);
    --info-foreground: oklch(0.2 0.05 245);
    --info-soft: oklch(0.3 0.05 245);
    --info-soft-foreground: oklch(0.86 0.08 245);

    --border: oklch(0.32 0.012 var(--brand-hue));
    --separator: oklch(0.28 0.01 var(--brand-hue));
    --input: oklch(0.52 0.02 var(--brand-hue));
    --input-surface: oklch(0.2 0.01 var(--brand-hue));
    --ring: var(--primary);

    --chart-1: var(--primary);
    --chart-2: oklch(0.65 0.02 var(--brand-hue));
    --chart-3: var(--info);
    --chart-4: var(--success);
    --chart-5: var(--warning);

    --sidebar: oklch(0.21 0.012 var(--brand-hue));
    --sidebar-foreground: oklch(0.86 0.01 var(--brand-hue));
    --sidebar-muted-foreground: oklch(0.68 0.015 var(--brand-hue));
    --sidebar-primary: oklch(0.31 0.015 var(--brand-hue));
    --sidebar-primary-foreground: var(--foreground);
    --sidebar-accent: oklch(0.27 0.012 var(--brand-hue));
    --sidebar-accent-foreground: var(--foreground);
    --sidebar-border: var(--separator);
    --sidebar-ring: var(--ring);
    --header-border: var(--separator);
}
```

Apagar o comentário "Dashboard palette" antigo acima de `:root`. Trocar as duas regras `::selection` (em `@layer base` e no fim do arquivo) por uma só, no `@layer base`, e acrescentar a regra de alto contraste do sistema operacional logo abaixo dela:

```css
    ::selection {
        background-color: var(--primary);
        color: var(--primary-foreground);
    }

    /* Alto contraste do SO: superfícies tingidas perdem o fundo; o contorno mantém a separação. */
    @media (forced-colors: active) {
        [data-slot='badge'],
        [data-slot='button'],
        [data-slot='card'],
        [data-slot='alert'] {
            outline: 1px solid transparent;
        }
    }
```

- [ ] **Step 6: Boot shell, favicon e hue no Blade**

Em `resources/views/app.blade.php`:

1. Logo após `<!DOCTYPE html>`, antes do `<html>`: `@php($branding = app(\App\Services\BrandingService::class))`.
2. `<html lang="..." style="--brand-hue: {{ $branding->hue() }}" @class([...])>`.
3. No `<style>` inline, trocar os blocos `html`, `html.dark`, `#boot-shell`, `#boot-loading`, `#noscript-notice`, `#boot-error` por:

```css
            html {
                background-color: oklch(0.975 0.004 var(--brand-hue, 215));
                color-scheme: light;
            }

            html.dark {
                background-color: oklch(0.19 0.01 var(--brand-hue, 215));
                color-scheme: dark;
            }

            #boot-shell {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: oklch(0.975 0.004 var(--brand-hue, 215));
                color: oklch(0.48 0.11 var(--brand-hue, 215));
            }

            html.dark #boot-shell {
                background: oklch(0.19 0.01 var(--brand-hue, 215));
                color: oklch(0.78 0.1 var(--brand-hue, 215));
            }

            #boot-loading,
            #noscript-notice {
                position: fixed;
                bottom: 16px;
                z-index: 10000;
                padding: 8px 14px;
                border-radius: 8px;
                background: oklch(1 0 0);
                color: oklch(0.46 0.02 var(--brand-hue, 215));
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
                font: 13px/1.4 system-ui, sans-serif;
            }

            #boot-loading { right: 16px; }
            #noscript-notice { left: 16px; }

            html.dark #boot-loading,
            html.dark #noscript-notice {
                background: oklch(0.27 0.012 var(--brand-hue, 215));
                color: oklch(0.72 0.015 var(--brand-hue, 215));
            }

            #boot-error {
                position: fixed;
                right: 12px;
                bottom: 12px;
                left: 12px;
                z-index: 99999;
                max-height: 40vh;
                overflow: auto;
                padding: 10px 14px;
                border: 1px solid oklch(0.38 0.14 25);
                border-radius: 8px;
                background: oklch(0.95 0.03 20);
                color: oklch(0.38 0.14 25);
                font: 12px/1.4 ui-monospace, monospace;
                white-space: pre-wrap;
            }
```

4. Favicons:

```blade
        <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
```

5. `<title>{{ $branding->name() }}</title>` dentro de `<x-inertia::head>`.
6. Substituir o `<svg>` do `#boot-shell` por:

```blade
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" role="img" aria-label="{{ $branding->name() }}">
                <rect x="9" y="2" width="13" height="13" rx="3.5" fill="currentColor" opacity="0.55" />
                <rect x="2" y="9" width="13" height="13" rx="3.5" fill="currentColor" />
            </svg>
```

7. Texto de `#boot-loading`: `Carregando...`; de `#noscript-notice`: `Esta página precisa de JavaScript.`; prefixo em `#boot-error`: `'Erro: '`.

- [ ] **Step 7: Favicon e ícone de toque**

`public/brand/favicon.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
  <style>:root{color:#006c84}@media (prefers-color-scheme:dark){:root{color:#62c8df}}</style>
  <rect x="9" y="2" width="13" height="13" rx="3.5" fill="currentColor" opacity="0.55"/>
  <rect x="2" y="9" width="13" height="13" rx="3.5" fill="currentColor"/>
</svg>
```

`public/brand/apple-touch-icon.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 180">
  <rect width="180" height="180" rx="40" fill="#006c84"/>
  <g transform="translate(30 30) scale(5)" fill="#ffffff">
    <rect x="9" y="2" width="13" height="13" rx="3.5" opacity="0.55"/>
    <rect x="2" y="9" width="13" height="13" rx="3.5"/>
  </g>
</svg>
```

Gerar os binários e remover o favicon do Laravel:

```bash
magick -density 384 -background none public/brand/apple-touch-icon.svg -resize 180x180 public/apple-touch-icon.png
magick -density 384 -background none public/brand/favicon.svg -resize 32x32 public/favicon.ico
git rm -q public/favicon.svg
```

- [ ] **Step 8: Build e testes**

```bash
npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Feature/BrandingTest.php
```

Esperado: build sem erro; testes passam. Abrir `http://127.0.0.1:8000/dashboard` via preview: cores novas no canvas/sidebar, boot shell sem "R", sem flash branco no escuro.

- [ ] **Step 9: Commit**

```bash
git add resources/css/app.css resources/views/app.blade.php public/brand public/favicon.ico public/apple-touch-icon.png tests/Unit/Frontend/TokenUsageTest.php
git rm -q --cached public/favicon.svg 2>/dev/null || true
git commit -m "feat(theme): tokens semanticos por brand-hue, tintas suaves e boot shell da marca"
```

---

### Task 3: Componentes de marca e remoção dos restos Runey, Best Play e Bionexis

**Files:**
- Create: `resources/js/components/brand/brand-mark.tsx`, `resources/js/components/brand/brand-wordmark.tsx`
- Modify: `resources/js/components/app-logo.tsx` (reescrever)
- Modify: `resources/js/components/dashboard/dashboard-sidebar.tsx:1-10,480-500`, `components/dashboard/dashboard-header.tsx`, `components/app-header.tsx:4,96`, `components/app-sidebar.tsx`, `layouts/auth/auth-card-layout.tsx`, `layouts/auth/auth-simple-layout.tsx`, `layouts/auth/auth-split-layout.tsx`, `pages/error.tsx:4,77`, `layouts/app/app-dashboard-layout.tsx`
- Delete: `resources/js/components/app-logo-icon.tsx`, `resources/js/components/layout/molecule-background.tsx`, `public/src/imagens/logo-icon-dark.svg`, `public/src/imagens/logo-icon-white.svg`, `public/src/assets/imagens/molecule/*.png`
- Test: `tests/Unit/Frontend/TokenUsageTest.php` (acrescentar arquivos em `MIGRATED`)

**Interfaces:**
- Produces: `BrandMark({ className?: string })` SVG em `currentColor`; `BrandWordmark({ className?: string })` lê `branding.name`; `AppLogo({ showText?: boolean, className?: string })` compõe `BrandMark` (ou `<img>` do `branding.logo.mark`) + `BrandWordmark`, e quando `branding.logo.light/dark` existir renderiza as imagens completas.

- [ ] **Step 1: Acrescentar ao teste os arquivos desta task**

Em `TokenUsageTest::MIGRATED`, adicionar:

```php
        'resources/js/components/app-logo.tsx',
        'resources/js/components/brand/brand-mark.tsx',
        'resources/js/components/brand/brand-wordmark.tsx',
        'resources/js/layouts/app/app-dashboard-layout.tsx',
        'resources/js/layouts/auth/auth-card-layout.tsx',
        'resources/js/layouts/auth/auth-simple-layout.tsx',
        'resources/js/layouts/auth/auth-split-layout.tsx',
        'resources/js/pages/error.tsx',
```

E um teste de ausência:

```php
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
```

Rodar: `php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php`. Esperado: FAIL.

- [ ] **Step 2: Criar `brand-mark.tsx`**

```tsx
import { cn } from '@/lib/utils';

type Props = { className?: string };

/** Símbolo do kit: dois blocos arredondados sobrepostos. Herda a cor do texto. */
export function BrandMark({ className }: Props) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className={cn('size-6 shrink-0', className)}
        >
            <rect x="9" y="2" width="13" height="13" rx="3.5" fill="currentColor" opacity="0.55" />
            <rect x="2" y="9" width="13" height="13" rx="3.5" fill="currentColor" />
        </svg>
    );
}
```

- [ ] **Step 3: Criar `brand-wordmark.tsx`**

```tsx
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type Props = { className?: string };

export function BrandWordmark({ className }: Props) {
    const { branding } = usePage().props;

    return (
        <span
            title={branding.name}
            className={cn(
                'block max-w-40 truncate font-brand text-[15px] leading-none font-bold tracking-tight',
                className,
            )}
        >
            {branding.name}
        </span>
    );
}
```

- [ ] **Step 4: Reescrever `app-logo.tsx`**

```tsx
import { usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/brand/brand-mark';
import { BrandWordmark } from '@/components/brand/brand-wordmark';
import { cn } from '@/lib/utils';

type Props = {
    /** Mostra o nome ao lado do símbolo. */
    showText?: boolean;
    className?: string;
};

export default function AppLogo({ showText = true, className }: Props) {
    const { branding } = usePage().props;
    const fullLogo = branding.logo.light || branding.logo.dark;

    if (showText && fullLogo) {
        return (
            <span className={cn('flex h-8 items-center', className)}>
                <img
                    src={branding.logo.light ?? branding.logo.dark ?? ''}
                    alt={branding.name}
                    className={cn('h-8 w-auto', branding.logo.dark && 'dark:hidden')}
                />
                {branding.logo.dark && (
                    <img
                        src={branding.logo.dark}
                        alt={branding.name}
                        className="hidden h-8 w-auto dark:block"
                    />
                )}
            </span>
        );
    }

    return (
        <span className={cn('flex items-center gap-2.5', className)}>
            {branding.logo.mark ? (
                <img src={branding.logo.mark} alt="" className="size-7 shrink-0" />
            ) : (
                <BrandMark className="size-7" />
            )}
            {showText && <BrandWordmark />}
        </span>
    );
}
```

- [ ] **Step 5: Trocar os consumidores**

- `dashboard-sidebar.tsx`: remover `import AppLogoIcon`; no bloco da logo, `{isCollapsed ? <AppLogo showText={false} /> : <AppLogo />}` e remover a prop `alt=""`.
- `dashboard-header.tsx`: `<AppLogo showText={!isSidebarCollapsed} />` já é compatível; nada a mudar além de confirmar o import.
- `app-header.tsx:96` e `app-sidebar.tsx`: trocar `<AppLogoIcon className="h-6 w-6 fill-current text-black dark:text-white" />` por `<AppLogo showText={false} />` e ajustar o import.
- `auth-card-layout.tsx`: trocar o bloco do link da logo por `<AppLogo className="text-foreground" />` (importar `AppLogo`).
- `auth-simple-layout.tsx:21` e `auth-split-layout.tsx`: trocar `AppLogoIcon ...` por `<AppLogo showText={false} className="text-foreground" />`; no split, o painel esquerdo usa `bg-sidebar text-sidebar-foreground` no lugar de `bg-muted ... bg-zinc-900 ... text-white`.
- `pages/error.tsx:77`: `<AppLogo showText={false} className="text-foreground" />`.
- `app-dashboard-layout.tsx`: remover `import { MoleculeBackground }` e as duas ocorrências de `<MoleculeBackground />`.
- `error.tsx` e os três layouts de auth entram na catraca nesta task: aplicar o mapa de substituição de classes em qualquer classe fixa que o teste apontar neles (o painel esquerdo do split, por exemplo).

- [ ] **Step 6: Remover os arquivos alheios**

```bash
git rm -q resources/js/components/app-logo-icon.tsx resources/js/components/layout/molecule-background.tsx public/src/imagens/logo-icon-dark.svg public/src/imagens/logo-icon-white.svg
git rm -rq public/src/assets/imagens/molecule
rmdir resources/js/components/layout 2>/dev/null || true
```

- [ ] **Step 7: Verificar**

```bash
grep -rn "AppLogoIcon\|MoleculeBackground\|DEFAULT_LOGO_ALT" resources/js || echo "sem referencias"
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
```

Esperado: "sem referencias"; tudo verde. No navegador: símbolo + "Start kit" na sidebar, só símbolo quando recolhida, login com símbolo, canvas sem moléculas.

- [ ] **Step 8: Commit**

```bash
git add -A resources/js/components resources/js/layouts resources/js/pages/error.tsx public/src tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(brand): simbolo e wordmark do kit; remove logo Runey, alt Best Play e moleculas Bionexis"
```

---

### Task 4: Primitivos, `PageHeader`, `EmptyState` e ações de linha

**Files:**
- Modify: `resources/js/components/ui/button.tsx`, `badge.tsx`, `card.tsx`, `input.tsx`, `textarea.tsx`, `alert.tsx`, `sonner.tsx`
- Modify: `resources/js/components/data-table/core/data-table.tsx:108-150`
- Modify: `resources/css/app.css` (remover alias `primary-dark`)
- Create: `resources/js/components/page-header.tsx`, `resources/js/components/empty-state.tsx`
- Test: `tests/Unit/Frontend/TokenUsageTest.php`

**Interfaces:**
- Produces: `Button` variantes `default | destructive | outline | secondary | ghost | link`, tamanhos `default | sm | lg | icon` (icon com área de toque 44px abaixo de `md`); `Badge` variantes `default | secondary | success | warning | destructive | info | outline`; `Alert` variantes `default | destructive | success | warning | info`; `PageHeader({ title, description?, eyebrow?, actions?, className? })`; `EmptyState({ icon, title, description?, action? })`.

- [ ] **Step 1: Ampliar o teste**

Em `MIGRATED`, acrescentar:

```php
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
```

Novo teste:

```php
    public function test_data_table_renders_icon_only_destructive_actions_as_ghost(): void
    {
        $source = file_get_contents(self::root().'resources/js/components/data-table/core/data-table.tsx');

        $this->assertStringContainsString('function rowActionVariant(', $source);
        $this->assertStringContainsString('hover:bg-destructive-soft', $source);
    }
```

Rodar: esperado FAIL.

- [ ] **Step 2: `button.tsx`**

Substituir `buttonVariants` por:

```tsx
const buttonVariants = cva(
  "cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[background,color,box-shadow,opacity,transform] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:ring-destructive/40 aria-invalid:border-destructive active:translate-y-px",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover",
        destructive:
          "bg-destructive text-destructive-foreground shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive",
        outline:
          "border border-input bg-card text-foreground shadow-xs hover:bg-secondary",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-accent",
        ghost: "text-muted-foreground hover:bg-secondary hover:text-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 px-3 has-[>svg]:px-2.5",
        lg: "h-10 px-6 has-[>svg]:px-4",
        icon: "relative size-9 before:absolute before:-inset-1 before:content-[''] md:before:content-none",
      },
    },
    defaultVariants: { variant: "default", size: "default" },
  }
)
```

- [ ] **Step 3: `badge.tsx`**

Substituir `badgeVariants` por:

```tsx
const badgeVariants = cva(
    "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:ring-2 focus-visible:ring-ring aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
    {
        variants: {
            variant: {
                default: "border-transparent bg-primary-soft text-primary-soft-foreground",
                secondary: "border-transparent bg-secondary text-secondary-foreground",
                success: "border-transparent bg-success-soft text-success-soft-foreground",
                warning: "border-transparent bg-warning-soft text-warning-soft-foreground",
                destructive: "border-transparent bg-destructive-soft text-destructive-soft-foreground",
                info: "border-transparent bg-info-soft text-info-soft-foreground",
                outline: "border-border text-foreground [a&]:hover:bg-accent",
            },
        },
        defaultVariants: { variant: "default" },
    }
)
```

- [ ] **Step 4: `card.tsx`, `input.tsx`, `textarea.tsx`**

- `Card`: classe base `"bg-card text-card-foreground flex flex-col gap-6 rounded-lg border border-border py-6 shadow-xs dark:shadow-none"`.
- `Input`: trocar `selection:bg-primary-dark` por `selection:bg-primary`; trocar `bg-background dark:bg-input-surface` por `bg-input-surface`; manter o resto.
- `Textarea`: aplicar a mesma troca de fundo e seleção (abrir o arquivo e espelhar as classes do `Input`).

- [ ] **Step 5: `alert.tsx`**

```tsx
const alertVariants = cva(
  "relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-muted text-foreground border-border",
        destructive: "bg-destructive-soft text-destructive-soft-foreground border-destructive-soft-foreground/20",
        success: "bg-success-soft text-success-soft-foreground border-success-soft-foreground/20",
        warning: "bg-warning-soft text-warning-soft-foreground border-warning-soft-foreground/20",
        info: "bg-info-soft text-info-soft-foreground border-info-soft-foreground/20",
      },
    },
    defaultVariants: { variant: "default" },
  }
)
```

Em `AlertDescription`, trocar `text-muted-foreground` por `text-current opacity-90`.

- [ ] **Step 6: `sonner.tsx`**

Remover `richColors` e trocar as classes de `success`, `error`, `warning`, `info` por:

```tsx
                    success: 'border-success-soft-foreground/20 bg-success-soft text-success-soft-foreground',
                    error: 'border-destructive-soft-foreground/20 bg-destructive-soft text-destructive-soft-foreground',
                    warning: 'border-warning-soft-foreground/20 bg-warning-soft text-warning-soft-foreground',
                    info: 'border-info-soft-foreground/20 bg-info-soft text-info-soft-foreground',
```

- [ ] **Step 7: `page-header.tsx` e `empty-state.tsx`**

```tsx
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    /** Linha discreta acima do título, como a saudação do dashboard. */
    eyebrow?: ReactNode;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({ title, description, eyebrow, actions, className }: Props) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 md:flex-row md:items-end md:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                {eyebrow && <p className="text-xs text-muted-foreground">{eyebrow}</p>}
                <h1 className="text-2xl font-bold tracking-tight text-balance text-foreground">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>
            )}
        </header>
    );
}
```

```tsx
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
};

export function EmptyState({ icon: Icon, title, description, action }: Props) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <div>
                <p className="text-sm font-semibold text-foreground">{title}</p>
                {description && (
                    <p className="mt-1 text-[13px] text-muted-foreground">{description}</p>
                )}
            </div>
            {action}
        </div>
    );
}
```

- [ ] **Step 8: Regra das ações de linha em `data-table.tsx`**

Acima do componente que renderiza as ações (perto de `actionButtonSize`), adicionar:

```tsx
/** Ações só com ícone nunca ficam vermelhas em repouso: o vermelho aparece em hover e foco. */
function rowActionVariant(
    action: { variant?: string },
    showLabel: boolean,
): { variant: 'ghost' | 'default' | 'destructive' | 'outline' | 'secondary' | 'link'; className?: string } {
    if (!showLabel && action.variant === 'destructive') {
        return {
            variant: 'ghost',
            className:
                'hover:bg-destructive-soft hover:text-destructive-soft-foreground focus-visible:bg-destructive-soft focus-visible:text-destructive-soft-foreground',
        };
    }

    if (!showLabel && action.variant === 'outline') {
        return { variant: 'ghost' };
    }

    return { variant: (action.variant as 'ghost') ?? 'ghost' };
}
```

Nos dois `<Button ...>` do `actions.map`, trocar `variant={action.variant ?? 'ghost'}` por:

```tsx
                            variant={rowActionVariant(action, showLabel).variant}
                            className={rowActionVariant(action, showLabel).className}
```

- [ ] **Step 9: Limpar `data-table.tsx` se a catraca apontar**

O arquivo entra em `MIGRATED` nesta task. Se o teste listar classes fixas nele (fora da função nova), aplicar o mapa de substituição; nada de lógica muda.

- [ ] **Step 10: Verificar**

```bash
grep -rn "primary-dark" resources/ || echo "sem primary-dark"
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
```

No navegador (`/users` em 127.0.0.1:8000): ações de linha em cinza, excluir vermelho só no hover; botão "Novo" petróleo.

- [ ] **Step 11: Commit**

```bash
git add resources/js/components/ui resources/js/components/page-header.tsx resources/js/components/empty-state.tsx resources/js/components/data-table/core/data-table.tsx resources/css/app.css tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(ui): primitivos nos tokens, PageHeader, EmptyState e acoes de linha neutras"
```

---

### Task 5: Shell (cabeçalho, sidebar, navegação de módulos, menu da conta)

**Files:**
- Modify: `resources/js/components/dashboard/dashboard-header.tsx`, `dashboard-sidebar.tsx`, `nav-module-items.tsx`, `nav-module-switcher.tsx`, `user-menu-content.tsx`, `theme-toggle.tsx`, `notification-bell.tsx`, `breadcrumbs.tsx`, `nav-footer.tsx`
- Test: `TokenUsageTest::MIGRATED` (acrescentar os nove arquivos)

- [ ] **Step 1: Acrescentar os arquivos ao teste e rodar (FAIL esperado)**

- [ ] **Step 2: `dashboard-header.tsx`**

- Botão mobile de menu: `className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-foreground md:hidden"`.
- Avatar do menu da conta: `AvatarFallback` com `className="rounded-full bg-primary-soft text-sm font-bold text-primary-soft-foreground"`.
- Botão de recolher: manter; o `focus-visible:ring-ring` já usa o token.

- [ ] **Step 3: `dashboard-sidebar.tsx`**

- Rótulos de seção (`Módulos`, títulos de seção e de módulo, três ocorrências): `text-2xs font-semibold tracking-wide text-sidebar-muted-foreground uppercase`.
- Tooltips flutuantes do item recolhido (`bg-foreground ... text-background text-[10px]`): `rounded-md bg-popover px-2 py-1 text-2xs font-semibold text-popover-foreground shadow-md`.
- Bloco da conta no rodapé: botão com `border-sidebar-border bg-sidebar-accent/60 text-sidebar-foreground hover:bg-sidebar-accent data-[state=open]:bg-sidebar-accent`; `AvatarFallback` com `bg-primary-soft text-2xs font-bold text-primary-soft-foreground`; nome `text-[13px] font-semibold text-sidebar-foreground`; e-mail `text-2xs text-sidebar-muted-foreground`.
- Item ativo e hover (todas as ocorrências de `bg-secondary text-foreground` e `hover:bg-secondary hover:text-foreground` dentro da sidebar): `bg-sidebar-primary text-sidebar-primary-foreground` e `hover:bg-sidebar-accent hover:text-sidebar-accent-foreground`.
- Linha de grupos aninhados `border-l border-border` passa a `border-sidebar-border`.

- [ ] **Step 4: `nav-module-items.tsx`**

- `SidebarNavButton`: `isActive ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'`.
- `CompactSidebarLink`: mesmo par acima no lugar de `hover:bg-secondary hover:text-foreground` e `bg-secondary text-foreground`.
- Variante `header` (abas de módulos): aba ativa `bg-secondary text-foreground`, inativa `text-muted-foreground hover:bg-accent hover:text-foreground`; conferir que nenhuma classe `white`/`black` sobrou.

- [ ] **Step 5: `user-menu-content.tsx` e `nav-footer.tsx`**

- Rótulos: `Settings` vira `Configurações`; `Log out` vira `Sair`.
- `nav-footer.tsx`: aplicar o mapa de substituição (quatro classes fixas).

- [ ] **Step 6: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
git add resources/js/components tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(shell): sidebar, cabecalho e menus nos tokens com copy em pt-BR"
```

No navegador: sidebar escura petróleo, item ativo em cápsula clara, conta no rodapé sem branco translúcido, avatar em tinta suave.

---

### Task 6: Dashboard

**Files:**
- Modify: `resources/js/pages/dashboard.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ `resources/js/pages/dashboard.tsx`)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL: 72 ocorrências)**

- [ ] **Step 2: Constantes e cabeçalho**

```tsx
const panel = 'rounded-lg border border-border bg-card shadow-xs dark:shadow-none';
const focus =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';
```

Substituir o bloco `<div className="flex flex-col gap-4 lg:flex-row ...">` do topo por:

```tsx
                <PageHeader
                    eyebrow={
                        <>
                            Olá, <span className="font-semibold text-foreground">{firstName}</span>
                        </>
                    }
                    title="Visão geral"
                    actions={
                        <div
                            role="group"
                            aria-label="Filtrar recursos por módulo"
                            className={cn(panel, 'flex flex-wrap items-center gap-1 p-1')}
                        >
                            {[{ slug: 'all', label: 'Todos os módulos' }, ...modules].map((module) => (
                                <button
                                    key={module.slug}
                                    type="button"
                                    aria-pressed={selected === module.slug}
                                    onClick={() => setSelected(module.slug)}
                                    className={cn(
                                        'h-8 rounded-md px-3 text-xs font-semibold transition-colors',
                                        focus,
                                        selected === module.slug
                                            ? 'bg-secondary text-foreground'
                                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                    )}
                                >
                                    {module.label}
                                </button>
                            ))}
                        </div>
                    }
                />
```

Importar `PageHeader` de `@/components/page-header`.

- [ ] **Step 3: `Metric`**

Remover as tintas de fundo por `tone`; o tom fica só no chip:

```tsx
const toneChip = {
    slate: 'bg-muted text-muted-foreground',
    amber: 'bg-warning-soft text-warning-soft-foreground',
    emerald: 'bg-success-soft text-success-soft-foreground',
} as const;

// dentro do JSX de Metric:
<section className={cn(panel, 'relative min-h-40 p-3')}>
    <div className="flex items-start justify-between gap-2">
        <h2 className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</h2>
        <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-full', toneChip[tone])}>
            <Icon size={14} />
        </span>
    </div>
    <p className="mt-3 text-xl font-bold tracking-tight text-foreground tabular-nums">{value}</p>
    <p className="mt-1 text-2xs leading-relaxed text-muted-foreground">{caption}</p>
</section>
```

- [ ] **Step 4: Resto da página pelo mapa**

Aplicar o mapa de substituição no restante (painel principal, barra de distribuição, atalhos, listas e "Sua conta"). Pontos específicos:

- barra de distribuição: `['bg-chart-1', 'bg-chart-2', 'bg-chart-3'][index % 3]`, trilho `bg-muted`;
- ícone do painel principal: `bg-primary-soft text-primary-soft-foreground`;
- marcador de recurso: `bg-success`; ícones de "Sua conta": `text-success`; check final: `text-muted-foreground`;
- cabeçalho da lista (`Recurso / Módulo / Acesso`): `text-2xs font-semibold tracking-wide text-muted-foreground uppercase`;
- linhas: `border-b border-separator last:border-b-0 hover:bg-accent`, sem `rounded-lg` (uma classe só para os dois temas);
- contadores `font-mono text-[11px]` viram `font-mono text-2xs tabular-nums`;
- atalhos: `border-transparent bg-muted text-foreground hover:bg-accent`.
- Todos os `dark:` desta página são apagados.

- [ ] **Step 5: Verificar e commitar**

```bash
grep -c "dark:" resources/js/pages/dashboard.tsx   # esperado: 0
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
git add resources/js/pages/dashboard.tsx tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(dashboard): composicao mantida sobre tokens semanticos"
```

---

### Task 7: Shell da DataTable

**Files:**
- Modify: `resources/js/components/data-table/shell/data-table-header.tsx`, `data-table-body.tsx`, `data-table-toolbar.tsx`, `data-table-shell.tsx`, `data-table-export-status.tsx`, `data-table-bulk-actions-bar.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os seis arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**

- [ ] **Step 2: Cabeçalho**

Em `data-table-header.tsx`: `<thead className="sticky top-0 z-10 bg-muted">`; `th` com `'relative overflow-hidden text-xs font-semibold whitespace-nowrap text-muted-foreground'` (sem uppercase); coluna fixa `bg-muted`; alça de resize `hover:bg-primary/60` mantém.

- [ ] **Step 3: Corpo**

Em `data-table-body.tsx`: linha `'hover:bg-accent'`, selecionada `'bg-primary-soft/40'`, zebra `even:bg-muted/60` e `even:bg-muted/30`; célula fixa `bg-card`; células numéricas e de data ganham `tabular-nums` quando `meta.type` for `number` ou `date` (acrescentar `meta?.type === 'number' || meta?.type === 'date' ? 'tabular-nums' : null` ao `cn` da `td`).

- [ ] **Step 4: Toolbar, status de export e barra de ações em massa**

Aplicar o mapa de substituição. Na barra de ações em massa, o contêiner usa `bg-popover text-popover-foreground border-border shadow-md`; a ação destrutiva em massa continua `variant="destructive"` (tem rótulo visível).

- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Unit/DataTableExportPayloadTest.php
git add resources/js/components/data-table tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(data-table): cabecalho rebaixado, hover e selecao nos tokens"
```

No navegador (`/users`, `/roles`): cabeçalho claro cinza em vez de navy; sem segunda massa escura no claro.

---

### Task 8: Autenticação

**Files:**
- Modify: `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `confirm-password.tsx`, `verify-email.tsx`, `two-factor-challenge.tsx`, `force-password-change.tsx`; `resources/js/components/text-link.tsx`, `input-error.tsx`, `password-input.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os onze arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**

- [ ] **Step 2: Componentes de apoio**

- `input-error.tsx`: classe `text-sm text-destructive-soft-foreground`.
- `text-link.tsx`: `text-foreground underline decoration-muted-foreground underline-offset-4 transition-colors hover:decoration-foreground`.
- `password-input.tsx`: botão de mostrar com `aria-label="Mostrar senha"` / `"Ocultar senha"`.

- [ ] **Step 3: Copy pt-BR por página**

| Arquivo | EN | PT |
| --- | --- | --- |
| login | `Log in` (Head, botão) | `Entrar` |
| login | `Email address` | `E-mail` |
| login | `email@example.com` | `nome@empresa.com.br` |
| login | `Password` (label e placeholder) | `Senha` |
| login | `Forgot password?` | `Esqueceu a senha?` |
| login | `Remember me` | `Lembrar de mim` |
| login | `Don't have an account?` / `Sign up` | `Não tem conta?` / `Cadastre-se` |
| login | `Log in to your account` / `Enter your email and password below to log in` | `Entrar na sua conta` / `Informe seu e-mail e senha para continuar` |
| register | `Register`, `Name`, `Full name`, `Create account`, `Already have an account?`, `Log in`, `Create an account`, `Enter your details below to create your account`, `Confirm password` | `Cadastrar`, `Nome`, `Nome completo`, `Criar conta`, `Já tem conta?`, `Entrar`, `Criar uma conta`, `Preencha os dados abaixo para criar sua conta`, `Confirmar senha` |
| forgot-password | `Forgot password`, `Enter your email to receive a password reset link`, `Email password reset link`, `Or, return to`, `log in` | `Esqueci a senha`, `Informe seu e-mail para receber o link de redefinição`, `Enviar link de redefinição`, `Ou volte para`, `entrar` |
| reset-password | `Reset password`, `Please enter your new password below`, `Reset password` (botão) | `Redefinir senha`, `Digite a nova senha abaixo`, `Redefinir senha` |
| confirm-password | `Confirm your password`, `This is a secure area of the application. Please confirm your password before continuing.`, `Confirm password` | `Confirme sua senha`, `Esta é uma área protegida. Confirme sua senha para continuar.`, `Confirmar senha` |
| verify-email | `Verify email`, `Please verify your email address by clicking on the link we just emailed to you.`, `A new verification link has been sent...`, `Resend verification email`, `Log out` | `Verificar e-mail`, `Confirme seu e-mail pelo link que acabamos de enviar.`, `Um novo link de verificação foi enviado para o e-mail cadastrado.`, `Reenviar e-mail de verificação`, `Sair` |
| two-factor-challenge | títulos, descrições e botões em inglês | `Autenticação em duas etapas`, `Digite o código do seu aplicativo autenticador`, `Continuar`, `usar um código de recuperação`, `usar o código do autenticador` |
| force-password-change | títulos, descrições e botões em inglês | `Defina uma nova senha`, `Sua senha temporária precisa ser trocada antes de continuar`, `Salvar nova senha` |

Antes de editar cada página, listar as strings reais com `grep -nE ">[A-Z][a-z].*<|title=\"|description=\"|placeholder=\"" <arquivo>` e cobrir todas.

- [ ] **Step 4: Classes**

Aplicar o mapa (`text-green-600` em `status` vira `text-success-soft-foreground`; `text-red-*` vira `text-destructive-soft-foreground`).

- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Feature/Auth
git add resources/js/pages/auth resources/js/components/text-link.tsx resources/js/components/input-error.tsx resources/js/components/password-input.tsx tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(auth): telas de autenticacao em pt-BR sobre tokens"
```

Nota: se algum teste de `tests/Feature/Auth` verificar texto em inglês renderizado (por exemplo `assertSee('Log in')`), atualizar a asserção para o novo texto, nunca enfraquecer.

---

### Task 9: Configurações

**Files:**
- Modify: `resources/js/layouts/settings/layout.tsx`, `resources/js/pages/settings/profile.tsx`, `security.tsx`, `appearance.tsx`, `resources/js/components/appearance-tabs.tsx`, `delete-user.tsx`, `heading.tsx`, `two-factor-setup-modal.tsx`, `two-factor-recovery-codes.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os nove arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**

- [ ] **Step 2: Layout**

Em `layouts/settings/layout.tsx`: itens `Perfil`, `Segurança`, `Aparência`; `aria-label="Configurações"`; substituir `<Heading title="Settings" description="..." />` por `<PageHeader title="Configurações" description="Gerencie seu perfil e as preferências da conta" />`; item ativo `bg-secondary text-foreground`.

- [ ] **Step 3: `heading.tsx`**

```tsx
<h2 className={variant === 'small' ? 'mb-0.5 text-base font-semibold' : 'text-xl font-semibold tracking-tight'}>
```

Descrição: `text-[13px] text-muted-foreground`.

- [ ] **Step 4: Copy pt-BR**

| Arquivo | EN | PT |
| --- | --- | --- |
| profile | `Profile settings`, `Profile information`, `Update your name and email address`, `Name`, `Full name`, `Email address`, `Email address` (placeholder), `Your email address is unverified.`, `Click here to resend the verification email.`, `A new verification link has been sent to your email address.`, `Save`, `Saved` | `Perfil`, `Informações do perfil`, `Atualize seu nome e e-mail`, `Nome`, `Nome completo`, `E-mail`, `nome@empresa.com.br`, `Seu e-mail ainda não foi verificado.`, `Reenviar e-mail de verificação.`, `Um novo link de verificação foi enviado para o seu e-mail.`, `Salvar`, `Salvo` |
| security | `Security settings`, `Update password`, `Ensure your account is using a long, random password to stay secure`, `Current password`, `New password`, `Confirm password`, `Save password`, `Saved`, `Two-factor authentication` e textos do bloco 2FA | `Segurança`, `Alterar senha`, `Use uma senha longa e aleatória para manter a conta protegida`, `Senha atual`, `Nova senha`, `Confirmar senha`, `Salvar senha`, `Salvo`, `Autenticação em duas etapas` e equivalentes |
| appearance + appearance-tabs | `Appearance settings`, `Update your account's appearance settings`, `Light`, `Dark`, `System` | `Aparência`, `Escolha o tema da interface`, `Claro`, `Escuro`, `Sistema` |
| delete-user | `Delete account`, `Delete your account and all of its resources`, `Warning`, `Please proceed with caution, this cannot be undone.`, `Are you sure you want to delete your account?`, `Once your account is deleted...`, `Password`, `Cancel`, `Delete account` | `Excluir conta`, `Exclui sua conta e todos os seus dados`, `Atenção`, `Esta ação não pode ser desfeita.`, `Tem certeza de que quer excluir sua conta?`, `Depois da exclusão, todos os dados serão apagados definitivamente. Digite sua senha para confirmar.`, `Senha`, `Cancelar`, `Excluir conta` |
| two-factor-setup-modal, two-factor-recovery-codes | todos os textos em inglês | equivalentes em pt-BR, tom direto |

`delete-user.tsx`: o bloco de aviso usa `<Alert variant="destructive">`; o botão "Excluir conta" continua `variant="destructive"` (abre diálogo de confirmação).

- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Feature/Settings
git add resources/js/layouts/settings resources/js/pages/settings resources/js/components/appearance-tabs.tsx resources/js/components/delete-user.tsx resources/js/components/heading.tsx resources/js/components/two-factor-setup-modal.tsx resources/js/components/two-factor-recovery-codes.tsx tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(settings): configuracoes em pt-BR sobre tokens e PageHeader"
```

---

### Task 10: Usuários

**Files:**
- Modify: `resources/js/pages/app/users/index.tsx`, `create.tsx`, `edit.tsx`, `show.tsx`, `roles.tsx`, `permissions.tsx`, `partials/user-form-fields.tsx`, `components/user-sub-nav.tsx`, `resources/js/components/temporary-password-dialog.tsx`, `delete-confirm-modal.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os dez arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**

- [ ] **Step 2: Cabeçalho e diálogo em `index.tsx`**

Trocar o bloco `<div className="flex items-center justify-between gap-4"><Heading .../>...</div>` por:

```tsx
                    <PageHeader
                        title="Usuários"
                        description="Gerencie as contas de usuário"
                        actions={
                            can.create && (
                                <Button type="button" onClick={() => setCreateUserOpen(true)}>
                                    <Plus className="size-4" />
                                    Novo usuário
                                </Button>
                            )
                        }
                    />
```

`Head title="Usuários"`; breadcrumb `title: 'Usuários'`; `DialogTitle` `Novo usuário`; `DialogDescription` `Crie uma conta sem sair da lista.`; rótulos das ações `View/Edit/Delete` viram `Visualizar/Editar/Excluir` (o `label` é o `aria-label`); textos restantes em inglês (`Verified`, `Yes`, `No`, `Created at`, `Actions`, `Export`...) viram `Verificado`, `Sim`, `Não`, `Criado em`, `Ações`, `Exportar`. Badges de verificação: `variant="success"` para `Sim`, `variant="secondary"` para `Não`.

- [ ] **Step 3: Demais páginas**

- `create.tsx`: breadcrumbs `Usuários` / `Novo usuário`; `PageHeader title="Novo usuário" description="Crie uma nova conta de usuário" actions={<Button variant="outline" asChild><Link href={index.url()}>Cancelar</Link></Button>}`.
- `user-form-fields.tsx`: `Informações do usuário`, `Primeiro acesso com senha temporária`, `A conta será criada com uma senha temporária segura. Copie após a criação e envie ao usuário; ele precisará trocá-la no primeiro acesso.`, `Nome`, `Nome completo`, `E-mail`, `nome@empresa.com.br`, `Criar usuário`, `Salvar alterações`, `Cancelar`. O bloco informativo usa `<Alert variant="info">`.
- `edit.tsx`, `show.tsx`, `roles.tsx`, `permissions.tsx`, `user-sub-nav.tsx`: títulos e abas em pt-BR (`Editar usuário`, `Detalhes`, `Papéis`, `Permissões`), `PageHeader`, mapa de classes.
- `temporary-password-dialog.tsx`, `delete-confirm-modal.tsx`: textos em pt-BR; botão de confirmação de exclusão `variant="destructive"`.

- [ ] **Step 4: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Feature/UserManagementTest.php
git add resources/js/pages/app/users resources/js/components/temporary-password-dialog.tsx resources/js/components/delete-confirm-modal.tsx tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(users): CRUD de usuarios em pt-BR sobre tokens e PageHeader"
```

Se `tests/Feature/UserManagementTest.php` não existir com esse nome, rodar `php artisan test --compact --filter=User`.

---

### Task 11: Papéis e permissões

**Files:**
- Modify: `resources/js/pages/app/roles/index.tsx`, `edit.tsx`, `show.tsx`, `permissions.tsx`, `components/role-sub-nav.tsx`; `resources/js/pages/app/permissions/index.tsx`, `edit.tsx`, `show.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os oito arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**
- [ ] **Step 2: `PageHeader` em cada index/show/edit** (`Papéis` / `Gerencie os papéis de acesso do sistema` / `Novo papel`; `Permissões` / `Gerencie as permissões disponíveis` / `Nova permissão`); abas `Detalhes`, `Permissões`, `Usuários`.
- [ ] **Step 3: Contadores** (`Permissões`, `Usuários` nas colunas): `tabular-nums`, ícone `text-muted-foreground`; avatar da linha `bg-primary-soft text-primary-soft-foreground`.
- [ ] **Step 4: Mapa de classes e strings restantes em inglês** (`grep -nE "'(Edit|View|Delete|Save|Cancel|Name|Description|Created at|Actions)'" <arquivo>`).
- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php --filter="Role|Permission"
git add resources/js/pages/app/roles resources/js/pages/app/permissions tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(acl): papeis e permissoes em pt-BR sobre tokens"
```

---

### Task 12: Clientes

**Files:**
- Modify: `resources/js/pages/app/clients/index.tsx`, `create.tsx`, `edit.tsx`, `show.tsx`, `components/client-sub-nav.tsx`, `partials/client-form-fields.tsx`, `partials/client-delete-dialog.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os sete arquivos)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL)**
- [ ] **Step 2: `PageHeader`** (`Clientes` / `Gerencie os registros de clientes` / `Novo cliente`; `Novo cliente` / `Cadastre um novo cliente`); `Head` em pt-BR; colunas `Nome`, `E-mail`, `Ações`.
- [ ] **Step 3: Mapa de classes; diálogo de exclusão com `variant="destructive"` só no botão de confirmar.**
- [ ] **Step 4: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php --filter=Client
git add resources/js/pages/app/clients tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(clients): clientes em pt-BR sobre tokens e PageHeader"
```

---

### Task 13: Listagens: notificações, exportações, activity log e e-mails

**Files:**
- Modify: `resources/js/pages/notifications/index.tsx`, `resources/js/components/notification-item.tsx`, `resources/js/pages/app/exports/index.tsx`, `resources/js/pages/app/activity-log/index.tsx`, `resources/js/pages/app/email-logs/index.tsx`
- Test: `TokenUsageTest::MIGRATED` (+ os cinco arquivos) e regra de `<select`:

```php
    public function test_lot_one_pages_have_no_native_form_controls(): void
    {
        foreach (self::MIGRATED as $file) {
            if (! str_ends_with($file, '.tsx')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('~<select\b|<textarea\b|type="(?:date|time|datetime-local|month)"~', file_get_contents(self::root().$file), "Controle nativo em {$file}");
        }
    }
```

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL: email-logs tem `<select`)**

- [ ] **Step 2: E-mails: substituir os dois `<select>`**

```tsx
            <Select
                value={filters.status ?? 'all'}
                onValueChange={(value) => applyFilters({ status: value === 'all' ? undefined : value })}
            >
                <SelectTrigger size="sm" className="w-44" aria-label="Filtrar por status">
                    <SelectValue placeholder="Todos os status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos os status</SelectItem>
                    {Object.entries(statuses).map(([value, label]) => (
                        <SelectItem key={value} value={value}>{label}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
```

Repetir para categorias (`Todas as categorias`, `aria-label="Filtrar por categoria"`). Importar `Select, SelectContent, SelectItem, SelectTrigger, SelectValue` de `@/components/ui/select`. Se `SelectTrigger` não aceitar `size`, usar `className="h-8 w-44 text-xs"`.

- [ ] **Step 3: Estados vazios**

Nas quatro páginas, trocar os blocos "ícone + texto" por `<EmptyState icon={Bell} title="Nenhuma notificação" description="Você está em dia com tudo." />`, `<EmptyState icon={FileText} title="Nenhuma exportação" description="Os arquivos gerados aparecem aqui por uma hora após a conclusão." />`, `<EmptyState icon={Mail} title="Nenhum e-mail encontrado" />`; o `emptyMessage` da DataTable do activity log continua texto.

- [ ] **Step 4: Classes e badges**

Aplicar o mapa. Badges de status de e-mail/exportação: `sent/completed` = `success`, `failed` = `destructive`, `pending/queued` = `warning`, `delivered` = `info`. O botão "Kill Switch" vira `variant="outline"` com ícone e texto `Interromper envios`; quando desligado, `Badge variant="destructive"` com `Desligado`.

- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php --filter="Notification|Export|ActivityLog|EmailLog"
git add resources/js/pages/notifications resources/js/components/notification-item.tsx resources/js/pages/app/exports resources/js/pages/app/activity-log resources/js/pages/app/email-logs tests/Unit/Frontend/TokenUsageTest.php
git commit -m "feat(lists): listagens nos tokens, Select do sistema e estados vazios unicos"
```

---

### Task 14: Catálogo do design system

**Files:**
- Modify: `resources/js/pages/admin/dev/design-system.tsx` (seções `colors`, `typography`, `badges`, `alerts`)
- Test: `TokenUsageTest::MIGRATED` (+ o arquivo)

- [ ] **Step 1: Acrescentar ao teste e rodar (FAIL: 12 ocorrências)**
- [ ] **Step 2: Seção "Cores"**: lista de swatches com os tokens `background, card, popover, muted, secondary, accent, primary, primary-soft, success, success-soft, warning, warning-soft, destructive, destructive-soft, info, info-soft, border, input, sidebar`, cada um com o nome do token e o par de texto; exemplo de cada swatch:

```tsx
<div className="rounded-lg border border-border bg-primary-soft p-3 text-primary-soft-foreground">
    <p className="text-xs font-semibold">--primary-soft</p>
    <p className="text-2xs">texto: --primary-soft-foreground</p>
</div>
```

- [ ] **Step 3: Seção "Tipografia"**: amostras 11, 12, 13, 14, 16, 20, 24, 30px com os papéis da spec e um exemplo `tabular-nums`.
- [ ] **Step 4: Badges e alerts**: incluir as variantes `success`, `warning`, `info`.
- [ ] **Step 5: Verificar e commitar**

```bash
npm run types:check && npm run lint:check && npm run build
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php
git add resources/js/pages/admin/dev/design-system.tsx tests/Unit/Frontend/TokenUsageTest.php
git commit -m "docs(design-system): catalogo com tokens semanticos e tintas suaves"
```

---

### Task 15: Harness Playwright e verificação completa

**Files:**
- Modify: `tests/Fixtures/Frontend/design-parity-browser.mjs`, `tests/Unit/Frontend/DesignParityTest.php`

- [ ] **Step 1: Ampliar as asserções PHP (falham antes do runner)**

Em `DesignParityTest`, após as asserções existentes:

```php
        $this->assertSame([], $report['lightAudit']['failures']);
        $this->assertSame([], $report['usersAudit']['failures']);
        $this->assertSame([], $report['createAudit']['failures']);
        $this->assertSame([], $report['profileAudit']['failures']);
        $this->assertSame([], $report['loginLightAudit']['failures']);
        $this->assertSame([], array_values(array_filter($report['served'], fn (string $url) => preg_match('~logo-icon|molecule~', $url) === 1)));
        $this->assertTrue($report['bootShell']['dark']);
        $this->assertTrue($report['longBrand']['fits']);
```

Trocar o nome do teste para `test_real_application_build_renders_the_start_kit_visual_contract_over_http` e `setTimeout(90)` por `setTimeout(150)`.

- [ ] **Step 2: Runner: registrar arquivos servidos e mock da API de usuários**

No handler `createServer`, no início: `report.served.push(request.url);` (inicializar `served: []` em `report`). Antes do roteamento estático, responder:

```js
    if (request.url.startsWith('/api/v1/users')) {
        response.writeHead(200, { 'content-type': 'application/json' });
        response.end(JSON.stringify({
            data: [1, 2, 3].map((id) => ({
                id,
                uuid: `00000000-0000-4000-8000-00000000000${id}`,
                name: ['Preview Visual', 'Ana Souza', 'Caio Lima'][id - 1],
                email: `pessoa${id}@example.test`,
                email_verified_at: id === 3 ? null : '2026-10-20T00:00:00Z',
                created_at: '2026-10-21T15:54:00Z',
                updated_at: '2026-10-21T15:54:00Z',
                can: { update: true, delete: id !== 1 },
            })),
            meta: { current_page: 1, last_page: 1, per_page: 30, total: 3 },
        }));

        return;
    }
```

Conferir os campos contra `resources/js/types/user.ts` e acrescentar os obrigatórios que faltarem. Requisições `/api/v1/users` não contam como `backendRequests`.

- [ ] **Step 3: Runner: `payload()` para as novas rotas**

```js
const components = {
    '/admin/dev/design-system': 'admin/dev/design-system',
    '/login': 'auth/login',
    '/app/users': 'app/users/index',
    '/app/users/create': 'app/users/create',
    '/settings/profile': 'settings/profile',
};
// component: components[new URL(url).pathname] ?? 'dashboard'
```

Props adicionais: `branding: { name: mode === 'long' ? 'Plataforma de Gestão Integrada Corporativa Ltda' : 'Start kit', shortName: 'SK', hue: 215, logo: { mark: null, light: null, dark: null } }`, `can: { create: true, update: true, delete: true, view: true }`, `mustVerifyEmail: false`.

- [ ] **Step 4: Runner: auditorias no claro, boot shell e marca longa**

Renomear `auditDark` para `auditContrast` (todas as chamadas). Logo após `document.fonts.ready` no dashboard claro:

```js
    report.lightAudit = await auditContrast(page);
    check('visible light text meets WCAG AA contrast', report.lightAudit.failures.length === 0);
    report.bootShell = await page.evaluate(() => ({
        dark: (() => {
            const probe = document.createElement('div');
            probe.id = 'boot-shell';
            document.documentElement.classList.add('dark');
            document.body.appendChild(probe);
            const shell = getComputedStyle(probe).backgroundColor;
            const canvas = getComputedStyle(document.body).backgroundColor;
            probe.remove();
            document.documentElement.classList.remove('dark');
            return shell === canvas;
        })(),
    }));
    check('dark boot shell matches the dark canvas', report.bootShell.dark);
```

Depois, ainda no claro:

```js
    await page.goto(`${origin}/app/users`);
    await page.getByRole('cell', { name: 'Ana Souza' }).waitFor();
    report.usersAudit = await auditContrast(page);
    check('users list text meets light WCAG AA contrast', report.usersAudit.failures.length === 0);
    check(
        'icon-only delete action is neutral at rest',
        await page.getByRole('button', { name: 'Excluir' }).first().evaluate((el) => !getComputedStyle(el).backgroundColor.includes('oklch(0.48')),
    );
    await page.goto(`${origin}/app/users/create`);
    await page.getByRole('heading', { name: 'Novo usuário' }).waitFor();
    report.createAudit = await auditContrast(page);
    check('create form text meets light WCAG AA contrast', report.createAudit.failures.length === 0);
    await page.goto(`${origin}/settings/profile`);
    await page.getByRole('heading', { name: 'Configurações' }).waitFor();
    report.profileAudit = await auditContrast(page);
    check('settings text meets light WCAG AA contrast', report.profileAudit.failures.length === 0);
    await page.goto(`${origin}/login`);
    await page.getByRole('textbox', { name: 'E-mail', exact: true }).waitFor();
    report.loginLightAudit = await auditContrast(page);
    check('login text meets light WCAG AA contrast', report.loginLightAudit.failures.length === 0);
    await page.goto(`${origin}/dashboard?mode=long`);
    await page.getByRole('heading', { name: 'Visão geral' }).waitFor();
    report.longBrand = {
        fits: await page.locator('aside').evaluate((aside) => aside.scrollWidth <= aside.clientWidth),
    };
    check('long brand name does not overflow the sidebar', report.longBrand.fits);
    await page.goto(`${origin}/dashboard`);
```

Atualizar a verificação existente "section navigation remains active on resource URLs": esperar pelo heading `Usuários` em vez de `Visão geral`, e a classe ativa passa a ser `bg-sidebar-primary`. Atualizar o login escuro para `getByRole('textbox', { name: 'E-mail', exact: true })` e o nome do heading antigo "dark cards ..." não muda.

- [ ] **Step 5: Rodar o harness**

```powershell
npm run build
$env:DESIGN_PARITY_BROWSER_TESTS = '1'
$env:DESIGN_PARITY_NODE_BINARY = (Get-Command node).Source
$env:DESIGN_PARITY_PLAYWRIGHT_MODULE = '<caminho absoluto do index.mjs do Playwright instalado>'
php artisan test --compact tests/Unit/Frontend/DesignParityTest.php
```

Esperado: PASS com 30 ou mais verificações; capturas em `output/playwright/design-parity/`. Se o Playwright não estiver instalado na máquina, registrar que o harness não rodou e seguir para o Step 6 (não instalar dependência no produto).

- [ ] **Step 6: Verificação completa do lote**

```bash
php artisan test --compact tests/Unit/Frontend/TokenUsageTest.php tests/Feature/BrandingTest.php
php artisan test --compact
npm run types:check && npm run lint:check && npm run format:check
vendor/bin/pint --test config app/Services app/Http/Middleware tests/Feature/BrandingTest.php tests/Unit/Frontend
"C:/Users/GABRIELGOBBI/.claude/skills/impeccable/scripts/impeccable" detect --json resources/js
```

Esperado: suíte verde (relatar falhas preexistentes sem corrigi-las aqui); detector sem achados novos nos arquivos do lote (os três de timeline, tools e slack permanecem, fora do lote).

- [ ] **Step 7: Inspeção manual no navegador**

`preview_start` com `worktree-serve`, login com a conta de seed, nos dois temas, 1440x900 e 375x812: dashboard, usuários (hover de linha, foco por Tab no excluir), novo usuário (erro de validação com campo vazio), login, configurações, notificações vazias, e-mails (Select), sidebar recolhida e Sheet mobile com Escape. Capturar e enviar ao chat as quatro telas equivalentes às capturas "antes" da auditoria.

- [ ] **Step 8: Commit**

```bash
git add tests/Fixtures/Frontend/design-parity-browser.mjs tests/Unit/Frontend/DesignParityTest.php
git commit -m "test(design): harness audita contraste no claro, usuarios, formulario, settings e marca longa"
```

---

### Task 16: Documentação do contrato

**Files:**
- Create: `docs/design/bionexis-contract-2026-10-20.md` (conteúdo atual do `DESIGN.md`)
- Modify: `DESIGN.md` (reescrever), `docs/design/dark-mode.md` (nota de supersessão no topo), `docs/design/bionexis-parity.md` (idem)
- Create: `PRODUCT.md`

- [ ] **Step 1: Arquivar o contrato antigo**

```bash
git mv DESIGN.md docs/design/bionexis-contract-2026-10-20.md
```

Inserir no topo do arquivo movido: `> Histórico. Substituído em 22/10/2026 pelo contrato em [DESIGN.md](../../DESIGN.md).`

- [ ] **Step 2: Novo `DESIGN.md`**

Estrutura obrigatória (uma seção por item, valores copiados da spec, sem travessão):

1. `# Start Kit: sistema visual tematizável` e parágrafo de propósito (identidade própria, kit tematizável por `config/branding.php`, shell preservado).
2. `## Marca`: `BrandMark`, `BrandWordmark`, `AppLogo`, variáveis `BRAND_*`, fallbacks, favicon em `public/brand/`.
3. `## Estrutura da aplicação`: copiar a seção homônima do contrato antigo (medidas do shell continuam válidas), trocando "Módulo selecionado: cápsula cinza clara" por `secondary` + `foreground` e "avatar teal" por `primary-soft`.
4. `## Cores`: tabelas 2.1 e 2.2 da spec e as regras 2.3.
5. `## Tipografia`: seção 4 da spec.
6. `## Componentes`: tabela da seção 5 da spec.
7. `## Dashboard compacto`: itens 1 a 7 do contrato antigo, com cores trocadas por tokens (chips `*-soft`, barra `chart-*`).
8. `## Responsividade, acesso e estados`: copiar do contrato antigo e acrescentar alvos de toque 44px e `forced-colors`.
9. `## Validação`: harness, `TokenUsageTest`, detector, inspeção manual, limites.

- [ ] **Step 3: `PRODUCT.md`**

```markdown
# Start Kit

**O que é:** kit inicial Laravel 13 + Inertia 3 + React 19 para derivar aplicações
administrativas (CRUD, ACL, exports, notificações, uploads, timeline).

**Para quem:** times que criam um produto novo a partir do kit e precisam trocar
marca, acento e logo sem reescrever telas.

**Modo das superfícies:** Operate (painel, listagens, formulários, configurações).
Não há superfícies Persuade neste repositório; a página `welcome` é placeholder.

**Verdade do produto:** navegação e permissões vêm do servidor; o frontend nunca
deduz privilégios. UUIDs, idempotência e exports assíncronos são contratos.

**Restrições:** componentes do sistema antes de controle nativo; sem travessão;
pt-BR na interface; contraste AA em todo texto; um acento por tela.

**Fora de escopo visual:** dev tools, geradores, welcome, timeline e uploads
seguem para o lote 2 de identidade visual.
```

- [ ] **Step 4: Notas de supersessão**

No topo de `docs/design/dark-mode.md` e `docs/design/bionexis-parity.md`: `> Histórico (20 a 21/10/2026). Tokens e composição atuais estão em [DESIGN.md](../../DESIGN.md); diagnóstico que motivou a troca em [visual-identity-audit.md](visual-identity-audit.md).`

- [ ] **Step 4b: Documentação interna que cita o visual antigo**

```bash
grep -rln "molecule\|runey\|brand-teal\|table-background\|primary-dark\|logo-icon" docs/.start_kit.obs docs/design README.md 2>/dev/null
```

Em cada arquivo listado, trocar a referência pelo equivalente novo (`BrandMark`, `--primary`, `bg-muted` no cabeçalho de tabela) ou marcar o trecho como histórico com a data. Não reescrever documentos inteiros.

- [ ] **Step 5: Verificar links e travessões, commitar**

```bash
grep -rnP "\x{2014}" DESIGN.md PRODUCT.md docs/design/*.md docs/superpowers/**/*.md && echo "TRAVESSAO" || echo "ok"
grep -oE "\]\([^)]+\)" DESIGN.md PRODUCT.md docs/design/*.md | sed -E 's/.*\]\((.*)\)/\1/' | grep -v "^http" | sort -u
git add DESIGN.md PRODUCT.md docs/design
git commit -m "docs(design): novo contrato visual tematizavel, PRODUCT.md e historico Bionexis"
```

Esperado: "ok"; todos os caminhos listados existem (conferir com `ls`).

- [ ] **Step 6: Relatório final**

Resumir no chat: commits da branch, verificações executadas com resultado (PHPUnit, tsc, ESLint, Prettier, Pint, detector, harness ou motivo de não ter rodado), capturas "depois", e limites (lote 2 pendente, nada commitado em main, sem push).
