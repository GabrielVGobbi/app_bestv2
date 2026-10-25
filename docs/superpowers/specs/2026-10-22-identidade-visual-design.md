# Identidade visual do Start Kit: spec de design

Data: 22/10/2026. Branch: `claude/platform-visual-identity-1bb740`.
Diagnóstico de origem: [docs/design/visual-identity-audit.md](../../design/visual-identity-audit.md).

## 1. Objetivo e decisões

Dar ao Start Kit uma identidade visual própria, coerente e acessível, que cada
projeto derivado possa tematizar por configuração, preservando a composição do
shell já validada (sidebar 240/80px, cabeçalho 64px, dashboard compacto, DataTable).

Decisões aprovadas em 22/10/2026:

| Tema | Decisão |
| --- | --- |
| Direção | Identidade própria, shell mantido |
| Marca | Kit tematizável (identidade padrão neutra, trocável por config) |
| Idioma | Interface em pt-BR em tudo que for tocado |
| Escopo do lote 1 | Fundação + telas centrais |
| Abordagem | Token-first com camada semântica e configuração de marca |
| Acento padrão | Petróleo, `--brand-hue: 215` |
| Sidebar no claro | Âncora escura; cabeçalho de tabela deixa de ser escuro |

O que não muda: módulos, rotas, dados, permissões, UUIDs, idempotência,
notificações, exports, fluxos de negócio, variantes `sidebar-logo`/`header-logo`
e estilos de navegação `modules`/`flat`.

## 2. Arquitetura dos tokens

Tudo vive em `resources/css/app.css`. Dois níveis na prática:

1. **Parâmetros de marca e fórmulas.** `--brand-hue` (0 a 360, padrão 215) definido
   em `:root` e sobrescrito inline no `<html>` pelo Blade a partir de
   `config('branding.hue')`. Neutros e acento são `oklch(L C var(--brand-hue))`;
   status (sucesso, aviso, perigo, info) têm hue fixo e não seguem a marca.
2. **Tokens semânticos.** Mantêm os nomes shadcn já usados em 45 mil linhas
   (`background`, `card`, `popover`, `muted`, `secondary`, `accent`, `primary`,
   `destructive`, `success`, `warning`, `border`, `input`, `ring`, `sidebar-*`),
   com valores novos, mais os papéis que faltavam. Renomear tudo seria churn
   sem ganho; adicionar só o que falta é a menor mudança completa.

O `@theme` do Tailwind continua mapeando `--color-x: var(--x)`. Nada de paleta
Tailwind fixa (`slate-500`, `emerald-400`, `amber-50`) nos arquivos do lote.

### 2.1 Valores verificados (modo claro)

Contraste medido por luminância relativa WCAG, cores compostas em sRGB.

