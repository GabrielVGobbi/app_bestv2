# Start Kit: sistema visual tematizável

Este é o contrato visual canônico do projeto. O Start Kit tem identidade própria,
neutra e acessível, e cada projeto derivado troca nome, acento e logo por
`config/branding.php` sem reescrever telas. A composição do shell validada em
20/10/2026 continua valendo (sidebar 240/80px, cabeçalho 64px, dashboard
compacto, DataTable); só a pele mudou. Os componentes interativos (sidebar, tooltip,
dropdown, select e abas) são do Fluid Functionalism, vendorizados em
`resources/js/components/ui/fluid/` desde 23/10/2026. Spec de origem:
[2026-10-22-identidade-visual-design.md](docs/superpowers/specs/2026-10-22-identidade-visual-design.md).
Diagnóstico que motivou a troca: [visual-identity-audit.md](docs/design/visual-identity-audit.md).
Contrato anterior (Bionexis), como histórico:
[bionexis-contract-2026-10-20.md](docs/design/bionexis-contract-2026-10-20.md).

## Marca

- `config/branding.php` lê `BRAND_NAME`, `BRAND_SHORT_NAME`, `BRAND_HUE`,
  `BRAND_LOGO_MARK`, `BRAND_LOGO_LIGHT` e `BRAND_LOGO_DARK` (exemplo comentado em
  `.env.example`).
- `App\Services\BrandingService` resolve os fallbacks: nome vazio usa
  `config('app.name')`; sigla em maiúsculas, até 3 caracteres; `hue` não numérico
  ou fora de 0 a 360 cai em **215** (petróleo); logo apontando para arquivo
  inexistente em `public/` cai no símbolo do kit e registra `Log::warning`.
- `HandleInertiaRequests` compartilha `branding` (`name`, `shortName`, `hue`,
  `logo.mark|light|dark`). O prop `name` continua como alias até o lote 2.
- `resources/views/app.blade.php` escreve `style="--brand-hue: N"` no `<html>`,
  usa `branding.name` no `<title>` e no boot shell, e aponta o favicon para
  `public/brand/favicon.svg` (com `favicon.ico` e `apple-touch-icon.png` gerados do
  mesmo símbolo).
- `components/brand/brand-mark.tsx` (`BrandMark`): dois blocos arredondados
  sobrepostos, SVG 24x24 em `currentColor`, o de trás a 55% de opacidade.
- `components/brand/brand-wordmark.tsx` (`BrandWordmark`): `branding.name` em Geist
  700, 15px, `tracking-tight`; trunca em 160px com `title`.
- `components/app-logo.tsx` (`AppLogo`) compõe símbolo + nome; com logo de arquivo
  configurada usa a imagem (versões claro/escuro) com 32px de altura,
  `object-contain` e largura limitada ao espaço, sem deformar. Sidebar recolhida
  mostra só o símbolo, centralizado.
- Caminhos de logo são relativos a `public/`; segmentos `..` são recusados e caem
  no símbolo do kit, como arquivo ausente.
- Não existem mais `AppLogoIcon`, o "R" da Runey, o alt "Best Play", as moléculas
  Bionexis nem o token `runey-orange`. O canvas não tem imagem nem gradiente.

## Estrutura da aplicação

- Laravel 13, Inertia 3, React 19 e Tailwind 4; usar os componentes instalados.
- Entrada: `resources/js/app.tsx`; layout persistente:
  `resources/js/layouts/app/app-dashboard-layout.tsx`.
- Sidebar à esquerda, cabeçalho acima da coluna de conteúdo e scroll apenas na
  área principal. Sem scroll horizontal da página.
- Variante padrão `sidebar-logo`: logo no topo da sidebar; cabeçalho **64px**.
  A sidebar é o Sidebar do Fluid Functionalism (seção "Sidebar Fluid"): **240px**
  por padrão, redimensionável, e faixa de ícones de **80px** recolhida. Logo sem
  cápsula de fundo em volta do link.
- Variante `header-logo` e navegação `flat` continuam suportadas e recebem os
  mesmos tokens.
- Cabeçalho: padding horizontal **20px**; `SidebarTrigger` do Fluid (tooltip com o
  atalho `[`), divisor, breadcrumb, módulos sem cápsula externa, notificações,
  tema e conta.
