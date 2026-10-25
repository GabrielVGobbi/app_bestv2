# Validação das preferências de DataTable — 21/10/2026

## Correção de largura de ações — 22/10/2026

Complemento após relato de arraste: removida a alça da coluna imediatamente
anterior a Ações; cabeçalhos e links de ações inline têm arraste nativo
desabilitado. Ícones inline agora têm área fixa de 32 × 32 px, raio de 6 px e
sem sombra; exclusão usa vermelho sobre fundo transparente. A largura calculada
considera o padding real da célula (três ícones em Users: 140 px).
Chrome/HTTP em `/users`: arrastar a divisória anterior e o título Ações
preservou ordem, tabela de 1198 px e coluna de 140 px; nenhuma das duas áreas
tem alça. Todos os nove controles mediram 32 × 32 px; Edit/Delete de Developer
continuaram desabilitados. Captura: `report/actions-no-drag.jpg` na pasta desta
validação. TypeScript, build e os 15 checks do contrato de layout passaram.
ESLint: zero erros e o aviso conhecido `react-hooks/incompatible-library` no
TanStack. A nova assertion da fixture completa de navegador foi adicionada,
mas essa suíte não foi repetida; o arraste foi conferido no navegador real.
Não conferidos neste complemento: mobile, modo escuro e demais listagens.

Quando todas as colunas tinham largura salva, a tabela ficava menor que o
viewport (897 px contra 1198 px em Users). Ações já tinha resize desabilitado;
a última coluna de dados agora preenche a sobra, respeitando seu máximo, e
larguras de colunas não redimensionáveis ignoram preferências de tamanho.

Verificação desta correção: contrato de layout com 15 checks via PHPUnit
(1 teste, 4 assertions); Pint do teste, ESLint do algoritmo, TypeScript e build
do aplicativo aprovados. Chrome na sessão existente de
`http://www2.start_kit.com.br/users`: tabela de 1198 px, Ações de 132 px,
sem alça e sem espaço na borda direita. Redução de Nome por teclado de 306 para
296 px manteve Ações em 132 px e alinhada à direita; Nome voltou a 306 px.
Apenas layout foi manipulado; nenhum botão de edição/exclusão foi acionado.
Captura: `storage/framework/testing/datatable/report/actions-fixed.jpg`.
Não repetidos nesta correção: arraste por ponteiro, mobile e demais listagens.

Implementação local autorizada, sem schema/backend/dependências novas. As seis
listagens e o gerador CRUD usam identidade estável de tabela. A retenção de
preferências por conta é separada da seleção, exports, registros e caches privados.
O purge de `private-state:` e `preserveStorage` não foram alterados.

## Verificações executadas

- PHPUnit focado: 110 testes, 519 assertions; 105 passaram, cinco cenários de
  symlink nativo do gerador foram pulados pela guarda Windows existente. Exigem
  runner Linux; nenhum skip novo foi introduzido.
- PHPUnit de regressão de seleção/API, export/payload/download e cancelamento:
  42 testes, 912 assertions, todos passaram. SQLite em memória e runtime/storage
  de testes isolados conforme PREP-001; sem banco existente ou integrações reais.
- Contratos reais TypeScript por PHPUnit/Node: normalização, migração própria,
  contas, corrupção/versão, storage bloqueado, configurações granulares, callbacks
  obsoletos, labels/valores `false`/`0`, metadados de filtros e geometria responsiva.
- Chrome em HTTP no domínio `www2.start_kit.com.br`, porta exclusiva/loopback,
  dados/HTTP Axios controlados: 25 verificações da tabela e uma verificação
  agregada dos 15 casos de seleção existentes. Nenhum pageerror ou saída de rede; 57 requests controlados
  na suíte de seleção. Essa fixture não comprova autenticação do backend.
- A fixture verificou restauração antes da primeira query (busca/filtros/sort/lote),
  drag/setas/visibilidade e proteção da última coluna, resize por teclado/pointer,
  guia sem commit intermediário, Escape/pointercancel, pin/geometria, zero GET ao
  configurar/autosize, leitura completa por teclado, placeholders/chips, resets
  preservando seleção, purge privado, A/B, reload e infinito sem mistura de páginas.
- Chrome/HTTP autenticado, aplicativo e APIs reais em cópia descartável sem
  `.env`/`.config`, com `AuthorityHttpEnvironment` e MySQL próprio: sete verificações
  passaram em `/roles`. Login admin, F5, resize seguido de logout imediato,
  entrada developer com preferências próprias e retorno admin restaurando layout;
  as quatro respostas JSON da tabela foram 200. Linhas carregadas e opacidade
  final do diálogo foram aguardadas para a captura. Zero pageerror/saída de rede.
  A extensão de navegação autenticada das seis listagens está descrita abaixo.