| Token | Valor | Hex aprox. | Uso |
| --- | --- | --- | --- |
| `--background` | `oklch(0.975 0.004 H)` | #f4f7f8 | canvas |
| `--card` | `oklch(1 0 0)` | #ffffff | superfície |
| `--popover` | `oklch(1 0 0)` | #ffffff | elevado |
| `--muted` | `oklch(0.955 0.005 H)` | #ecf1f2 | rebaixado, cabeçalho de tabela, chips |
| `--secondary` | `oklch(0.93 0.008 H)` | | botão secundário, filtro selecionado |
| `--accent` | `oklch(0.91 0.01 H)` | | hover de linhas e menus (sentido shadcn) |
| `--foreground` | `oklch(0.22 0.02 H)` | #0f1d20 | texto, 17.3:1 sobre card |
| `--muted-foreground` | `oklch(0.46 0.02 H)` | #4c5b5f | texto secundário, 7.1:1 sobre card, 6.2:1 sobre muted, 6.2:1 sobre primary-soft |
| `--border` | `oklch(0.90 0.008 H)` | #d8dfe1 | bordas decorativas |
| `--separator` | `oklch(0.93 0.006 H)` | | linhas internas |
| `--input` | `oklch(0.58 0.02 H)` | #6d7e82 | borda de campo, 4.2:1 sobre card |
| `--input-surface` | `var(--card)` | | fundo de campo |
| `--primary` | `oklch(0.48 0.11 H)` | #006c84 | ação, foco, marca; branco sobre ele 6.0:1 |
| `--primary-hover` | `oklch(0.43 0.11 H)` | #005d75 | hover/active do primário |
| `--primary-foreground` | `oklch(1 0 0)` | #ffffff | |
| `--primary-soft` | `oklch(0.95 0.03 H)` | #d9f4fb | avatar, chip de ícone, badge do acento |
| `--primary-soft-foreground` | `oklch(0.38 0.10 H)` | #004e63 | 8.1:1 sobre primary-soft |
| `--ring` | `var(--primary)` | | anel de foco |
| `--success` | `oklch(0.46 0.12 150)` | #0f6a31 | branco sobre ele 6.7:1 |
| `--success-soft` / `-foreground` | `oklch(0.95 0.035 150)` / `oklch(0.36 0.10 150)` | #dff6e2 / #004b1e | 9.1:1 |
| `--warning` / `--warning-foreground` | `oklch(0.80 0.15 75)` / `oklch(0.28 0.06 60)` | #f5ae39 / #3e2104 | 7.7:1 (texto escuro sobre aviso) |
| `--warning-soft` / `-foreground` | `oklch(0.96 0.04 80)` / `oklch(0.40 0.09 60)` | #fff0d4 / #6a3a06 | 8.4:1 |
| `--destructive` | `oklch(0.48 0.17 25)` | #a92227 | branco sobre ele 7.1:1 |
| `--destructive-soft` / `-foreground` | `oklch(0.95 0.03 20)` / `oklch(0.38 0.14 25)` | #ffe7e6 / #7c1117 | 9.1:1 |
| `--info` | `oklch(0.48 0.10 245)` | #236292 | branco sobre ele 6.5:1 |
| `--info-soft` / `-foreground` | `oklch(0.95 0.025 245)` / `oklch(0.38 0.10 245)` | #e1f1ff / #004673 | 8.6:1 |
| `--sidebar` | `oklch(0.24 0.03 H)` | #0c2328 | âncora escura |
| `--sidebar-foreground` | `oklch(0.88 0.01 H)` | #d0d9dc | 11.4:1 |
| `--sidebar-muted-foreground` | `oklch(0.70 0.02 H)` | #91a2a6 | rótulos de seção, e-mail, 6.2:1 |
| `--sidebar-accent` | `oklch(0.29 0.03 H)` | #192f35 | hover |
| `--sidebar-primary` | `oklch(0.93 0.008 H)` | #e2e9eb | cápsula do item ativo |
| `--sidebar-primary-foreground` | `var(--foreground)` | | 14:1 sobre a cápsula |
| `--sidebar-border` | `oklch(0.29 0.02 H)` | | |
| `--header-border` | `transparent` | | |
| `--chart-1..5` | primary, `oklch(0.60 0.02 H)`, info, success, warning | | distribuição e gráficos |

Removidos: `--primary-dark`, `--primary-light`, `--brand-teal`, `--brand-blue`,
`--orange`, `--color-runey-orange`, `--table-background`, `--table-text`.
`--primary-dark` fica como alias de `--primary-hover` até o último consumidor
migrar e some no fim do lote.

### 2.2 Valores verificados (modo escuro)