- Módulo selecionado no cabeçalho (o mesmo cujos itens a sidebar mostra): pílula
  elevada do Fluid (`surface-3` com `shadow-surface-3`), texto `foreground` e só o
  ícone em `--module-active` (= `primary-soft-foreground`), com
  `aria-current="page"`; a pílula desliza entre módulos em `spring.moderate`.
  Inativos em `muted-foreground`, hover na pílula `hover`. Nunca um botão de
  acento preenchido como substituto desse estado.
- Com módulos no cabeçalho, mostrar os itens diretamente na sidebar.
- Conta fixa no rodapé: linha do Fluid com avatar `primary-soft` de **20px**, nome
  e chevron; o menu da conta abre para cima, na largura da linha, e inclui
  "Restaurar sidebar".
- Página: `.page-wrapper`, máximo **1600px**, centralizada; padding horizontal
  **16px** no mobile e **24px** a partir de `md`; padding superior **20px**.
  `page-wrapper-lg`/`-xl` usam a mesma largura, e o `Header` das telas de módulo
  limita o título e as ações a ela: título, botão principal, toolbar e tabela
  começam e terminam na mesma linha. `page-wrapper-sm`/`-md` (formulários)
  mantêm a largura estreita, mas começam na margem do título. Não somar outro
  padding horizontal (`p-4`) dentro do wrapper.
- Cabeçalho de página: um só visual, `components/page-header.tsx` (`PageHeader`),
  com voltar opcional (`backHref`), título, descrição, linha discreta (`eyebrow`) e
  ações à direita; empilha abaixo de `md`. `components/dashboard/header.tsx`
  (`Header`: `title`, `subtitle`, `backHref`, `children`) é a faixa fixa no topo das
  telas de módulo e só envolve o `PageHeader`. `Heading` fica para seções internas.

## Sidebar Fluid

