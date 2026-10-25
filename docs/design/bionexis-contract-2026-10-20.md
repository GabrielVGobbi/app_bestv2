# Start Kit: sistema visual Bionexis

> Histórico. Substituído em 22/10/2026 pelo contrato em [DESIGN.md](../../DESIGN.md).

Este é o contrato visual canônico do projeto. A referência aprovada é o layout
de [Bionexis](http://www2.bionexis.com.br/dashboard), especialmente seu dashboard
compacto. A solicitação de 20/10/2026 autoriza copiar **somente o visual**:
os módulos, rotas, dados, permissões e identidade continuam sendo do Start Kit.

## Fonte da referência e limite de paridade

A comparação usa a captura fornecida pelo usuário e os componentes do projeto
local que serve o domínio Bionexis. Foram inspecionados `resources/css/app.css`,
`components/dashboard/`, `components/nav-module-items.tsx`,
`layouts/app/app-dashboard-layout.tsx`, `pages/dashboard.tsx` e os PNGs do fundo.
O projeto de referência é somente leitura. Seus componentes de negócio e suas
regras de autoridade não são copiados.

O dashboard inicial do Start Kit ainda tinha `PlaceholderPattern`; portanto não
era uma reprodução do dashboard do Bionexis. Tokens parecidos e os mesmos
primitivos de botão/card não produzem a mesma composição. A paridade deve ser
avaliada no shell, hierarquia, densidade e estados, não só pela paleta.

Não importar documentação inteira da referência: ela também contém instruções
antigas de Aptos e estilos específicos de recebimento. Os valores abaixo e o
código atual do Start Kit definem o contrato aplicável.

## Estrutura da aplicação

- Laravel 13, Inertia 3, React 19 e Tailwind 4; usar os componentes instalados.
- Entrada: `resources/js/app.tsx`; layout persistente:
  `resources/js/layouts/app/app-dashboard-layout.tsx`.
- Sidebar à esquerda, cabeçalho acima da coluna de conteúdo e scroll apenas na
  área principal. Sem scroll horizontal da página.
- Variante padrão `sidebar-logo`: sidebar expandida **240px**, recolhida **80px**;
  cabeçalho **64px**. Logo sem cápsula de fundo em volta do link.
- Variante `header-logo` continua suportada. Não eliminar a configuração existente.
- Sidebar: padding horizontal **12px**, vertical **20px**; logo seguida por **40px**
  de espaço; seções separadas por **32px**; itens em cápsula, padding **8px 12px**.
- Cabeçalho: padding horizontal **20px**; controle de recolher, divisor, breadcrumb,
  módulos sem cápsula externa, notificações, tema e conta.
- Módulo selecionado no cabeçalho: cápsula cinza clara com texto escuro. Nunca usar
  um botão teal preenchido como substituto desse estado.
- Com módulos no cabeçalho, mostrar os itens diretamente na sidebar. O bloco
  “Módulo ativo” não pertence a essa variante.
- Conta fixa no rodapé: avatar teal, nome e e-mail em cápsula translúcida escura.
- Página: `.page-wrapper`, máximo **1600px**, centralizada; padding horizontal
  **16px** no mobile e **24px** a partir de `md`; padding superior **20px**.

## Cores

Tokens globais ficam em `resources/css/app.css`. Não duplicar paletas por tela.

| Uso              | Claro                        | Escuro    |
| ---------------- | ---------------------------- | --------- |
| Canvas           | `oklch(0.9401 0 0 / 12.9%)`  | `#121416` |
| Texto global     | `oklch(25% 0.08 205)`        | `#edf0f2` |
| Card global      | `oklch(0.984 0.003 247.858)` | `#1c2024` |
| Sidebar          | `oklch(25% 0.08 205)`        | `#171b1f` |
| Texto sidebar    | `oklch(0.9 0.006 220)`       | `#dce1e6` |
| Primária         | `oklch(0.65 0.19 196)`       | `#47d7c6` |
| Botão primário   | `#00616a`                    | `#47d7c6` |
| Texto secundário | `oklch(0.554 0.046 257.417)` | `#a9b3bd` |
| Secundária       | `oklch(0.96 0.004 247)`      | `#30373e` |
| Borda global     | `oklch(0.9 0.006 220)`       | `#343c44` |

O dashboard compacto da referência usa superfícies brancas a 95%, borda slate-200,
texto principal slate-950, texto secundário slate-500 e microtexto slate-400.
O modo claro mantém esses valores da referência. No escuro, a revisão solicitada
em 20/10/2026 substitui os azuis slate e pretos desconectados pelos tokens
semânticos `card`, `border`, `foreground` e `muted-foreground`. Preservar a
composição da referência; não manter cores antigas apenas por estarem no clone.

### Modo escuro

- Revisão de 21/10/2026: neutros grafite com baixa saturação. Canvas `#121416`,
  cards `#1c2024`, overlays `#252a30`; camadas elevadas são mais claras que o canvas.
  Teal fica concentrado em ações, foco e detalhes, sem tingir todas as superfícies.
- Texto principal `#edf0f2`, secundário `#a9b3bd`. Texto normal e placeholders:
  mínimo **4.5:1**; texto grande **3:1**. Conferir o fundo composto real.
- Bordas de painéis `#343c44`; separadores internos `separator` (`#2b3137`);
  bordas de campos `#6c7a88`, com mínimo **3:1** contra o campo `#161a1e`.
  Não usar uma única borda fraca para todos os papéis. Linhas internas do dashboard
  têm separador reto e discreto, sem desenhar cápsulas dentro de cada painel.
- Indicadores compartilham a superfície `card`; tons amber/emerald ficam nos ícones.
  Atalhos usam `muted` sem segundo contorno. Filtro selecionado usa `secondary`
  e `foreground`, preservando o estado pressionado e o foco de teclado.
- Ação/foco teal `#47d7c6` com texto `#062d2c`. Evitar letras brancas sobre teal
  claro, inclusive nos avatares. Foco deve continuar visível ao usar Tab.
- Status: erro `#f58b95`, sucesso `#71d7a4`, aviso `#f4cc7a`. Em fundos sólidos,
  usar o respectivo `*-foreground` escuro. Em alertas, badges suaves e menus
  destrutivos, usar o próprio tom semântico claro sobre superfície escura.
- Hover da sidebar usa `sidebar-accent`, sem flashes de branco no tema escuro.
- `color-scheme` no root e no HTML inicial sincroniza controles nativos antes do
  carregamento do React. A preferência explícita ganha do sistema; no modo
  `system`, a página e o botão de tema devem reagir juntos à mudança do SO.
- Storage indisponível não pode impedir a escolha do tema na sessão atual.
- Novos componentes escuros consomem tokens; não introduzir paletas slate/zinc
  paralelas em cards, menus, formulários ou painéis.

Sidebar ativa: fundo `secondary`, texto `foreground`. Texto claro sobre a cápsula
clara é um erro de contraste, mesmo que use o token `sidebar-accent-foreground`.
Não reduzir a opacidade dos textos normais da sidebar para 45%, 55% ou 70%.

## Tipografia

- Geist local para interface e marca tipográfica; Geist Mono local para códigos
  e pequenos contadores. Fallback é contingência de carregamento.
- Arquivos e licença em `resources/fonts/`; `@font-face` em `app.css`, pesos normais
  **100–900**, `font-display: swap`. Vite gera URLs com hash.
- Não depender de Bunny/Google Fonts nem de fontes instaladas no Windows.
- Base HTML **16px**; corpo operacional **14px**; sidebar e módulos **13px/500**;
  descrições **12px**; metadados **11px**; rótulos de seção **10px/700**, uppercase
  com tracking; título do dashboard **24px/700**; valores **20–24px/700**.
- Verificar a fonte efetivamente renderizada, não apenas `getComputedStyle()`.

## Dashboard compacto

1. Saudação discreta, título “Visão geral” e controle segmentado alinhado à direita.
2. Grade de **12 colunas** em `xl`, gap **12px**: painel principal ocupa seis;
   quatro indicadores dividem as outras seis. Indicadores em duas colunas no
   mobile e quatro em `sm`.
3. Indicadores: padding **12px**, rótulo **10px**, ícone circular **28px** no canto,
   valor **20px**. Tons de apoio amber e emerald suaves, nunca gradientes.
4. Painel principal com valor **24px**, ícone circular **36px**, barra de distribuição
   de **8px** e pequenos botões de resumo com borda.
5. Faixa de atalhos com padding **12px**, gap **8px**, até seis colunas no desktop.
   Cada atalho usa ícone circular **28px** e rótulo **14px/600**.
6. Área inferior em **7 + 5 colunas**; painel grande à esquerda, dois menores
   empilhados à direita; gap **12px**. Painéis compactos com padding **12px**,
   borda fina, raio **16px** e sombra mínima.
7. Linhas: texto **12px**, metadados **11px**, separadores slate-100, cantos
   arredondados, marcador pequeno de estado e ação discreta à direita.

No Start Kit, indicadores e listas descrevem os recursos da navegação já filtrada
pelo servidor. O controle segmentado filtra módulos. Não inventar pedidos,
faturamento, clientes recentes ou filtros de período sem dados correspondentes.
Contagens de navegação não representam contagens de registros do banco.

## Componentes e páginas existentes

Reutilizar `components/ui/`, DataTable, formulários e diálogos existentes. Os
primitivos de botão e card já coincidiam com a referência; não reescrevê-los só
para produzir uma alteração aparente. As mudanças do shell e dos tokens se
aplicam às telas existentes.

Raios: **8px** xs, **10px** sm, **14px** md, **16px** lg, **20px** xl, **9999px** pill.
Botão padrão **36px**, campo **40px**; usar variantes existentes. Sombras suaves
em painéis; sombras fortes apenas onde o componente de overlay já as exige.

## Fundo e movimento

Usar os PNGs da referência em `public/src/assets/imagens/molecule/` pelo componente
`components/layout/molecule-background.tsx`: fundo fixo, sem captura de eventos,
opacidade **9%** no claro e **3%** no escuro, atrás do conteúdo. Não hotlinkar.
Movimento de fundo é decorativo e deve parar com `prefers-reduced-motion`.
Recolher sidebar: transição de largura **200ms**, respeitando reduced motion.

## Responsividade, acesso e estados

- Abaixo de **768px**, navegação em Sheet com overlay, Escape, foco contido e
  retorno ao controle. A sidebar desktop oculta não recebe foco.
- Módulos viram dropdown quando não há largura suficiente no cabeçalho.
- Recolher sidebar mantém links com nome acessível e tooltip; seções viram
  separadores, sem perder os links dos grupos.
- Manter light/dark, teclado, foco visível, estados vazios e rotas ancestrais ativas.
- O frontend usa somente a navegação recebida; nunca deduz permissões por papel
  e nunca concede acesso para preencher um card.
- Não alterar UUIDs, idempotência, notificações, exports ou fluxos de negócio
  para conseguir paridade visual.

## Validação

Build e TypeScript não provam paridade. Comparar no navegador HTTP em desktop e
mobile, conferir fontes renderizadas, sidebar normal/recolhida, módulos, tema,
menus, ausência de overflow e de erros. Testes de composição podem usar props
sintéticas e assets reais, com toda rede interceptada; isso não prova login ou
autorização do backend e deve ser identificado como tal no relatório.

Referência visual não autoriza mudanças em dados reais, banco, integrações,
mensagens, dependências, commit ou implantação.