| Token | Valor | Hex aprox. | Contraste |
| --- | --- | --- | --- |
| `--background` | `oklch(0.19 0.01 H)` | #0f1517 | |
| `--card` | `oklch(0.23 0.01 H)` | #181e20 | mais claro que o canvas |
| `--popover` | `oklch(0.27 0.012 H)` | #20282a | |
| `--muted` | `oklch(0.21 0.01 H)` | #131a1b | |
| `--secondary` | `oklch(0.31 0.015 H)` | #283235 | |
| `--accent` | `oklch(0.30 0.012 H)` | | hover |
| `--foreground` | `oklch(0.94 0.005 H)` | #e8eced | 14.2:1 sobre card |
| `--muted-foreground` | `oklch(0.72 0.015 H)` | | 6.8:1 sobre card, 5.3:1 sobre primary-soft |
| `--border` | `oklch(0.32 0.012 H)` | #2c3537 | |
| `--separator` | `oklch(0.28 0.01 H)` | | |
| `--input` | `oklch(0.52 0.02 H)` | #5c6c70 | 3.3:1 sobre o campo |
| `--input-surface` | `oklch(0.20 0.01 H)` | #111719 | |
| `--primary` / `-foreground` | `oklch(0.78 0.10 H)` / `oklch(0.20 0.04 H)` | #62c8df / #001a21 | 9.3:1; 8.7:1 sobre card |
| `--primary-hover` | `oklch(0.83 0.09 H)` | | |
| `--primary-soft` / `-foreground` | `oklch(0.30 0.045 H)` / `oklch(0.85 0.08 H)` | #0c333c / #8fdcef | 8.8:1 |
| `--success` / `-foreground` | `oklch(0.80 0.14 150)` / `oklch(0.22 0.06 150)` | #75d78d / #00220a | 9.6:1 |
| `--success-soft` / `-foreground` | `oklch(0.30 0.05 150)` / `oklch(0.85 0.12 150)` | #1a3520 / #92e4a4 | 8.8:1 |
| `--warning` / `-foreground` | `oklch(0.85 0.14 80)` / `oklch(0.25 0.06 70)` | #fdc357 / #331b00 | 10.1:1 |
| `--warning-soft` / `-foreground` | `oklch(0.31 0.05 75)` / `oklch(0.88 0.12 85)` | #3f2c10 / #fcd176 | 9.2:1 |
| `--destructive` / `-foreground` | `oklch(0.75 0.14 20)` / `oklch(0.22 0.08 20)` | #fa8789 / #37040a | 7.5:1 |
| `--destructive-soft` / `-foreground` | `oklch(0.30 0.06 20)` / `oklch(0.86 0.10 20)` | #472021 / #ffb7b6 | 8.5:1 |
| `--info` / `-foreground` | `oklch(0.78 0.10 245)` / `oklch(0.20 0.05 245)` | #7fbef3 / #00182b | 9.1:1 |
| `--info-soft` / `-foreground` | `oklch(0.30 0.05 245)` / `oklch(0.86 0.08 245)` | #163045 / #a5d7ff | 8.9:1 |
| `--sidebar` / `-foreground` | `oklch(0.21 0.012 H)` / `oklch(0.86 0.01 H)` | #121a1c / #cad3d5 | 11.6:1 |
| `--sidebar-muted-foreground` | `oklch(0.68 0.015 H)` | #8e9b9e | 6.2:1 |
| `--sidebar-accent` | `oklch(0.27 0.012 H)` | | hover |
| `--sidebar-primary` / `-foreground` | `oklch(0.31 0.015 H)` / `var(--foreground)` | #283235 | 11:1 |
| `--header-border` | `var(--separator)` | | |
| `--chart-1..5` | primary, `oklch(0.65 0.02 H)`, info, success, warning | | |

### 2.3 Regras de uso

- **Um acento por tela.** `primary` para ação principal, foco e marca. Status só
  para status. Nunca acento em avatar, barra de distribuição ou chip decorativo:
  esses usam `primary-soft` com `primary-soft-foreground`.
- **Dois níveis de texto.** `foreground` e `muted-foreground`. A hierarquia
  secundária vem de tamanho e peso, não de um terceiro cinza (um terceiro cinza
  não passa 4.5:1 sobre superfícies tingidas).
- **Branco sobre acento só no claro.** No escuro, `primary-foreground` é escuro.
  Badges e avatares nunca usam `text-white`.
- **Hierarquia pelos neutros.** `card` sobre `background`; `muted` para áreas
  rebaixadas (cabeçalho de tabela, chips, trilhos); `popover` para flutuantes.
- **Bordas por papel.** `border` decorativa; `separator` interna; `input` para
  campos (3:1 garantido).
- **Proibido** `text-[10px]`, `text-white` sobre tinta suave, classes de paleta
  Tailwind fixas, `opacity` em texto para criar hierarquia.

## 3. Marca tematizável

### 3.1 `config/branding.php`

```php
return [
    'name' => env('BRAND_NAME'),               // null usa config('app.name') no serviço
    'short_name' => env('BRAND_SHORT_NAME', 'SK'),
    'hue' => (int) env('BRAND_HUE', 215),
    'logo' => [
        'mark' => env('BRAND_LOGO_MARK'),        // SVG opcional; null usa o símbolo do kit
        'light' => env('BRAND_LOGO_LIGHT'),      // logo completa para fundo claro
        'dark' => env('BRAND_LOGO_DARK'),        // logo completa para fundo escuro
    ],
];
```

- `app/Services/BrandingService.php` resolve o config: `name` nulo usa
  `config('app.name')`; `hue` fora de 0 a 360 cai em 215; `short_name` com mais
  de 3 caracteres é cortado; caminhos de logo inexistentes caem no símbolo do kit.
  `config()` não é chamado dentro do arquivo de config, para manter `config:cache` seguro.
