# Auditoria de identidade visual, 22/10/2026

Relatório de diagnóstico da iniciativa "identidade visual da plataforma"
(branch `claude/platform-visual-identity-1bb740`). Não altera código. Serve de
base para a spec de redesenho que vem em seguida. Complementa
[bionexis-parity.md](bionexis-parity.md) e [dark-mode.md](dark-mode.md).

## Método

- **Código:** `resources/css/app.css` (tokens), shell (`layouts/app/app-dashboard-layout.tsx`,
  `components/dashboard/dashboard-header.tsx`, `dashboard-sidebar.tsx`,
  `nav-module-items.tsx`), `pages/dashboard.tsx`, primitivos `components/ui/`
  (button, card, input, badge, alert, sonner), shell da DataTable, layouts de auth,
  `resources/views/app.blade.php`, `app-logo.tsx` e `app-logo-icon.tsx`.
- **Navegador:** checkout principal servido em `http://127.0.0.1:8000`
  (`php artisan serve`), sessão developer de seed, temas claro e escuro,
  1440x900 e 375x812. Páginas: login, dashboard, users (lista e criação),
  clients, roles, email-logs, exportações, notificações, activity log,
  settings/profile, confirm-password, dev tools, design-system.
  O domínio Laragon `www2.start_kit.com.br` bloqueou os assets no navegador
  embutido e `localhost:8097` caiu em 401 do Sanctum (host fora da lista stateful).
- **Automação:** varredura de contraste WCAG sobre texto renderizado (cor e fundo
  compostos via canvas) no dashboard claro e escuro e em exportações escuro;
  `impeccable detect --json resources/js`; Tab x7 para conferir foco.
- **Skills:** superpowers:brainstorming, impeccable (playbooks audit e critique),
  redesign-existing-projects, design:accessibility-review, design:design-critique.
  Não empilhadas, por decisão do vault: design-taste-frontend, minimalist-ui,
  high-end-visual-design (fora de escopo para produto denso).

## Placar (régua do `impeccable audit`)

| # | Dimensão | Nota | Achado-chave |
| --- | --- | --- | --- |
| 1 | Acessibilidade | 2/4 | contraste reprovado no claro; 58 usos de 10 e 11px; select nativo |
| 2 | Performance | 3/4 | fundo animado só em transform; backdrop-blur no header; sem medição profunda |
| 3 | Theming | 2/4 | ~500 classes de paleta fixa em 38 arquivos; modo claro fora dos tokens |
| 4 | Responsivo | 3/4 | mobile funciona; alvos de toque de 32 a 36px; filtro quebra em duas linhas |
| 5 | Integridade | 2/4 | marca indefinida, restos Runey/Best Play/Bionexis, idioma misto, acentos concorrentes |
| | **Total** | **12/20** | **Aceitável: trabalho significativo** |

## Contraste medido

Dashboard, modo claro, 1440x900 (31 combinações únicas de texto):

| Elemento | Texto | Fundo | Razão | Exigido |
| --- | --- | --- | --- | --- |
| Cabeçalho da listagem "Recurso / Módulo / Acesso" (slate-400, 10px) | 144,161,185 | 255,255,255 | 2.62:1 | 4.5:1 |
| Avatar do rodapé da sidebar, branco sobre teal | 255,255,255 | 0,175,181 | 2.69:1 | 4.5:1 |
| Avatar do cabeçalho, branco sobre `runey-orange` | 255,255,255 | 232,97,58 | 3.39:1 | 4.5:1 |
| Módulo ativo no cabeçalho "Gestão" (13px) | 98,116,142 | 248,248,248 | 4.48:1 | 4.5:1 |
| Rótulos 10 e 11px slate-500 sobre cards âmbar e esmeralda | 98,116,142 | 253,251,239 | 4.50 a 4.60:1 | 4.5:1 (no limite) |

Modo escuro: dashboard com 22 combinações e zero falhas; exportações com 12 e
zero falhas. Menor tamanho de fonte encontrado: 10px. Foco por teclado visível
(anel branco nos links da sidebar).

## Achados por prioridade

### P1

1. **Marca indefinida.** `app.blade.php` desenha um "R" em Poppins com
   `aria-label="Runey"` no boot shell; `app-logo-icon.tsx` repete o "R";
   `DEFAULT_LOGO_ALT = 'Best Play'`; o token `--color-runey-orange` colore o
   avatar do cabeçalho no claro; `public/src/assets/imagens/molecule/` é imagem
   da Bionexis (peptídeos). O nome exibido é "Start kit". Nada disso pertence ao
   produto. Impacto: a plataforma não tem identidade reconhecível e carrega
   três marcas alheias.
2. **Modo claro sem disciplina de acento.** Na mesma tela convivem teal
   (`primary`), ciano (foco e barra), esmeralda (marcadores, card "Sua conta"),
   âmbar (card "Módulos"), laranja (avatar), preto (filtro selecionado) e
   navy (sidebar e cabeçalho da tabela). O escuro usa um acento só e funciona.