A solicitação de 23/10/2026 aplica o Sidebar do
[Fluid Functionalism](https://www.fluidfunctionalism.com/docs/sidebar) ao shell:
a fluidez do Fluid (realce deslizante, springs, rail de resize, seções
recolhíveis) nas cores da sidebar do Start Kit. O código está vendorizado em
`resources/js/components/ui/fluid/` (MIT; origem, commit e patches locais no
README dessa pasta). A composição fica em `components/dashboard/dashboard-sidebar.tsx`.

- Coluna encostada na borda (variante `sidebar`), fundo `--sidebar` (petróleo escuro
  no claro, grafite no escuro, ambos pelo `--brand-hue`) e borda `--sidebar-border`.
  A classe `.app-sidebar` em `app.css` reaponta os tokens do Fluid: linhas em
  repouso em `--sidebar-foreground`; realce e ativo na pílula `--sidebar-primary`
  com texto `foreground` (no escuro, o realce usa `--sidebar-accent`).
- Largura padrão **240px**; o rail da borda redimensiona de **160** a **360px** por
  arraste e a largura fica salva no navegador (`localStorage` `sidebar:width`; valor
  ausente ou fora do limite volta ao padrão). Clique no rail ou atalho recolhe.
  "Restaurar sidebar", no menu da conta do rodapé, volta a 240px e expandida
  (`resetSidebarPreferences` em `hooks/use-sidebar-preferences.ts`).
- Recolhida é a faixa de ícones de **80px** (`collapsedWidth`): ícones centralizados,
  nome em tooltip à direita e acessível a leitores de tela, seções como separadores,
  grupos mostrando os links direto, logo em ícone e só o avatar no rodapé. Arrastar a
  borda da faixa reexpande. A preferência fica em `localStorage` (`sidebar:collapsed`).
- Linhas de **36px** (8 + 20 + 8) com **2px** entre elas, recuo de **12px** e **10px**
  entre ícone e texto; ícone **16px** (traço 1.5 → 2 no hover/ativo), texto
  **13px/20px**; ativo com `aria-current="page"`, fundo `--active` e peso 550.
- Ritmo vertical (escala de 4): **20px** do topo até a logo, **28px** da logo ao
  primeiro rótulo (cerca de 38px visuais com o respiro do rótulo de 32px),
  **24px** entre seções, **12px** entre os links do rodapé e a conta e **20px** na
  base. Laterais de **12px**. Na faixa recolhida, **8px** entre grupos e separador.
  Valores em `NavGroup`, `useRowProps` e nas classes de header/nav/footer da
  composição.
- Fluid hover: um único destaque desliza para a linha mais próxima do cursor,
  sem piscar entre itens. Cada lista (`SidebarMenu`, submenu) tem o seu.
- Seções do módulo viram `SidebarGroup` recolhível com rótulo **12px**; grupos
  (`type: group`) viram linha-pai com submenu de um nível. No modo flat, cada
  módulo é o grupo recolhível e suas seções ficam como rótulos estáticos.
- Movimento pelos springs do Fluid (`fast` 0.08s, `moderate` 0.16s, `slow` 0.24s);
  `prefers-reduced-motion` remove o deslocamento e mantém só as opacidades.
- Atalho `[` alterna a sidebar fora de campos de texto e de áreas editáveis.
- Não editar estilos dentro de `ui/fluid/`; ajustar por props e `className`.

### Componentes Fluid nas telas

Lote 2 (23/10/2026): Tooltip, Dropdown, Select e Tabs do Fluid, migrados tela por
tela em todas as telas ativas. Código novo usa `@/components/ui/fluid/*`. Os
primitivos antigos `components/ui/select`, `tabs` e `dropdown-menu` foram
removidos; `components/ui/tooltip` fica só para os layouts legados sem uso.

- Shell: tooltip do tema, menu da conta no cabeçalho e no rodapé da sidebar
  (grade `SIDEBAR_MENU_POPUP`), seletor de módulos do cabeçalho estreito como
  opções `menuitemradio` com o módulo ativo marcado.
- Dashboard: o controle segmentado é `Tabs` do Fluid; o conteúdo filtrado fica no
  `TabPanel`. Indicador elevado desliza entre segmentos; setas do teclado trocam.
- DataTable: tooltips e menu das ações de linha (variante `destructive` vira
  item destrutivo; o ícone Lucide passa como componente), célula truncada com o
  tooltip invertido do Fluid em várias linhas, filtros (`Select` com ícone),
  configurações da tabela (menu e fixação de colunas), itens por página e
  visibilidade de colunas (`Dropdown` com seleção múltipla).
- Selects dentro de diálogos que não são Fluid declaram o substrato com
  `SurfaceProvider value={3}` (o diálogo é `bg-popover`), para o popup abrir um
  nível acima e não se fundir ao diálogo.
- Páginas Dev (Dev Tools, Activity Timeline, File Attachments, geradores, e-mail,
  formulários), Slack e Notificações: selects e abas do Fluid. Abas com muitos
  itens rolam na horizontal em vez de quebrar linha.
- Design System: a seção Tooltips mostra a API do Fluid.
- Timeline e anexos: menus de ações com `MenuItem`; Excluir usa `destructive`.
- Menus: superfície `Elevated` dois níveis acima do substrato, linhas de **36px**,
  raio **8px**, fluid hover, abertura em `spring.fast`. Itens são linhas com
  `onSelect`: navegação por `router.visit`, download por `window.open`.
- Select: gatilho de **36px** (campos de texto seguem com **40px**); borda
  `--input`, a mesma dos campos de texto.

## Cores

Tudo vive em `resources/css/app.css`. Neutros e acento são
`oklch(L C var(--brand-hue))`; status (sucesso, aviso, perigo, info) têm hue fixo.
O `@theme` mapeia `--color-x: var(--x)`. Contraste medido por luminância relativa
WCAG sobre o fundo composto.

### Claro

| Token | Valor | Hex aprox. | Uso |
| --- | --- | --- | --- |
| `--background` | `oklch(0.975 0.004 H)` | #f4f7f8 | canvas |
| `--card` | `oklch(1 0 0)` | #ffffff | superfície |
| `--popover` | `oklch(1 0 0)` | #ffffff | elevado |
| `--muted` | `oklch(0.955 0.005 H)` | #ecf1f2 | rebaixado, cabeçalho de tabela, chips |
| `--secondary` | `oklch(0.93 0.008 H)` | | botão secundário, filtro selecionado |
| `--accent` | `oklch(0.91 0.01 H)` | | hover de linhas e menus |
| `--foreground` | `oklch(0.22 0.02 H)` | #0f1d20 | texto, 17.3:1 sobre card |
| `--muted-foreground` | `oklch(0.46 0.02 H)` | #4c5b5f | texto secundário, 7.1:1 sobre card |
| `--border` | `oklch(0.90 0.008 H)` | #d8dfe1 | bordas decorativas |
| `--separator` | `oklch(0.93 0.006 H)` | | linhas internas |
| `--input` | `oklch(0.66 0.02 H)` | #859699 | borda de campo, 3.1:1 sobre card (o tom mais claro que mantém 3:1) |
| `--input-surface` | `var(--card)` | | fundo de campo |
| `--primary` | `oklch(0.48 0.11 H)` | #006c84 | ação, foco, marca; branco sobre ele 6.0:1 |
| `--primary-hover` | `oklch(0.43 0.11 H)` | #005d75 | hover do primário |
| `--primary-soft` / `-foreground` | `oklch(0.95 0.03 H)` / `oklch(0.38 0.10 H)` | #d9f4fb / #004e63 | avatar, chip de ícone, badge, 8.1:1 |
| `--success` / `-soft` / `-soft-foreground` | `oklch(0.46 0.12 150)` / `oklch(0.95 0.035 150)` / `oklch(0.36 0.10 150)` | #0f6a31 / #dff6e2 / #004b1e | 9.1:1 na tinta |
| `--warning` / `-foreground` | `oklch(0.80 0.15 75)` / `oklch(0.28 0.06 60)` | #f5ae39 / #3e2104 | 7.7:1 |
| `--warning-soft` / `-foreground` | `oklch(0.96 0.04 80)` / `oklch(0.40 0.09 60)` | #fff0d4 / #6a3a06 | 8.4:1 |
| `--destructive` | `oklch(0.48 0.17 25)` | #a92227 | branco sobre ele 7.1:1 |
| `--destructive-soft` / `-foreground` | `oklch(0.95 0.03 20)` / `oklch(0.38 0.14 25)` | #ffe7e6 / #7c1117 | 9.1:1 |
| `--info` | `oklch(0.48 0.10 245)` | #236292 | branco sobre ele 6.5:1 |
| `--info-soft` / `-foreground` | `oklch(0.95 0.025 245)` / `oklch(0.38 0.10 245)` | #e1f1ff / #004673 | 8.6:1 |
| `--sidebar` / `-foreground` | `oklch(0.24 0.03 H)` / `oklch(0.88 0.01 H)` | #0c2328 / #d0d9dc | âncora escura, 11.4:1 |
| `--sidebar-muted-foreground` | `oklch(0.70 0.02 H)` | #91a2a6 | rótulos de seção, e-mail, 6.2:1 |
| `--sidebar-accent` | `oklch(0.29 0.03 H)` | #192f35 | hover |
| `--sidebar-primary` / `-foreground` | `oklch(0.93 0.008 H)` / `var(--foreground)` | #e2e9eb | cápsula do item ativo, 14:1 |
| `--header-border` | `transparent` | | |
| `--chart-1..5` | primary, `oklch(0.60 0.02 H)`, info, success, warning | | distribuição |
| `--module-active` | `var(--primary-soft-foreground)` | | ícone do módulo ativo no cabeçalho |
| `--hover` / `--active` | `rgb(0 0 0 / 0.04)` / `rgb(0 0 0 / 0.07)` | | realce e ativo do Fluid fora da sidebar |
| `--surface-1..8` | `background`, `card`, depois `popover` | | escada de elevação do Fluid, com `--shadow-1..8` |

### Escuro

| Token | Valor | Hex aprox. | Contraste |
| --- | --- | --- | --- |
| `--background` | `oklch(0.19 0.01 H)` | #0f1517 | |
| `--card` | `oklch(0.23 0.01 H)` | #181e20 | mais claro que o canvas |
| `--popover` | `oklch(0.27 0.012 H)` | #20282a | |
| `--muted` | `oklch(0.21 0.01 H)` | #131a1b | |
| `--secondary` | `oklch(0.31 0.015 H)` | #283235 | |
| `--accent` | `oklch(0.30 0.012 H)` | | hover |
| `--foreground` | `oklch(0.94 0.005 H)` | #e8eced | 14.2:1 sobre card |
| `--muted-foreground` | `oklch(0.72 0.015 H)` | | 6.8:1 sobre card |
| `--border` / `--separator` | `oklch(0.32 0.012 H)` / `oklch(0.28 0.01 H)` | #2c3537 | |
| `--input` / `--input-surface` | `oklch(0.52 0.02 H)` / `oklch(0.20 0.01 H)` | #5c6c70 / #111719 | 3.3:1 sobre o campo |
| `--primary` / `-foreground` | `oklch(0.78 0.10 H)` / `oklch(0.20 0.04 H)` | #62c8df / #001a21 | 9.3:1 |
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
| `--module-active` | `var(--primary-soft-foreground)` | | |
| `--hover` / `--active` | `rgb(255 255 255 / 0.06)` / `rgb(255 255 255 / 0.1)` | | |
| `--surface-1..8` | `background`, `card`, `popover`, depois `oklch(0.30 a 0.42, H)` | | escada grafite do Fluid |

### Regras de uso

- **Um acento por tela.** `primary` para ação principal, foco e marca. Status só
  para status. Avatar, barra de distribuição e chip decorativo usam
  `primary-soft` + `primary-soft-foreground`.
- **Dois níveis de texto.** `foreground` e `muted-foreground`. Hierarquia
  secundária vem de tamanho e peso, não de um terceiro cinza nem de `opacity`.
- **Branco sobre acento só no claro.** No escuro, `primary-foreground` é escuro.
  Badges e avatares nunca usam `text-white`.
- **Hierarquia pelos neutros.** `card` sobre `background`; `muted` para áreas
  rebaixadas (cabeçalho de tabela, chips, trilhos); `popover` para flutuantes.
- **Bordas por papel.** `border` decorativa; `separator` interna; `input` para
  campos (3:1 garantido).
- **Proibido** nos arquivos migrados: classes de paleta Tailwind fixa
  (`slate-500`, `emerald-50`...), `text-[10px]`, `text-white` sobre tinta,
  variantes `dark:` para cor (o token cobre os dois temas). A catraca
  `tests/Unit/Frontend/TokenUsageTest.php` lista os arquivos migrados.
- `color-scheme` no root e no HTML inicial; boot shell e `html.dark` usam as
  mesmas fórmulas oklch do canvas, sem flash branco no escuro. A preferência
  explícita ganha do sistema; storage indisponível não impede trocar o tema.

## Tipografia

- Geist e Geist Mono locais (`resources/fonts/`), `font-display: swap`; sem
  serviço externo de fontes. Verificar a fonte renderizada, não só o CSS.
- Escala: **11px** (`text-2xs`) legenda de tabela densa e metadados; **12px**
  descrições; **13px** sidebar, módulos e corpo de tabela; **14px** corpo;
  **16px** lead; **20px** seção (h2); **24px** título de página; **30px** KPI.
  Pesos 400, 500, 600, 700.
- `tabular-nums` em KPIs, contadores, colunas numéricas e datas.
- Rótulos de seção: 11px/600, uppercase, `tracking-wide`, `muted-foreground`.
  Cabeçalho da DataTable: 12px/600, sentence case.
- `PageHeader`: título 24px/700 `tracking-tight`, descrição 14px
  `muted-foreground`. `Heading`: 16px/600 + descrição 13px.
- `text-wrap: balance` em títulos; `text-wrap: pretty` em descrições.

## Componentes

| Componente | Regra |
| --- | --- |
| Button | `default` = `primary`, hover `primary-hover`. `destructive` sólido só em diálogos de confirmação. `outline` = `card` + borda `border` (traço claro de painel; a borda `input` fica com os campos, inclusive gatilhos de campo feitos de botão, como `AsyncSelect` e o seletor de data). `secondary` = `secondary`. `ghost` neutro. Raio `sm` (10px), o mesmo dos campos. Altura 36px, sem misturar 32px na mesma linha; `size="icon"` 36px com área de toque 44px no mobile. Foco 2px `ring` com offset 2px. Press `active:translate-y-px`. |
| Badge | Só tintas suaves: `default` (primary-soft), `secondary`, `success`, `warning`, `destructive`, `info`, `outline`. Sem `text-white`. |
| Card | `card`, `border`, raio 16px, `shadow-xs` no claro e sem sombra no escuro. Cor só no chip de ícone (`*-soft`). |
| Input, Textarea | Altura 40px, `input-surface`, borda `input`, foco `ring`. Obrigatório: asterisco em `destructive`. Erro (`InputError`): 12px `destructive-soft-foreground` com ícone decorativo; `FormField` marca o próprio campo com `aria-invalid` e `aria-describedby`; fora dele, o campo recebe `{...errorProps(field, message)}` e o `InputError` recebe o mesmo `field`. Nada de `<select>`, `<textarea>` ou `type="date"` nativos nas telas. |
| DataTable (shell) | Card com raio 10px (`sm`, o mesmo dos botões da toolbar); `thead` em `sidebar` (mesmo petróleo da navegação) com `sidebar-foreground` 12px/600 e linhas verticais em `sidebar-border`; densidade `md` com cabeçalho de 40px e linhas de 48px, `sm` com 36px e 40px; toolbar com todos os controles em 36px e borda `border`; colunas fixas repetem listra e hover da linha em fundo opaco, para o destaque ir de borda a borda; hover `accent`; seleção `primary-soft/40`; separador `separator`; ações só com ícone em `ghost` neutro, excluir fica `destructive-soft` só em hover e foco. Hooks, estado, filtros e persistência não mudam. |
| Alert | `default` = `muted`; `destructive`, `success`, `warning`, `info` com as tintas suaves. |
| Toaster | `classNames` nas tintas suaves. |
| Avatar | Fallback `primary-soft` + `primary-soft-foreground` em todo lugar. |
| Empty state | `components/empty-state.tsx` (`EmptyState`): chip 40px `muted`, título 14px/600, descrição 13px `muted-foreground`, ação opcional. |
| Segmented control | Selecionado `secondary` + `foreground`. |
| Sidebar | Fluid. Item ativo e realce na pílula `sidebar-primary` com `foreground` (realce `sidebar-accent` no escuro); linhas em `sidebar-foreground`. |
| Select, Dropdown, Tabs, Tooltip | Do Fluid (`components/ui/fluid/`); ver "Componentes Fluid nas telas". Select com gatilho de 36px e borda `input`. |
| Header | Botões de ícone `ghost` 36px; módulo ativo na pílula elevada do Fluid. |
| Catálogo | `/admin/dev/components/design-system` mostra tokens, tintas suaves, escala tipográfica, badges e alerts. |

Raios: **8px** xs, **10px** sm, **14px** md, **16px** lg, **20px** xl, pill.
Controles (botões, campos, filtros rápidos) usam **10px**; cards e painéis **16px**.

### Cabeçalho de páginas

`components/dashboard/header.tsx` é a faixa de cabeçalho compartilhada das páginas
operacionais e dos showcases; o visual é o do `PageHeader`. Segue a composição da ficha de usuário: retorno e
nome à esquerda, navegação e ações à direita; no mobile, os grupos quebram linha.
Recebe `title`, `backHref` opcional e `subtitle` opcional, vazio por padrão.
Não renderizar uma linha vazia de subtítulo. Clientes mostram somente o nome;
não imprimir identificadores ou expressões como `client.uuid` como subtítulo.
Usuários podem manter o e-mail. O gerador CRUD deve produzir o mesmo contrato.

Usar ícones nas ações (inclusive X em Cancelar/Fechar e Save em Salvar), mantendo
os rótulos. Links de navegação com texto oculto no mobile mantêm nome acessível e
`aria-current` no item ativo. Exemplos puros de variantes de botão e opções de
seletores continuam representando seus próprios componentes.

Diálogos usam a superfície opaca `popover` nos dois temas; o token `background`
da página é semitransparente no claro. Limitar a altura ao viewport e permitir
rolagem interna para que o formulário permaneça acessível em telas pequenas.

Campos seguem o padrão da modal de novo usuário, definido nos primitivos e não por
`className` na tela: `Label` com 14px/600 em `foreground`, asterisco de obrigatório
em `destructive` e 8px até o campo (`FormField` empilhado, `space-y-2` ou
`grid gap-2`). `Input` e `Textarea` usam a borda `input` em repouso (3,1:1 no claro
e 3,3:1 no escuro, o tom mais leve que ainda atende aos 3:1 da WCAG 1.4.11 para
contorno de controle), sem sombra duplicando o contorno; o foco e o erro continuam vindo do componente. A modal de novo usuário usa
`outline-hidden` no contêiner para evitar o contorno nativo duplo, mantendo-o em
cores forçadas.

Telas de cadastro e edição em página (referência: `pages/app/clients/create.tsx` e
`edit.tsx`; o gerador CRUD produz o mesmo layout) ocupam a altura da área principal
(`flex min-h-full flex-col`), com o conteúdo em `page-wrapper-md`. Cada seção é uma
grade `md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]`: título 14px/600 e descrição
12px à esquerda, campos num `Card` à direita, em duas colunas a partir de `sm`.
Salvar e Cancelar ficam na barra `sticky bottom-0` encaixada na base da área de
conteúdo (borda superior, `bg-card/95`), com o conteúdo em `page-wrapper-md` (começa
na margem do título e termina com o formulário) e visível com formulário curto ou longo; botões no tamanho padrão de 36px. No mobile, o aviso
de obrigatórios sai da barra para os botões caberem.

### DataTable: preferências e leitura

A tabela mantém a composição compacta existente. Configurar colunas usa o diálogo,
botões, checkbox e select do sistema: ordem por arrastar/setas, visibilidade,
fixação esquerda/direita e densidade compacta/confortável (`sm`/`md`). Seleção e
ações ficam protegidas; pelo menos uma coluna de dados permanece visível.
Resize atua somente nas colunas com guia durante o gesto, confirmação ao soltar,
setas de teclado e cancelamento por Escape/pointercancel. Cabeçalho e corpo
compartilham larguras e offsets. Não há guia na divisória anterior a Ações.
As guias ficam invisíveis em repouso e aparecem na divisória sob hover, foco
visível ou durante arraste, com transição de opacidade de 150 ms e sem transição
quando o usuário prefere movimento reduzido.
Ações não permite arraste; seus botões
inline têm 32 px, raio de 6 px, sem sombra ou preenchimento forte em repouso,
mantendo foco, tooltips e estados desabilitados.
A coluna de ações nasce fixada à direita, preservando posição e largura durante
o resize das colunas de dados. `actionsConfig.sticky=false` permite uma exceção
explícita quando necessária.
No mobile, relaxar a fixação quando necessário
sem apagar a preferência. Ajustar ao conteúdo mede somente linhas carregadas.

Filtros rápidos sem valor mostram somente o nome; nome e valor aparecem quando
ativos. Chips dos filtros avançados ficam na mesma linha da toolbar, sem uma
segunda faixa e sem repetir filtros rápidos. Zero e falso são valores ativos.
Texto truncado permite leitura completa com
quebra de linha por toque, clique ou teclado. O popover de leitura usa largura
do conteúdo, limitada a 24rem e ao viewport, padding compacto e alinhamento ao
final do acionador. Layout e leitura usam tokens semânticos nos dois temas e
não provocam consulta. O modo padrão é rolagem
infinita; paginação explícita permanece disponível.

Preferências locais versionadas por conta/tabela sobrevivem ao logout sob
`datatable-preferences:v1:` por autorização específica. A retenção não inclui
registros, cache ou seleção, e não altera a limpeza de `private-state:`. Consulte
o [contrato de persistência](resources/js/components/data-table/README.md#persistencia-da-selecao).

## Dashboard compacto

1. `PageHeader` com saudação discreta, título "Visão geral" e controle segmentado
   à direita.
2. Grade de **12 colunas** em `xl`, gap **12px**: painel principal ocupa seis;
   quatro indicadores dividem as outras seis (duas colunas no mobile, quatro em `sm`).
3. Indicadores: padding **12px**, rótulo 11px uppercase, chip de ícone **28px**
   (`muted`, `warning-soft` ou `success-soft`), valor **20px** `tabular-nums`. Corpo
   do card sem tinta.
4. Painel principal com valor **24px**, chip **36px** `primary-soft`, barra de
   distribuição de **8px** em `chart-1..3` sobre trilho `muted`; botões de módulo
   em `muted` sem borda, contagem em `card`.
5. Faixa de atalhos com padding **12px**, gap **8px**, até seis colunas no desktop;
   o painel é o único contorno: cada atalho é uma linha sem fundo (hover `accent`)
   com chip de ícone `muted`.
6. Área inferior em **7 + 5 colunas**; painéis com padding **12px**, borda fina,
   raio **16px** e sombra mínima. Nada com borda dentro de um painel com borda:
   chips de ícone dos títulos em `muted`, sem contorno.
7. Linhas: texto **12px**, metadados **11px**, separador `separator` reto, marcador
   `success` e ação discreta à direita.

Indicadores e listas descrevem os recursos da navegação já filtrada pelo
servidor. Não inventar dados sem fonte; contagens de navegação não são contagens
de registros.

## Responsividade, acesso e estados

- Abaixo de **768px**, navegação no drawer do Fluid (Radix Dialog) com overlay,
  Escape, foco contido, título e descrição acessíveis e retorno do foco a quem o
  abriu. A sidebar desktop oculta não recebe foco.
- Módulos viram dropdown quando não há largura no cabeçalho.
- Sidebar recolhida mantém links com nome acessível e tooltip.
- Alvos de toque de **44px** no mobile para botões de ícone.
- `forced-colors`: badges, botões, cards e alerts ganham `outline` transparente
  para manter separação no alto contraste do sistema.
- `prefers-reduced-motion`: os springs do Fluid perdem o deslocamento e mantêm só
  as opacidades; recolher a sidebar usa o spring `slow` (**0.24s**, saída
  **0.16s**) e respeita a preferência. O canvas não tem fundo animado.
- Manter light/dark, teclado, foco visível, estados vazios e rotas ancestrais ativas.
- O frontend usa só a navegação recebida; nunca deduz permissões por papel.
- Não alterar UUIDs, idempotência, notificações, exports ou fluxos de negócio
  para conseguir efeito visual.
- Copy da interface em pt-BR, sentence case, sem "!" em mensagens de sistema.

## Validação

- `tests/Feature/BrandingTest.php`: fallbacks de nome, sigla, hue e logo, caminho
  fora de `public/`, dashboard com logo ausente; prop compartilhado.
  `tests/Feature/BootShellTest.php`: hue, título, favicon, boot shell e fundos claro
  e escuro iguais às fórmulas de `--background` em `app.css`.
- `tests/Unit/Frontend/TokenContrastTest.php`: pares de tokens em AA (texto 4.5:1,
  borda de campo e foco 3:1) para todo `--brand-hue` de 0 a 355, de 5 em 5, nos
  dois temas. Menor folga: borda de campo sobre `card` no escuro, 3.04:1 no hue 355.
- `tests/Unit/Frontend/TokenUsageTest.php`: catraca de tokens, restos de marca,
  copy em inglês, `PageHeader`, `EmptyState` e controles nativos nos arquivos
  migrados.
- Harness Playwright (`tests/Unit/Frontend/DesignParityTest.php` +
  `tests/Fixtures/Frontend/design-parity-browser.mjs`), opt-in com
  `DESIGN_PARITY_BROWSER_TESTS=1`, `DESIGN_PARITY_NODE_BINARY` e
  `DESIGN_PARITY_PLAYWRIGHT_MODULE`: medidas do shell, Geist renderizada, contraste
  AA no claro e no escuro (dashboard, usuários, novo usuário, configurações,
  login, menus e catálogo), borda de campo 3:1, boot shell escuro igual ao canvas,
  marca longa sem overflow, logo de arquivo 10:1 sem distorção, dashboard e
  usuários com `--brand-hue` 30, 150, 280 e 355 nos dois temas, sem rede externa. Capturas em
  `output/playwright/design-parity/`.
- `impeccable detect --json resources/js` sem achados nos arquivos do lote 1.
- Build e TypeScript não provam paridade; o harness usa props sintéticas e não
  prova login nem autorização do backend. Inspeção manual no navegador HTTP
  (`127.0.0.1`, host stateful do Sanctum) nos dois temas, desktop e mobile.

O lote 2 (23/10/2026) estendeu o contrato a dev tools e catálogo de componentes,
`welcome`, `modules/timeline`, `modules/uploads`, `admin/backup`, `admin/slack`,
`external-api/*`, `rich-text-editor`, `async-select`, shell alternativo
(`app-header`, `app-sidebar`), extras da DataTable (exportação, paginação, estados
vazios) e templates do CRUD generator.

A integração de 23/10/2026 juntou essa identidade ao trabalho Fluid do checkout
principal: componentes e comportamento do Fluid; tokens, marca e textos da
identidade; um único cabeçalho de página; nenhuma molécula.

Os filtros avançados da DataTable (`advanced-filter-panel`, `filter-date-section`,
`filter-category-content`, `quick-filter-select`), a célula truncada, a demo de
edição inline e as classes dos filtros rápidos entraram na catraca de tokens com a
integração. Intervalos de filtro aparecem como "0 a 15", sem travessão.