- `HandleInertiaRequests` compartilha `branding` (`name`, `shortName`, `hue`,
  `logo`), substituindo o prop solto `name`.
- `app.blade.php`: `<html style="--brand-hue: {{ hue }}">`, `<title>` e favicon
  a partir do config; boot shell usa o mesmo símbolo e as mesmas fórmulas oklch
  do canvas e da sidebar, sem Poppins, sem `#0d0d0d`, sem "Runey".

### 3.2 Identidade padrão do kit

- **Símbolo:** dois quadrados arredondados sobrepostos em deslocamento
  diagonal (blocos de um kit), SVG 24x24 monocromático em `currentColor`, o de
  trás a 55% de opacidade. Componente `components/brand/brand-mark.tsx`; o mesmo
  desenho em `public/brand/mark.svg` (favicon e boot shell) e
  `public/brand/mark-dark.svg` quando o projeto fornecer versão própria.
- **Wordmark:** `components/brand/brand-wordmark.tsx` renderiza `branding.name`
  em Geist 700, 15px, `tracking-tight`, cor herdada. Trunca a 160px com `title`.
- **`AppLogo`** passa a compor símbolo + wordmark e aceita a logo de arquivo
  quando configurada. `AppLogoIcon` some; os dez consumidores
  (`dashboard-sidebar`, `dashboard-header`, `app-header`, `app-sidebar`,
  três layouts de auth, `error.tsx`) usam `AppLogo` ou `BrandMark`.
- **Saem:** `/src/imagens/logo-icon-*.svg`, `DEFAULT_LOGO_ALT = 'Best Play'`,
  `favicon.svg` do Laravel, `apple-touch-icon.png` genérico (regerado do símbolo),
  moléculas em `public/src/assets/imagens/molecule/` e `molecule-background.tsx`.
- **Canvas:** sem imagem e sem gradiente. A hierarquia vem das superfícies
  (`background`, `card`, `muted`). Qualquer textura de fundo fica para um
  pedido futuro.

## 4. Tipografia

- Geist e Geist Mono locais, sem mudança.
- Escala em `@theme`: `--text-2xs: 0.6875rem` (11px) novo; demais já existem.
  Uso: 11px só em legendas de tabela densa e metadados; 12px descrições;
  13px sidebar, módulos e corpo de tabela; 14px corpo; 16px lead;
  20px seção (h2); 24px título de página; 30px KPI. Pesos 400, 500, 600, 700.
- `font-variant-numeric: tabular-nums` em KPIs, contadores, colunas numéricas e
  datas (utilitário `tabular-nums` nas células e nos valores).
- Rótulos de seção e de cabeçalho de tabela: 11px/600, uppercase,
  `tracking-wide`, `muted-foreground`. No cabeçalho da DataTable: 12px/600,
  sentence case, sem uppercase.
- `components/page-header.tsx`: título 24px/700 `tracking-tight`, descrição
  14px `muted-foreground`, área de ações à direita, empilha abaixo de `md`.
  Substitui os cabeçalhos ad hoc de dashboard, usuários, papéis, permissões,
  clientes, notificações, exportações, activity log, e-mails e `Heading`
  (que fica só para seções internas: 16px/600 + descrição 13px).
- `text-wrap: balance` em títulos; `text-wrap: pretty` em descrições.

## 5. Componentes e padrões

