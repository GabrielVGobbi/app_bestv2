# Modo escuro

> Histórico (20 a 21/10/2026). Tokens e composição atuais estão em [DESIGN.md](../../DESIGN.md); diagnóstico que motivou a troca em [visual-identity-audit.md](visual-identity-audit.md).

## Refinamento visual, 21/10/2026

Inspeção da sessão autenticada existente no Chrome, em HTTP no domínio local,
confirmou excesso de contornos e pouca hierarquia: separadores e bordas de cards
usavam o mesmo tom; cada linha terminava em uma curva; indicadores inteiros
tinham fundos coloridos. O contraste numérico anterior passava, mas não garantia
uma boa composição visual.

Os tokens globais agora usam grafite de baixa saturação, mantendo teal para
ações/foco. Cards têm superfície uniforme, ícones dos indicadores preservam seus
tons semânticos, atalhos têm superfície `muted` sem segundo contorno e o filtro
selecionado usa `secondary`. Separadores retos usam o novo token `separator`,
mais discreto que `border`; campos mantêm seu próprio token de borda contrastante.
A composição Bionexis, a fonte e o modo claro foram preservados.

| Token/uso                                      | Antes                          | Depois                                 |
| ---------------------------------------------- | ------------------------------ | -------------------------------------- |
| Canvas                                         | `#10171b`                      | `#121416`                              |
| Texto principal/card/popover/secundária/tabela | `#e8f0f3`                      | `#edf0f2`                              |
| Card                                           | `#182329`                      | `#1c2024`                              |
| Popover                                        | `#202e35`                      | `#252a30`                              |
| Secundária                                     | `#25363e`                      | `#30373e`                              |
| Muted                                          | `#1d2b32`                      | `#242a30`                              |
| Texto secundário                               | `#adc0c7`                      | `#a9b3bd`                              |
| Accent                                         | `#2b4049`                      | `#333c44`                              |
| Texto accent/sidebar-accent                    | `#edf8f9`                      | `#edf0f2`                              |
| Borda de painel                                | `#354b56`                      | `#343c44`                              |
| Separador interno                              | `border`                       | `#2b3137`                              |
| Borda de campo                                 | `#607985`                      | `#6c7a88`                              |
| Superfície do campo                            | `#101c22`                      | `#161a1e`                              |
| Sidebar                                        | `#0e1c22`                      | `#171b1f`                              |
| Texto sidebar                                  | `#dce8ed`                      | `#dce1e6`                              |
| Hover sidebar                                  | `#234049`                      | `#2b3239`                              |
| Borda sidebar                                  | `#2c424c`                      | `#2b3137`                              |
| Borda header                                   | `#2b3c44`                      | `#2b3137`                              |
| Fundo tabela                                   | `#21343d`                      | `#242a30`                              |
| Indicadores amber/emerald                      | `warning/8`, `success/8`       | `card`                                 |
| Bordas indicadores                             | `warning/30`, `success/30`     | `border`                               |
| Atalhos                                        | `card`, borda `border`         | `muted`, borda transparente            |
| Filtro selecionado                             | `primary`/`primary-foreground` | `secondary`/`foreground`               |
| Barra de distribuição                          | emerald/slate/cyan fixos       | `chart-1`/`muted-foreground`/`chart-2` |
| Marcadores de recurso                          | emerald-400                    | `success`                              |
| Ícones de título e texto vazio                 | slate-500                      | `muted-foreground`                     |
| Cabeçalho de listagem                          | slate-400                      | `muted-foreground`                     |

O teste de composição existente ganhou regressões de baixa saturação do canvas,
separadores e superfícies dos indicadores. O primeiro run falhou no build anterior:
amplitude RGB do canvas 11, separador igual à borda do painel, raio de linha 16px
e fundos coloridos. O resultado final e os limites estão registrados abaixo.

### Validação desta revisão

- Na raiz do projeto: build Vite, TypeScript, ESLint focal e Prettier aprovados.
- PHPUnit: 1 teste / 17 assertions; runner com **44 verificações Chrome aprovadas**.
  Nenhum erro de aplicação, request ao backend, destino externo ou mutação no runner.
  Textos do dashboard, menu da conta, componentes e login sem falhas no contraste
  medido; campo da página de componentes: borda **3.98:1**, placeholder **8.22:1**.
- Chrome do usuário em `http://www2.start_kit.com.br/dashboard`: sessão existente
  inspecionada em desktop e 390×844; filtros, troca claro/escuro, conta e menu mobile
  conferidos. Viewport restaurado e página deixada no tema escuro, sem overflow.
- O menu mobile revelou ausência de descrição acessível. Foi adicionada uma
  `SheetDescription` invisível, com regressão que falhou antes da correção e passou
  depois. O Chrome confirmou `aria-describedby` após o novo build. Logs anteriores
  do aviso foram preservados; erros de Adblock vieram de `chrome-extension://`,
  não da aplicação. Não foram ocultados nem alteradas extensões.
