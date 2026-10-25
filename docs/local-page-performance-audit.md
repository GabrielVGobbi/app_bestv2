# Varredura de carregamento — 23/10/2026

## Escopo e método

Inspeção de controllers, recursos, autorização, dados compartilhados, navegação,
paginação e consultas associadas às telas. Leituras no Chrome autenticado em
`http://www2.start_kit.com.br`, cruzadas com Telescope e medidas do Debugbar.
Nenhum formulário funcional foi submetido no ambiente existente. Spotify real,
envio de mensagens, geração de arquivos e operações administrativas não foram
acionados. O [ajuste anterior de SSR](local-roles-performance.md) permanece ativo.

Foram observados 32 endereços distintos: 30 telas renderizadas, a confirmação
de senha solicitada ao entrar em segurança e uma tentativa de rota antiga de
backups que retornou 404. A rota atual de backups foi identificada e medida.
Os tempos abaixo são TTFB do Chrome, em ms, e não substituem um benchmark de
carga. Houve atividade concorrente no computador; não se atribui toda diferença
de tempo às correções deste lote.

| Tela | Amostras de TTFB |
| --- | ---: |
| Dashboard | 1754 / 57 / 60 |
| Usuários | 87 |
| Permissões | 72 |
| Clientes | 88 |
| Atividades | 70 |
| E-mails | 143 / 69 |
| Exportações | 74 |
| Notificações | 78 |
| Perfil | 200 |
| Aparência | 136 |
| Backups (`/admin/dev/backups`) | 183 |
| Configuração Slack | 93 |
| Diagnóstico das integrações | 82 |
| Ferramentas (`/admin/dev`) | 61 |
| Criar usuário | 64 |
| Criar cliente | 54 |
| Catálogo de componentes | 60 |
| Async select | 60 |
| Edição inline | 56 |
| Gerador de CRUD | 449 / 73 |
| Gerador de seeders | 118 |
| Form playground | 68 |
| Toasts | 50 |
| Demonstração de notificações | 57 |
| Activity actions | 282 / 130 |
| Activity timeline | 54 |
| Editor de texto | 62 |
| Demonstração de e-mails | 66 |
| Anexos | 221 |
| Design system | 1165 / 77 |

O dashboard de 1709 ms no Telescope (`a30d2ad8-9ea0-4462-b6e9-7c0aa105169e`)
fez três consultas em 6,25 ms, sem duplicatas nem HTTP externo. O Debugbar
registrou routing apenas em aproximadamente 1666 ms e preparação da resposta em
10 ms. Portanto, o pico ocorreu predominantemente antes do controller, sem
evidência de consulta lenta ou SSR. A causa exata desse pico transitório não foi
comprovada. A segunda abertura teve 55 ms no Telescope.

O request diferido de rotas das ferramentas levou 856 ms no Telescope; o Debugbar
atribuiu cerca de 808 ms à preparação da resposta. A página inicial teve 59 ms:
o catálogo já usa `Inertia::defer`, portanto não bloqueia a abertura do painel.
Não se substituiu esse fluxo nem se introduziu cache de rotas potencialmente
desatualizado. A API de atividades observada respondeu em 205 ms; as listagens
principais de usuários, clientes e permissões ficaram entre 54 e 102 ms no
Telescope nas amostras iniciais.

## Custos reproduzidos e corrigidos

### Navegação compartilhada

`HandleInertiaRequests::share()` chamava `global()`, `modules()` e `footer()`.
Cada getter resolvia a árvore completa: três construções em local ou três leituras
do cache nos demais ambientes. O middleware agora obtém `resolved()` uma vez e
compartilha as mesmas chaves. Getters, regras de visibilidade, ordenação, URLs e
invalidação do cache permanecem compatíveis. Não há memoização adicional que
possa carregar dados antigos para uma nova requisição.

A regressão contou três resoluções da mesma rota antes da mudança e uma depois.
Esse resultado mede trabalho eliminado; não representa redução de 67% no tempo
total da página.

### Lista de e-mails