| Componente | Regra |
| --- | --- |
| Button | `default` = `primary` sólido, hover `primary-hover`, texto `primary-foreground`. `destructive` sólido só em diálogos de confirmação. `outline` = `card` + `input`. `secondary` = `secondary`. `ghost` neutro. Altura 36px; `size="icon"` 36px com área de toque 44px no mobile via `before:` invisível. Anel de foco 2px `ring` com offset 2px. Press: `active:translate-y-px`. |
| Badge | Só tintas suaves: `primary-soft`, `success-soft`, `warning-soft`, `destructive-soft`, `info-soft`, `secondary`; texto do respectivo `-foreground`. Variante `outline` mantida. Sem `text-white`. |
| Card | `card`, `border`, raio 16px, `shadow-xs` no claro e sem sombra no escuro. Sem tinta no corpo; cor só no chip de ícone (`*-soft`, 28 a 36px). |
| Input, Select, Textarea | Altura 40px, `input-surface`, borda `input`, foco `ring`. Label 13px/500. Erro: texto 12px `destructive-soft-foreground` com ícone, `aria-invalid`. Obrigatório: asterisco em `destructive`. |
| DataTable (shell) | `thead` em `muted` com `muted-foreground` 12px/600; linhas 13px; hover `accent`; separador `separator`; ações de linha em ícones `ghost` 32px (44px de toque) neutros, excluir só fica `destructive` em hover e foco; mais de três ações viram menu. Nenhuma mudança em hooks, estado, filtros ou persistência. |
| Alert | `default` = `muted`; `destructive` = `destructive-soft`; novas `success`, `warning`, `info` com as tintas suaves. Ícone à esquerda em `*-soft-foreground`. |
| Toaster (sonner) | `classNames` mapeados aos tokens suaves; remover emerald, red, amber, sky. |
| Avatar | fallback `primary-soft` + `primary-soft-foreground` em todos os lugares (header, sidebar, menus, listas). |
| Empty state | `components/empty-state.tsx`: chip de ícone 40px `muted`, título 14px/600, descrição 13px `muted-foreground`, ação opcional. Usado por notificações, exportações, e-mails e DataTable. |
| Segmented control do dashboard | selecionado = `secondary` + `foreground`; nunca preto nem acento sólido. |
| Barra de distribuição | `chart-1..5` por módulo, ordem estável. |
| Sidebar | mantém medidas; item ativo `sidebar-primary` + `sidebar-primary-foreground`; hover `sidebar-accent`; seção `sidebar-muted-foreground`; conta no rodapé com `sidebar-accent` e borda `sidebar-border`, nome em `sidebar-foreground` e e-mail em `sidebar-muted-foreground` (sem `white/70`). |
| Header | botões de ícone `ghost` 36px; módulo ativo `secondary` + `foreground`; avatar como acima. |
| Theme toggle | mantido; ícones `muted-foreground`. |

## 6. Telas do lote 1 e copy

Todas as telas abaixo migram para tokens, `PageHeader`, padrões acima e copy
pt-BR. Strings em inglês deixam de existir nelas.

- Shell: `app-dashboard-layout`, `dashboard-header`, `dashboard-sidebar`,
  `nav-module-items`, `nav-module-switcher`, `user-menu-content` ("Configurações",
  "Sair"), `notification-bell`, `theme-toggle`, `breadcrumbs`.
- Dashboard (`pages/dashboard.tsx`): composição mantida; 72 classes fixas viram
  tokens; cards sem tinta; "Verificada" vira texto com chip `success-soft`.
- Auth: `auth-card-layout` (único layout em uso; `simple` e `split` recebem
  tokens e logo mas não copy), login, registro, esqueci senha, redefinir senha,
  confirmar senha, verificação de e-mail, 2FA, troca forçada de senha.
  Copy: "Entrar na sua conta", "E-mail", "Senha", "Lembrar de mim", "Entrar",
  "Esqueceu a senha?", "Não tem conta? Cadastre-se".
- Settings: layout de settings, perfil, segurança, aparência, `delete-user`,
  `appearance-tabs` ("Claro", "Escuro", "Sistema"), `heading`.
  Copy: "Configurações", "Perfil", "Segurança", "Aparência", "Salvar",
  "Excluir conta", "Atenção", "Esta ação não pode ser desfeita".
- CRUD: usuários (index, create, edit, show, roles, permissions, partials),
  papéis (index, edit, show, permissions), permissões (index, edit, show),
  clientes (index, create, edit, show, partials). Copy: "Usuários",
  "Gerenciar contas de usuário", "Novo usuário", "Criar usuário", "Cancelar",
  "Nome", "E-mail", "Primeiro acesso com senha temporária" e equivalentes.
- Listas: notificações, exportações, activity log, e-mails (os dois `<select>`
  viram o `Select` de `components/ui/select.tsx`).
- Estados: `pages/error.tsx`, `temporary-password-dialog`, `delete-confirm-modal`.

Fora do lote 1 (lote 2): `admin/dev/*` (dev tools, componentes, geradores),
`welcome.tsx`, `modules/timeline`, `modules/uploads`, `admin/backup`,
`admin/slack`, `external-api/*`, `rich-text-editor`, `async-select`, `data-table`
além do shell (bulk actions bar, export dialog, filtros avançados). Eles herdam
os tokens pelos primitivos; suas classes fixas ficam para o lote 2.