- Capturas da sessão real: `output/playwright/dark-refinement/before-chrome.jpg`
  e `after-chrome.jpg`; evidência automatizada em `output/playwright/design-parity/`.

O runner de componentes/login usa build real e dados sintéticos com rede isolada.
A inspeção da sessão real foi somente visual e de controles locais; não executou
CRUD, autenticação nova ou operações em banco. Não se alega teste de todas as
telas, outros navegadores ou E2E completo do backend. UUIDs não foram alterados.

## Revisão anterior, 20/10/2026

## Contrato

O [DESIGN.md](../../DESIGN.md#escuro) define os tokens e as regras atuais.
A composição Bionexis foi preservada; o pedido de ajuste completo do modo escuro
autoriza substituir sua mistura de preto/cinza e painéis slate por uma família
de neutros com leve tom teal, refinada para grafite em 21/10. Os módulos e contratos
de negócio não mudaram.

O modo claro mantém sua composição e paleta. A seleção de texto dos campos usa
a primária escura com seu foreground para manter legibilidade em ambos os temas.

## Alterações

- Canvas, cards e overlays em camadas mais claras; textos principal/secundário
  coerentes; bordas de campos separadas das bordas decorativas.
- Dashboard consome tokens semânticos no escuro em vez de cores slate fixas.
- Formulários têm fundo de campo, borda e foco legíveis; avatares usam texto
  escuro sobre teal claro; login compartilha o canvas do tema.
- Botões preenchidos de erro usam foreground escuro. Alertas, badges suaves e
  itens destrutivos de menu usam texto claro semântico sobre superfície escura.
- Menus usam o popover global; diálogos recebem superfície elevada no escuro.
  Hover de sidebar não vira branco ao passar o mouse.
- CSS inicial e metadados declaram color-scheme; o hook já sincronizava o esquema
  após o boot. O hook agora também notifica a mudança do sistema aos controles
  React e permite escolher tema quando localStorage está bloqueado.
- A preferência explícita prevalece sobre a do SO; sem opção explícita, o sistema
  define o tema. Fontes locais e suporte a HTTP permanecem.

## Evidência

O teste existente `tests/Unit/Frontend/DesignParityTest.php` foi ampliado. Usa o
build real e um servidor HTTP descartável no hostname local com porta livre,
props sintéticas e APIs simuladas. Não acessa banco nem encaminha requests para
o backend ou integrações. A reprodução está em
[bionexis-parity.md](bionexis-parity.md#evidência-e-reprodução).

Antes da correção, o teste reproduziu cards mais escuros que o canvas e textos
de avatar com contraste 2.12:1/3.39:1, além de filtros com 4.22:1. A revisão
também identificou a utilização incorreta de `destructive-foreground` em alertas
transparentes. As assertions foram mantidas; corrigiram-se os pares de cores.

A auditoria converte as cores computadas para sRGB, compõe alpha com os ancestrais
e calcula luminância relativa/contraste. Verifica textos renderizados do dashboard,
menu da conta, página de componentes e login: 4.5:1 para texto normal, 3:1 para
texto grande. Campos: bordas ≥3:1 e placeholders ≥4.5:1. Controles desabilitados e
texto apenas para leitores de tela são excluídos da medição visual.

Também verifica persistência entre páginas, mudança do sistema, rótulo do botão,
override manual, storage indisponível, responsividade, foco, navegação recolhida,
variantes e ausência de erros. As capturas em
`output/playwright/design-parity/` incluem desktop/mobile, componentes e login
escuros; o relatório JSON contém valores medidos e diagnósticos.

Resultado final: 40 verificações de navegador aprovadas; PHPUnit 1 teste/17
assertions. Nenhuma falha de contraste nos textos dos quatro cenários auditados.
No campo medido: borda 3.77:1 e placeholder 9.20:1. Build, TypeScript, ESLint focal
(incluindo UI com `--no-ignore`), Prettier dos arquivos de formato aplicável,
Pint do teste e sintaxe PHP do template passaram. Os primitivos UI gerados
mantiveram seu formato existente; não houve reformatação geral desses arquivos.

Limites: avaliação visual com dados sintéticos e Chromium local; não é E2E de
login real, auditoria integral WCAG, validação de todas as telas de negócio,
Safari/Firefox, alto contraste do SO ou produção. A medição considera fundos CSS
compostos, não os pixels decorativos do fundo molecular. Nenhuma alteração em
dados, autorizações, UUIDs, dependências, envios ou implantação.

O detector mecânico Impeccable foi executado nos alvos alterados e retornou lista
vazia. Os componentes UI ignorados pelo ESLint normal foram verificados também
com `--no-ignore`; imports de tipos de Alert/Badge foram alinhados ao padrão local.
