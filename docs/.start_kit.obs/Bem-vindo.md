# StartKit — Documentação do Projeto

Bem-vindo ao vault de documentação interno do **StartKit**, um kit de partida full-stack para aplicações Laravel + React/Inertia.

Comece pelo [README de instalação](../../README.md), pelas [regras canônicas](../../AGENTS.md), pelo [mapa da arquitetura](../../.docs/project/architecture.md) e pelo [guia de retomada](../../.docs/project/project.md). O [painel da estabilização](../stabilization/board.md) aponta o estado e as evidências atuais; a presença de uma integração neste índice não autoriza sua ativação.

---

## Stack

| Camada | Tecnologia |
| --- | --- |
| Backend | PHP 8.3 + Laravel 13 |
| Frontend | React 19 + Inertia.js v3 |
| Estilos | Tailwind CSS v4 |
| Realtime | Laravel Reverb + Echo |
| Auth | Laravel Fortify + Sanctum |
| Tipos de rotas | Laravel Wayfinder |
| Monitoramento | Laravel Pulse + Telescope |

---

## Módulos Documentados

| Módulo | Arquivo | O que cobre |
| --- | --- | --- |
| Exportação Assíncrona | [Contrato canônico](Exports/async-export-system.md) | Trait Exportable, ProcessExport, API, DataTable, polling e downloads |
| Backups e Dumps | [Contrato canônico](../backups/backup-dumps-module.md) | Backups, dumps, retenção, configuração e recuperação |
| Email | [Contrato do módulo](Email/email-module.md) | Envio assíncrono, idempotência, kill switch e webhooks |
| Notificações | [Contrato canônico](Notifications/notifications-module.md) | AppNotification, eventos privados e ações autorizadas |
| Slack | [Contrato do módulo](Slack/slack-module.md) | Webhooks, log e kill switch |
| Navegação | [Contrato](Navigation/navigation-config.md) | config/navigation.php, NavigationService e sidebar React |
| CRUD Generator | [Código do módulo](../../app/Modules/CrudGenerator) | startkit:crud, UI administrativa e arquivos gerados |