## 7. Documentação

- `DESIGN.md` reescrito como contrato da nova identidade (estrutura do atual,
  valores desta spec, regras da seção 2.3, inventário de componentes). O texto
  atual vai para `docs/design/bionexis-contract-2026-10-20.md` como histórico.
- `PRODUCT.md` curto (Impeccable `init`): o que é o kit, para quem (times que
  derivam aplicações Laravel/Inertia), modo Operate, restrições de autoridade.
- `/admin/dev/components/design-system`: seção "Cores" lista os tokens
  semânticos e as tintas suaves; seção "Tipografia" mostra a escala da seção 4.
- `docs/.start_kit.obs/Navigation/*` e `docs/design/*` atualizados onde citarem
  tokens removidos ou as moléculas.
- `.env.example` ganha `BRAND_HUE=215` comentado.

## 8. Validação

- **PHPUnit:** `tests/Feature/BrandingConfigTest.php` (hue fora de faixa cai em
  215, short_name cortado, logo ausente cai no símbolo, prop `branding`
  compartilhado); `tests/Unit/Frontend/TokenUsageTest.php` (nenhum arquivo do
  lote 1 contém classes de paleta fixa, `text-[10px]`, `runey`, `Poppins`,
  `Best Play`, `molecule`).
- **Harness Playwright** (`tests/Unit/Frontend/DesignParityTest.php` +
  `tests/Fixtures/Frontend/design-parity-browser.mjs`): rotas ampliadas para
  dashboard, usuários (lista), novo usuário, login, settings/perfil e
  notificações vazias, nos dois temas; auditoria de contraste exige 4.5:1 em
  todo texto e 3:1 em bordas de campo; fonte renderizada Geist; sem overflow;
  sem `/src/imagens/logo-icon`; capturas em `output/playwright/design-parity/`.
  As assertions de medidas do shell permanecem.
- **Detector:** `impeccable detect --json resources/js` sem novos achados nos
  arquivos do lote (os três atuais ficam para o lote 2).
- **Build e lint:** `npm run build`, `npx tsc --noEmit`, ESLint e Prettier nos
  arquivos tocados, Pint nos PHP tocados.
- **Inspeção manual:** worktree servido em `127.0.0.1:8000` (host na lista
  stateful do Sanctum), temas claro e escuro, 1440x900 e 375x812, teclado (Tab,
  Escape no Sheet), hover de linhas, toasts e diálogos. Capturas "depois"
  enviadas ao chat ao lado das "antes".

Build e TypeScript não provam paridade; o relatório final diz o que foi
inspecionado e o que não foi.

## 9. Casos de borda e erros

- Tema: boot shell e `html` usam as mesmas fórmulas oklch e `color-scheme`;
  sem flash branco no escuro. `localStorage` indisponível continua permitindo
  trocar tema na sessão (comportamento atual preservado).
- `prefers-reduced-motion`: transições de largura e de ícones param; sem fundo
  animado para desligar.
- Nome de marca longo: wordmark trunca com reticências e `title`; no
  recolhido mostra só o símbolo.
- Logo de arquivo configurada mas inexistente: `AppLogo` cai no símbolo e
  registra `Log::warning` uma vez por request.
- `hue` inválido ou ausente: 215.
- Variante `header-logo` e navegação `flat`: recebem os mesmos tokens; o
  harness já cobre as variantes.
- Alto contraste do SO (`forced-colors`): bordas de `secondary`/`muted` ganham
  `outline` transparente para manter separação.

## 10. Riscos

- Migração de ~350 classes fixas nos arquivos do lote 1: fazer por tela, com
  `TokenUsageTest` como catraca, nunca em massa cega.
- O checkout principal tem alterações não commitadas na DataTable (shell e
  hooks). Esta spec muda só classes do shell (`data-table-header`, `-body`,
  `-toolbar`, `-shell`) e nada em hooks; ainda assim, conflito é possível e
  deve ser resolvido mantendo a lógica do checkout principal.
- O harness Playwright depende de um caminho local do Playwright; a validação
  relata se não rodou.
- Trocar `name` por `branding.name` no prop compartilhado toca `AppLogo` e
  `welcome.tsx`; manter `name` como alias até o lote 2.