- Build do aplicativo com inputs reais e Compiler instalado, sem executar
  Wayfinder/Artisan ou alterar configuração privada. Build e tipos aprovados.
  Pint dos cinco arquivos PHP afetados aprovado; `git diff --check` aprovado.
- Assets e manifest validados foram integrados em `public/build` local (155
  entradas); referências conferidas e o bundle servido em HTTP pelo domínio local
  retornou 200 com o mesmo hash do arquivo validado. Não houve deploy.

## Limites

### Extensão de navegador nas seis listagens

A solicitação posterior de testar no navegador foi executada com Chromium/HTTP,
sessão real e APIs reais em cópia descartável, usando os helpers existentes de
isolamento e MySQL próprio. **89 verificações passaram**: Users (14), Clients (14),
Roles (13), Permissions (13), Activity Log (14), Inline Cell Edit (13), além de oito
verificações globais. As rotas visitadas foram `/users`, `/clients`, `/roles`,
`/permissions`, `/activity-log` e `/admin/dev/components/inline-cell-edit`.

Todas carregaram linhas reais do banco sintético, cabeçalhos e toolbar; busca fez
GET JSON real, os três painéis de filtro existentes tiveram seus placeholders
conferidos, e cada tabela exercitou resize por teclado, reordenação, fixação,
densidade, ajuste ao conteúdo sem GET e densidade restaurada após F5. Temas foram
alterados pelo controle real, confirmando classe, token de fundo e storage;
desktop e mobile ficaram sem overflow da página. A amostra de texto do corpo
`rgb(237,240,242)` sobre canvas `#121416` tem contraste 16,13:1; isso não constitui
auditoria de contraste de todos os elementos.

Foram 36 respostas API, todas 200, zero pageerror e zero tentativas de rede fora
da allowlist. Os únicos POSTs foram login/logout; o roteador de teste recusava
mutações de recursos e o ledger confirmou contagens de usuários, clientes, papéis,
permissões e vínculos intactas. Os 24 PNGs finais e o recibo completo estão em
`storage/framework/testing/datatable/report/six-pages/` (`browser.json`). Arquivos
`*-failure.png` e o recibo anterior à sincronização da busca são históricos.

Duas falhas de harness foram corrigidas sem reduzir assertions: o filtro de
Clients usa o rótulo `Name`; limpar busca tem debounce e sua resposta precisa
terminar antes de medir zero GET de layout. Também passou a ser aguardada a saída
do diálogo antes do toggle de tema. A execução completa foi repetida após essas
correções. Nenhum código de produto/frontend foi alterado nesta extensão.

Não foram executados salvamento inline, criação/exclusão de recursos ou jobs de
exportação reais: a extensão foi deliberadamente de leitura com login/logout.
SSR/realtime, Linux e produção continuam fora da validação.

O lint dos arquivos alterados conserva três imports sem uso preexistentes em
`pages/app/permissions/index.tsx` e o aviso conhecido do Compiler sobre TanStack
Table. Não foram suprimidos ou corrigidos fora do escopo. Os novos arquivos passam.
Não se alega lint global verde, suíte completa, Linux, produção SSR/realtime ou
implantação. A suíte de UI usa dados sintéticos e não substitui o cenário HTTP
autenticado das seis listagens, relatado acima.

As capturas finais padrão e Configurar colunas, nos temas claro/escuro, e os
recibos JSON ficam em `storage/framework/testing/datatable/report/`. Capturas
intermediárias com erro de geometria ou animação ainda em curso não são evidência
visual final.

Falhas encontradas e corrigidas: `0` em range era tratado como vazio; snapshots
mutáveis TanStack memoizados pelo React Compiler deixavam header e body em ordens
diferentes após pin/reordenação. Agora estado de layout, grupos de cabeçalho e
largura são entradas explícitas. A persistência não usa debounce pendente no
logout. O diálogo novo usa superfície opaca e o leitor de texto tem padding.

Os erros intermediários de preparação/seletores do harness foram corrigidos sem
reduzir assertions: dependências Cache omitidas na primeira cópia, EOF do launcher,
seletor antigo de menu e separador decorativo em vez da alça de largura.

Runtimes, Chrome e MySQL próprios encerraram com código 0. A revisão automática
de aprovação rejeitou a remoção da cópia fonte temporária, inclusive após verificar
token, caminho e ausência de processos, com a razão genérica `blocked by policy`.
A cópia sem configuração privada permanece em `%TEMP%/ops004-58218be3/source`;
nenhum mecanismo alternativo de exclusão foi usado para contornar a recusa.