3. **Contraste reprovado no claro** (tabela acima). Critério WCAG 1.4.3.
4. **Tokens não governam o claro.** 500 ocorrências de `slate|red|emerald|amber|green|blue|sky|yellow|violet|...-NNN`
   em 38 arquivos (slate 90, red 64, emerald 52, amber 43, green 40, blue 24,
   sky 19, yellow 17, violet 12, neutral 12, orange 10, cyan 9). Maiores:
   `pages/dashboard.tsx` 72, `pages/app/email-logs/index.tsx` 57,
   `admin/dev/components/email.tsx` 32, `admin/dev/tools.tsx` 28,
   `data-table-bulk-actions-bar.tsx` 25, `activity-log/index.tsx` 24,
   `timeline-activity-item.tsx` 24, `ui/sonner.tsx` 20. Cada componente duplica
   o visual com `dark:`; trocar tema ou marca exige editar tela por tela.

### P2

5. **Idioma misto.** Sidebar, listas e dev tools em pt-BR; login, registro,
   confirmação de senha, settings (Profile, Security, Appearance, Save, Delete
   account), criação de usuário (New User, Create user, Email address) e menu da
   conta (Settings, Log out) em inglês. `lang/pt_BR` existe só no backend; o
   frontend não tem helper de tradução.
6. **Escala tipográfica com micro texto.** 37 usos de `text-[10px]` e 21 de
   `text-[11px]`; rótulos uppercase com tracking em toda seção; números sem
   `tabular-nums`; `Heading` usa 16px/500 na variante small e 20px/600 na padrão,
   enquanto o dashboard usa 24px/700 e as listagens 20px/700 (três escalas de título).
7. **Vermelho em toda linha.** Botão destrutivo preenchido em cada linha de
   users, roles e permissions; "Kill Switch" vermelho no topo de e-mails;
   "Ativar" e "Desabilitar" vermelhos no dev tools. O vermelho deixa de significar perigo.
8. **Controle nativo.** `<select>` em `pages/app/email-logs/index.tsx` (2),
   `admin/dev/components/form-playground.tsx` (2), `admin/dev/components/email.tsx` (1)
   e `<textarea>` em `modules/timeline/components/comment-composer.tsx`,
   contra a regra global do projeto (componente existente antes de nativo).
9. **Alvos de toque.** Ícones de ação das tabelas e botões do cabeçalho com
   32 a 36px no mobile (WCAG 2.5.5 pede 44px, ou espaçamento equivalente).
10. **Toasts e badges com paleta própria.** `ui/sonner.tsx` fixa emerald, red,
    amber e sky; `Badge destructive` usa `text-white` no claro; `Alert default`
    é transparente.

### P3

11. **Duas massas escuras no claro.** Sidebar navy e cabeçalho da tabela navy
    (`bg-sidebar` em `data-table-header.tsx`) disputam peso com o conteúdo.
12. **Barra de distribuição com cores por índice** (esmeralda, slate, ciano),
    sem significado.
13. **Welcome** ainda é a página padrão do Laravel: fonte via Bunny, hex fixos,
    links para docs do Laravel.
14. **Boot shell** com `Poppins`, `#0d0d0d` e fundo branco em `html` fora dos tokens.
15. **Detector** (`impeccable detect`): borda lateral `border-l-2` em
    `timeline-comment-item.tsx:234`; gradiente violeta em `admin/dev/tools.tsx:181`;
    gradiente roxo em `admin/slack/index.tsx:119`.

## O que funciona e deve ser preservado

- Geist e Geist Mono locais com `@font-face` e `font-display: swap`.
- Arquitetura de tokens em `app.css` (oklch no claro, hex no escuro) mapeada
  para o `@theme` do Tailwind 4; raios, container 1600px, `page-wrapper`.
- Modo escuro grafite com teal reservado a ação e foco: passou na varredura.
- Shell: sidebar 240/80 com tooltips, Sheet mobile com Escape e foco contido,
  módulos no cabeçalho com dropdown responsivo, foco visível.
- DataTable com toolbar, filtros, export, preferências e skeletons.
- Catálogo vivo em `/admin/dev/components/design-system` (tipografia, cores,
  botões, badges, cards, alerts, formulários, avatares, skeletons, tooltips).
- Harness Playwright (`tests/Unit/Frontend/DesignParityTest.php`) com auditoria
  de contraste e capturas em `output/playwright/`.

## Capturas de referência (antes)

Desktop claro e escuro do dashboard, listagem de usuários no claro e dashboard
mobile foram enviados ao chat da sessão em 22/10/2026. O harness de paridade gera
capturas reproduzíveis; a spec deve prever o "depois" pelo mesmo caminho.

## Limites desta auditoria

- Não inspecionado: Chrome real do usuário, estados de hover, telas de erro,
  SSR, permissions, clients create/show, uploads, backups, crud generator,
  seeder generator, timeline, two-factor. A varredura de contraste cobriu
  dashboard (claro e escuro) e exportações (escuro); as demais telas foram
  avaliadas visualmente.
- `localhost` e `127.0.0.1:8000` não estão em `SANCTUM_STATEFUL_DOMAINS`; só
  `127.0.0.1:8000` (lista padrão do Sanctum) autentica a API. O 401 visto em
  `localhost:8097` é artefato do ambiente, não bug da aplicação.
- O checkout principal tem alterações não commitadas na DataTable e no DESIGN.md
  (seção "DataTable: preferências e leitura"); esta branch partiu de `f1d3433`
  e não as contém.
- Nenhuma dependência, migration, commit ou dado real foi alterado.