A consulta carregava o modelo completo e descartava campos apenas ao montar a
resposta. JSONs de `variables` e `metadata` eram trazidos do banco e hidratados
mesmo sem aparecerem na lista nem no detalhe lateral.

A consulta agora seleciona somente as 14 colunas da projeção operacional. O erro
continua genérico, o dado armazenado não muda e a busca, os filtros, a paginação,
os identificadores e o reenvio conservam seus contratos.

Com 26 mensagens sintéticas, cada uma com 128 KiB em cada um dos dois JSONs,
os atributos brutos dos 25 modelos da primeira página, codificados como JSON
para medição, somavam **6.571.775 bytes** antes. A regressão passou a exigir e
confirmou **menos de 50.000 bytes** após a correção. Isso não mede pico de memória
do processo nem implica que esses 6,57 MB fossem enviados ao navegador antes.
A página real estava sem e-mails; não se alega esse ganho sobre seus dados reais.

## Validação

- Regressões primeiro executadas contra o código antigo: duas falhas esperadas,
  comprovando três resoluções e 6.571.775 bytes hidratados.
- `NavigationServiceTest` e `EmailLogControllerTest`: **33 testes / 266 assertions**.
  Inclui campos expostos, erros genéricos, filtros, paginação, revogação de
  autoridade, reenvio e configurações.
- Integração adicional de `ClientCrudTest`, `Api/UserTest`, `AuthoritySecurityTest`
  e `ActivityLogTest`: **154 testes / 1.281 assertions**.
- Total focal: **187 testes / 1.547 assertions**, sem falhas finais, no PHP 8.3.36,
  usando as guardas existentes, SQLite em memória e integrações falsas.
- Pint passou nos cinco arquivos PHP alterados. `git diff --check` passou nos
  arquivos do lote; Git avisou sobre normalização futura de CRLF na documentação
  de e-mail, sem erro de whitespace.
- E2E no Chrome/HTTP do hostname original, porta isolada 64346, PHP 8.4.27,
  MySQL descartável com dono verificado, sessão exclusiva, Mail/filas/HTTP externo
  isolados, por `tests/Fixtures/Frontend/interface-browser-server.php`.
  Foram criadas 26 mensagens grandes somente nesse banco próprio. A tela mostrou
  `1–25 de 26 registros`, abriu o detalhe lateral e navegou por links Inertia para
  usuários (`30 carregados de 36`) e de volta para e-mails, preservando o menu.
  Erros consultados no console eram da extensão AdBlock, não da aplicação.
- O servidor confirmou zero usuários funcionais criados e demais contagens
  monitoradas intactas. Após `stop`, o teardown do harness terminou com código 1
  e `BindingResolutionException` para `ExceptionHandler`, com a limpeza do MySQL
  parcialmente executada. Não se afirma encerramento integral aprovado do harness.
  A inspeção independente confirmou ausência dos processos próprios e de listener
  na porta HTTP 64346. Restaram 54 arquivos sintéticos sob o diretório temporário
  `C:/Users/GABRIELGOBBI/AppData/Local/Temp/start-kit-mysql-2a1c7b63571ef1ae26f809a46d910e14/data`.
  A tentativa de remoção recursiva desse destino, após conferir caminho absoluto,
  ausência de links e processos, foi rejeitada pela revisão automática como
  `blocked by policy`. O resíduo permanece para limpeza manual; não se tentou
  contornar o bloqueio. Nenhum processo MySQL de outros projetos foi encerrado.

## Limites

Não se executaram suíte global, build frontend, teste de carga, produção, SSR
ativo nem chamadas reais ao Spotify. A tela de segurança redirecionou para
confirmação de senha; seu conteúdo protegido não foi exercitado. As medições de
componentes cobrem abertura e recursos já concluídos naquele instante, sem
certificar cada interação, carregamento diferido, upload, export ou gerador.
A paginação e os filtros de e-mail foram testados no PHPUnit; o E2E cobriu a
primeira página e o detalhe, pois o router descartável existente restringe query
strings dessas telas. Picos transitórios continuam possíveis e não foram
mascarados por desligar Telescope, Debugbar ou verificações de segurança.