Uploads possui a [configuração efetiva no módulo](../../app/Modules/Uploads/Config/uploads.php). Os demais módulos e providers estão no [mapa da arquitetura](../../.docs/project/architecture.md). Para autoridade e validação isolada, consulte [PREP-002](../stabilization/tasks/PREP-002.md#contrato-adotado) e [CI Windows local](../stabilization/ci-runtime.md). A [cópia antiga de Exports](../exports/async-export-system.md) e a [especificação original de Notifications](../notifications/spec.md) são históricas, não fontes concorrentes de implementação.

### Notas locais opcionais

As notas abaixo podem existir nesta estação, mas permanecem ignoradas e não integram a documentação distribuída em um clone novo. Seus caminhos são referências locais; não autorizam ativar integrações ou copiar arquivos de outro projeto:

- Backups: `Backups/integração com outro projeto.md`, receita contextual de portabilidade.
- Email: `Email/implementar-em-novo-projeto.md` e `Email/portar-para-novo-projeto.md`, receitas complementares ao contrato acima.
- Slack: `Slack/portar-para-novo-projeto.md`, receita complementar.
- CRUD Generator: `CrudGenerator/crud-generator.md`, nota local detalhada; o código distribuído está no índice acima.
- Financeiro: `Financeiro/picador-de-notas.md`, referência de outro domínio.
- C6 Bank: `C6 Bank - Integração/README.md` e `C6 Bank - Integração/DOC-C6-Apidog.md`, referências locais de API/Apidog.
- PlugNotas: `PlugNotas - DFe/sincronizacao-incremental-dfe.md`, estratégia de sincronização contextual.

As exceções de [`.gitignore`](../../.gitignore) são explícitas para o índice e os cinco contratos canônicos deste vault; não liberam o vault inteiro, as receitas locais ou suas configurações. Tornar um arquivo elegível para inclusão não o adiciona ao Git: a inclusão futura depende de uma revisão e operação Git próprias.

---

## Convenções do Projeto

- Módulos ficam em `app/Modules/{Modulo}/` com ServiceProvider próprio
- Models usam traits: `HasUuidIdentifier`, `Searchable`, `Exportable` (quando aplicável)
- API versionada em `/api/v1/` com `auth:sanctum`
- Rotas frontend tipadas via Wayfinder (`@/actions/` e `@/routes/`)
- Navegação gerenciada centralmente em `config/navigation.php`
- Exports assíncronos via job + polling (nunca síncronos)
- Notificações customizadas em `app_notifications` (não a tabela nativa do Laravel)

### Rotas de clientes e ordenação administrativa

O CRUD de clientes tem uma única declaração em `app/Modules/Clients/routes.php`, carregada por `ClientsServiceProvider`. O grupo web mantém `web`, `auth` e `verified`; a API mantém `/api/v1`, nomes `api.clients.*`, `api` e `auth:sanctum`. Não repetir esses resources em `routes/web.php` ou `routes/api.php`. Os nomes `clients.*`, helpers Wayfinder e binding por UUID são preservados também com cache de rotas.

As listagens API de papéis e permissões validam `sort` antes de aplicar a ordenação, depois de autorizar o catálogo do ator. Ambas aceitam `id`, `name`, `slug`, `description`, `created_at` e `updated_at`; papéis também aceitam `permissions_count` e `users_count`, e permissões aceitam `group`. `direction` aceita `asc`/`desc`, com padrão `name asc`. Colunas desconhecidas recebem 422; a ordenação mantém o escopo de autoridade e as contagens de usuários legíveis.

---

## Fluxo de Desenvolvimento

### Datas, locale e agendamento

O fuso da aplicação é `America/Sao_Paulo`, definido em `config/app.php`. PHP e Carbon usam esse fuso; o provider Carbon instalado acompanha `app.locale`, `app.fallback_locale` e mudanças por `App::setLocale()`, incluindo datas mutáveis, imutáveis e a facade Date. O AppServiceProvider não força locale do sistema operacional nem outro idioma. O locale de Faker continua configurado separadamente.

Armazenamento segue o contrato de cada campo: timestamps SQL sem offset de Client conservam a interpretação local existente; `scheduled_at` e `webhook_occurred_at` de EmailLog já normalizam explicitamente para UTC. A serialização de datas Eloquent usa ISO 8601 UTC com `Z`. Isso não autoriza converter dados existentes nem tratar todo campo SQL como UTC. Ao converter um Carbon mutável, use cópia para preservar o instante e o fuso do objeto original.

O formatador compartilhado e os rótulos da timeline exibem `pt-BR` no fuso local do navegador. As chaves dos grupos usam esse mesmo calendário local: um instante `2031-05-02T00:30:00Z` pertence a 1º de maio em São Paulo e a 2 de maio em UTC/Tóquio. A API continua transportando o instante UTC; cursores e ordem dos itens permanecem independentes da chave visual do dia.

As oito tarefas de manutenção declaram UTC explicitamente, conforme OPS-002. Backup executa às 02:00 UTC, limpeza às 03:00 UTC e monitoramento às 04:00 UTC; em São Paulo correspondem atualmente a 23:00 do dia anterior, 00:00 e 01:00. UTC evita que a agenda dependa do fuso da máquina; mudanças futuras de regra civil podem alterar a conversão local. As travas e o runtime operacional seguem o [contrato do scheduler](../stabilization/ops-scheduler-runtime.md).

As regressões de [REL-005](../stabilization/tasks/REL-005.md) usam relógio controlado, SQLite descartável e os helpers reais em Node/Intl nos fusos UTC, São Paulo, Nova York e Tóquio. A inspeção `schedule:list` não executa jobs. Esses checks não comprovam navegador/E2E, hidratação SSR, daemon de fila ou concorrência em banco nativo.

### Integrações externas: renovação OAuth e diagnóstico

O refresh de Spotify relê a conexão depois de adquirir o lock por usuário. OAuth POST permanece com uma tentativa, inclusive com `retry_attempts` maior: não há backoff/reenvio automático de credenciais. Para uma conexão expirada, a configuração é validada antes de esperar pelo lock; lease e espera usam `max(timeout, connect_timeout) + 10` segundos. A margem cobre a persistência após HTTP e não constitui fencing durável para pausas que excedam a lease. Tokens ainda válidos são reutilizados. Em múltiplos processos/servidores, todos precisam do mesmo cache com locks atômicos.

Os diagnósticos de chamadas do cliente HTTP preservam o status recebido em erros4xx/5xx,429 para limite e0 quando não houve resposta HTTP. Campos operacionais continuam projetados antes de log/cache, com erro genérico e URLs sem query sensível. Exceções HTTP de domínio e falhas de token Spotify conservam o código, sem incorporar corpo remoto na mensagem. Isso não autoriza registrar tokens, headers, payloads ou exceções brutas de transporte.

Os testes de [EXT-001](../stabilization/tasks/EXT-001.md) usam HTTP e relógio simulados com ArrayLock real, incluindo espera além10s, releitura do vencedor, isolamento por usuário e liberação em falhas. Não equivalem a prova multiprocessos, latência real de provedor ou E2E de navegador.

### Novo CRUD

```bash
php artisan startkit:crud {Model} --target=module --module={Modulo} --field=name:string:required:searchable --preview --no-interaction
# Revisar preview, depois aplicar sem --preview
php artisan wayfinder:generate --with-form --no-interaction
php artisan migrate
```

### Nova Notificação

```php
app(CreateNotificationAction::class)->handle(
    CreateNotificationDTO::system(recipientId: $user->id, title: '...', body: '...', category: NotificationCategory::Export)
);
```

### Novo Item de Navegação

1. Adicionar em `config/navigation.php` com `href: ['route' => 'rota.nome']`
2. Adicionar prefixo em `route_prefixes` do módulo
3. Garantir que o ícone existe em `resources/js/lib/nav-icons.ts`

### Exportação em Novo Model

1. Ler [[Exports/async-export-system]] e reutilizar o fluxo existente.
2. Definir `Exportable`, o mapa `$exportable`, a policy `viewAny` e o hook `applyExportScope` do model. O padrão recusa modelos sem escopo explícito; preservar os limites de dados do ator.
3. Adicionar ao `allowed_models` em `config/exports.php` e configurar `features={{ export: true }}` + `exportConfig` no `<DataTable>`.
4. Testar ator permitido/recusado, colunas e revogação antes do processamento, conforme o documento canônico.

---

## Ambiente Local

```bash
composer run dev       # Laravel + Vite + queue listener
php artisan reverb:start  # WebSocket opcional, processo separado
php artisan queue:listen  # Listener separado quando não usar composer run dev
```


### Inicialização repetível

Com as dependências PHP disponíveis, prepare os arquivos locais com:

```bash
php artisan app:init --skip-storage-link --no-interaction
```

O comando preserva `.env`, `.config` e o banco SQLite existentes. Uma chave não vazia no `.env`, incluindo aspas ou comentários, é mantida. Se a chave vem de configuração externa ou cache e é válida, ela também é preservada. A geração ocorre quando não há chave efetiva; o comando confere que uma chave utilizável foi persistida. Repetir a inicialização conserva a chave e os dados cifrados com ela.

`composer run init:start-kit` executa a mesma preparação com os links de storage configurados. A opção `--force-storage-link` repassa `--force` ao `storage:link`; a recriação segue as regras do Laravel para links simbólicos. Uma configuração de links vazia ou `--skip-storage-link` dispensa sua criação. A inicialização verifica o resultado dos comandos e das escritas; falhas retornam código diferente de zero e interrompem as etapas seguintes. Arquivos já criados antes de uma falha são mantidos para a nova tentativa.

`composer run setup` inclui instalação de dependências, preparação local, migrações e build. O evento `post-create-project-cmd` reutiliza a mesma preparação e propaga falhas de migração. A cópia inicial do `.env` no evento anterior à instalação das dependências também falha quando não consegue copiar o exemplo. Esses fluxos preservam uma chave já existente; a rotação de chaves continua sendo uma operação explícita de manutenção.
