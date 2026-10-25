# Modulo Backups & Dumps

Documentacao completa do modulo `App\Modules\Backup`. O modulo combina duas responsabilidades relacionadas:

- Backups automaticos da aplicacao usando `spatie/laravel-backup`.
- Dumps SQL sob demanda usando `spatie/db-dumper`, fila Laravel, download autenticado e expiracao automatica.

Use este documento como referencia para manter o modulo neste projeto ou replica-lo em outros projetos Laravel + Inertia + React.

---

## Indice

1. [Visao geral](#1-visao-geral)
2. [Capacidades](#2-capacidades)
3. [Fluxos principais](#3-fluxos-principais)
4. [Estrutura de arquivos](#4-estrutura-de-arquivos)
5. [Dependencias](#5-dependencias)
6. [Configuracao](#6-configuracao)
7. [Banco de dados](#7-banco-de-dados)
8. [Provider, rotas e schedule](#8-provider-rotas-e-schedule)
9. [Backend](#9-backend)
10. [Frontend](#10-frontend)
11. [Seguranca](#11-seguranca)
12. [Comandos operacionais](#12-comandos-operacionais)
13. [Testes](#13-testes)
14. [Como replicar em outro projeto](#14-como-replicar-em-outro-projeto)
15. [Checklist de producao](#15-checklist-de-producao)
16. [Problemas comuns](#16-problemas-comuns)

---

## 1. Visao geral

O modulo entrega uma tela administrativa em `/admin/backups`, com duas abas:

- **Backups Automaticos**: historico dos backups gerados pelo `spatie/laravel-backup`, com download autenticado.
- **Dumps de Banco**: geracao de arquivos `.sql` sob demanda, com selecao opcional de tabelas e tipo de conteudo.

O modulo fica isolado em `app/Modules/Backup` e registra suas proprias rotas, migrations, listeners e comandos via `BackupServiceProvider`.

---

## 2. Capacidades

| Recurso | Detalhe |
| --- | --- |
| Backup automatico | `php artisan backup:run` agendado diariamente as 02:00 |
| Historico de backups | Persistido em `backup_histories` via eventos do Spatie Backup |
| Download de backup | Rota web autenticada e protegida por role |
| Dump sob demanda | POST API cria registro e despacha job |
| Tipos de dump | MySQL/MariaDB/PostgreSQL: `schema`, `data`, `full`; SQLite: somente `full` |
| Escopo de tabelas | Opcional: todas as tabelas ou lista especifica |
| Exclusao de tabelas pesadas | Usa `database.connections.*.dump.exclude_tables` quando o dump nao e limitado por tabelas |
| Expiracao de dumps | Configurada em `config/database-exports.php` |
| Limpeza de dumps | `database-exports:prune`, agendado diariamente |
| UI de progresso | Estado em `localStorage`, polling e toast |
| Notificacao interna | Cria notificacao quando o dump fica pronto |

---

## 3. Fluxos principais

### 3.1 Backup automatico

```text
Scheduler
  -> php artisan backup:run
  -> spatie/laravel-backup gera o zip
  -> Spatie dispara BackupWasSuccessful ou BackupHasFailed
  -> SyncBackupToHistory grava backup_histories
  -> Usuario acessa /admin/backups
  -> Usuario baixa pelo endpoint autenticado
```

### 3.2 Dump sob demanda

```text
Usuario abre /admin/backups
  -> Aba "Dumps de Banco"
  -> Dialog carrega GET /api/v1/database-exports/tables
  -> Usuario escolhe modo conforme driver (SQLite somente full) e tabelas opcionais
  -> POST /api/v1/database-exports
  -> Cria database_exports com status pending
  -> Dispatch ProcessDatabaseExport
  -> Frontend salva no localStorage e inicia polling
  -> Job gera arquivo SQL em temp
  -> Storage local privado recebe database-exports/{uuid}/{token}.sql
  -> Conclusao + aviso AppNotification na mesma transacao
  -> Registro completed com expires_at; broadcast apos commit
  -> Download autenticado em /api/v1/database-exports/{uuid}/download
```

### 3.3 Limpeza automatica

```text
Scheduler
  -> php artisan database-exports:prune
  -> Reconcilia pending/processing abandonados e limpa candidatos canonicos
  -> Preserva processamento ativo e caminho quando a exclusao falha
  -> Remove completed expirado apenas apos limpeza confirmada
```

---

## 4. Estrutura de arquivos

```text
app/Modules/Backup/
  Console/Commands/
    PruneDatabaseExports.php
  Database/Migrations/
    2026_05_16_000001_create_backup_histories_table.php
    2026_05_16_000002_create_database_exports_table.php
  Http/Controllers/Admin/
    BackupController.php
  Http/Controllers/Api/
    DatabaseExportApiController.php
  Http/Requests/
    CreateDatabaseExportRequest.php
  Http/Resources/
    DatabaseExportResource.php
  Jobs/
    ProcessDatabaseExport.php
  Listeners/
    SyncBackupToHistory.php
  Models/
    BackupHistory.php
    DatabaseExport.php
  Providers/
    BackupServiceProvider.php
  routes.php

config/
  backup.php
  database-exports.php
  database.php
  navigation.php

resources/js/
  components/database-export/
    database-export-dialog.tsx
    database-export-status.tsx
    index.ts
    use-database-export.ts
  pages/admin/backup/
    index.tsx
  types/
    database-export.ts
  lib/api/api-routes.ts
  lib/nav-icons.ts

tests/Feature/Backup/
  DatabaseExportApiTest.php
```

---

## 5. Dependencias

### Composer

```json
{
  "require": {
    "spatie/laravel-backup": "^10.2"
  }
}
```

`spatie/laravel-backup` traz `spatie/db-dumper`, usado tambem pelo job manual de dump.

### Sistema operacional

O `spatie/db-dumper` depende dos binarios nativos do banco:

| Driver | Binario esperado |
| --- | --- |
| MySQL/MariaDB | `mysqldump` |
| PostgreSQL | `pg_dump` |
| SQLite | `sqlite3` |

Esses binarios precisam estar disponiveis no `PATH` do processo PHP/queue worker.

### Frontend

O frontend usa dependencias ja existentes no projeto:

- `@inertiajs/react`
- `@tanstack/react-query`
- `lucide-react`
- `sonner`
- componentes internos em `resources/js/components/ui`

---

## 6. Configuracao

### 6.1 `config/backup.php`

Arquivo de configuracao do `spatie/laravel-backup`.

Pontos importantes:

```php
'source' => [
    'files' => [
        'include' => [
            base_path(),
        ],
        'exclude' => [
            base_path('.env'),
            base_path('.env.*'),
            base_path('.config'),
            base_path('.config.*'),
            base_path('auth.json'),
            base_path('.npmrc'),
            base_path('.aws'),
            base_path('.ssh'),
            base_path('.git'),
            base_path('vendor'),
            base_path('node_modules'),
            base_path('bootstrap/cache'),
            database_path('*.sqlite*'),
            storage_path('framework'),
            storage_path('logs'),
            storage_path('debugbar'),
        ],
        'relative_path' => base_path(),
    ],
    'databases' => [
        env('DB_CONNECTION', 'mysql'),
    ],
],
```

Recomendacoes:

- Manter credenciais, `.config`, caches compilados e resíduos fora do backup conforme a lista acima.
- Usar disco privado.
- Em producao, definir `BACKUP_ARCHIVE_PASSWORD`.
- Ajustar `monitor_backups` conforme disco e volume esperado.

### 6.2 `config/database-exports.php`

Controla os dumps sob demanda.

```php
return [
    'disk' => 'local',
    'directory' => 'database-exports',
    'expires_after_hours' => 24,
];
```

| Chave | Descricao |
| --- | --- |
| `disk` | Disco do Storage para os dumps. Deve ser privado. |
| `directory` | Pasta dentro do disco. |
| `expires_after_hours` | Tempo de validade apos conclusao. |

### 6.3 `config/database.php`

O modulo usa a conexao default:

```php
$config = config('database.connections.'.config('database.default'));
```

Para backups e dumps completos, configure tabelas que devem ser excluidas:

```php
'mysql' => [
    // ...
    'dump' => [
        'exclude_tables' => [
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
            'pulse_aggregates',
            'pulse_entries',
            'activity_log',
        ],
    ],
],
```

Quando o usuario escolhe tabelas especificas, a lista escolhida tem prioridade e essas exclusoes nao sao aplicadas automaticamente.

### 6.4 `.env`

Variaveis recomendadas:

```dotenv
BACKUP_ARCHIVE_PASSWORD=uma-senha-forte
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

Para o backup automatico Spatie, se usar S3:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
```

Depois ajuste o disco do backup automatico em `config/backup.php`. Dumps sob demanda em `config/database-exports.php` exigem disco local privado, mesmo quando o backup automatico usa S3.

---

## 7. Banco de dados

### 7.1 `backup_histories`

Registra cada execucao do backup automatico.

| Coluna | Tipo | Descricao |
| --- | --- | --- |
| `id` | bigint | ID interno |
| `uuid` | uuid unique | Identificador publico |
| `disk` | string nullable | Disco escolhido; nulo quando a falha ocorre antes da escolha |
| `path` | string nullable | Caminho do arquivo no storage |
| `file_name` | string nullable | Nome exibido no download |
| `file_size` | unsigned big integer nullable | Tamanho em bytes |
| `status` | string | `completed` ou `failed` |
| `error_message` | text nullable | Erro quando falhar |
| `backed_up_at` | timestamp | Data da execucao |
| `created_at`, `updated_at` | timestamps | Controle Laravel |

### 7.2 `database_exports`

Registra dumps sob demanda.

| Coluna | Tipo | Descricao |
| --- | --- | --- |
| `id` | bigint | ID interno |
| `uuid` | uuid unique | Identificador publico |
| `user_id` | foreign id | Dono do dump |
| `tables` | json nullable | Tabelas selecionadas; `null` significa todas |
| `export_type` | string | `schema`, `data` ou `full` |
| `status` | string | `pending`, `processing`, `completed`, `failed` |
| `file_path` | string nullable | Caminho no Storage |
| `file_name` | string nullable | Nome amigavel |
| `file_size` | unsigned big integer nullable | Tamanho em bytes |
| `error_message` | text nullable | Mensagem de falha |
| `started_at` | timestamp nullable | Inicio do job |
| `completed_at` | timestamp nullable | Fim do job |
| `expires_at` | timestamp nullable | Expiracao do arquivo |
| `created_at`, `updated_at` | timestamps | Controle Laravel |

### 7.3 Route key

Os dois models usam `HasUuidIdentifier`. As rotas recebem UUID, nao ID numerico.

---

## 8. Provider, rotas e schedule

### 8.1 Provider

`BackupServiceProvider` faz quatro coisas:

```php
$this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
$this->loadRoutesFrom(__DIR__.'/../routes.php');
$this->commands([PruneDatabaseExports::class]);

Event::listen(BackupWasSuccessful::class, [$listener, 'handleSuccess']);
Event::listen(BackupHasFailed::class, [$listener, 'handleFailure']);
```

Registre em `bootstrap/providers.php`:

```php
use App\Modules\Backup\Providers\BackupServiceProvider;

return [
    // ...
    BackupServiceProvider::class,
];
```

### 8.2 Rotas web

Middleware:

```php
['web', 'auth', 'verified']
```

Rotas:

| Metodo | URI | Nome | Descricao |
| --- | --- | --- | --- |
| GET | `/admin/backups` | `backup.index` | Tela Inertia |
| GET | `/admin/backups/{backupHistory}/download` | `backup.download` | Download de backup |
| GET | `/api/v1/database-exports/{databaseExport}/download` | `backup.database-exports.download` | Download direto do dump pelo browser |

O download do dump fica no grupo web porque o browser navega diretamente para baixar o arquivo. Ele nao usa `fetch`.

### 8.3 Rotas API

Middleware:

```php
['api', 'auth:sanctum']
```

Rotas:

| Metodo | URI | Nome | Descricao |
| --- | --- | --- | --- |
| GET | `/api/v1/database-exports` | `backup.database-exports.index` | Lista dumps recentes nao expirados do usuario |
| POST | `/api/v1/database-exports` | `backup.database-exports.store` | Cria dump e despacha job |
| GET | `/api/v1/database-exports/tables` | `backup.database-exports.tables` | Lista tabelas do banco |
| GET | `/api/v1/database-exports/{databaseExport}` | `backup.database-exports.show` | Status do dump |

### 8.4 Schedule

A agenda em `routes/console.php` usa UTC explícito: `backup:run` às 02:00, `backup:clean` às 03:00 e `backup:monitor` às 04:00, diariamente. Os três compartilham mutex de duração e eleição por minuto no cache database. Dumps/exports/richtext têm limpeza diária; ZIP a cada hora. A configuração PHP/Carbon preserva America/Sao_Paulo como fuso efetivo histórico.

No Windows, `php artisan schedule:work` precisa de processo externo no checkout correto; a declaração da agenda sozinha não o inicia. A campanha comprova processos descartáveis e não instala uma tarefa persistente. [Contrato completo de horários, locks, operação e limites](../stabilization/ops-scheduler-runtime.md).

---

## 9. Backend

### 9.1 `BackupController`

Responsavel pela tela administrativa e download de backups automaticos.

Metodos:

- `index(Request $request): Response`
- `downloadBackup(BackupHistory $backupHistory, Request $request): StreamedResponse`

Regras de download:

| Condicao | Resposta |
| --- | --- |
| Usuario sem role `developer` ou `admin` | 403 |
| Backup nao concluido | 404 |
| Backup sem path | 404 |
| Arquivo ausente no disco | 404 |
| OK | `Storage::disk($disk)->download(...)` |

Tambem registra activity log:

```php
activity('backups')
    ->performedOn($backupHistory)
    ->causedBy($request->user())
    ->log('downloaded');
```

### 9.2 `DatabaseExportApiController`

Responsavel pela API dos dumps sob demanda.

Metodos:

- `index(Request $request): AnonymousResourceCollection`
- `store(CreateDatabaseExportRequest $request): JsonResponse`
- `show(DatabaseExport $databaseExport, Request $request): DatabaseExportResource`
- `download(DatabaseExport $databaseExport, Request $request): StreamedResponse`
- `tables(Request $request): JsonResponse`

Regras relevantes:

- Apenas roles `developer` e `admin`.
- O usuario so ve e baixa os proprios dumps.
- Dump expirado retorna 410.
- Dump nao concluido retorna 404.
- Arquivo ausente retorna 404.

### 9.3 `CreateDatabaseExportRequest`

Valida e autoriza a criacao de dumps.

Payload aceito:

```json
{
  "export_type": "full",
  "tables": ["users", "clients"]
}
```

`DatabaseDumpOptions` centraliza modos e nomes literais. A conexão Laravel resolvida fornece driver e configuração efetivos, inclusive aliases e opções vindas de URL; o nome da conexão não é tratado como driver. O catálogo usa o schema builder da conexão (`public` no PostgreSQL). Drivers desconhecidos recebem 422 antes de SQL específico ou despacho.

- MySQL/MariaDB e PostgreSQL: `schema`, `data`, `full`.
- SQLite: somente `full`; `schema`/`data` recebem 422, sem registro, job ou arquivo. Payload legado também é recusado no job antes do dumper.
- `tables` é um array opcional de nomes existentes. Cada nome passa por validação literal: MySQL/MariaDB aceitam letras ASCII, números e underscore; PostgreSQL somente letras minúsculas ASCII, números e underscore. O primeiro caractere não pode ser número. SQLite aceita somente letras ASCII e números, iniciando por letra.
- Padrões, nomes qualificados, espaços e fragmentos SQL/shell são recusados. No SQLite, underscore é padrão de seleção do CLI e por isso também é recusado; PostgreSQL mixedcase é recusado para evitar seleção incorreta por case folding.

### 9.4 `DatabaseExportResource`

Normaliza a resposta JSON.

Campos:

```json
{
  "uuid": "...",
  "status": "completed",
  "export_type": "full",
  "tables": null,
  "file_name": "database-export-uuid.sql",
  "file_size": 12345,
  "download_url": "https://app.test/api/v1/database-exports/uuid/download",
  "expires_at": "...",
  "created_at": "...",
  "completed_at": "..."
}
```

`download_url` so aparece quando:

- status e `completed`
- arquivo nao esta expirado

`error_message` so aparece quando status e `failed`.

### 9.5 `ProcessDatabaseExport`

Job principal dos dumps.

Interfaces:

```php
class ProcessDatabaseExport implements ShouldBeUnique, ShouldQueue
```

`uniqueId()` retorna o UUID do pedido. O claim atômico aceita apenas `pending` recente, impedindo que entregas duplicadas executem dois dumps. O orçamento é 900 segundos, uma tentativa; o subprocesso do dumper possui limite de 840 segundos.

Fluxo:

```text
pending recente -> claim processing com candidato exclusivo por token
  -> resolve configuração e dumper (limite 840s)
  -> dump para .part privado da tentativa
  -> publica stream e confere tamanho
  -> sob condição de estado/prazo, conclusão + aviso AppNotification na mesma transação
  -> broadcast após commit; limpeza dos resíduos identificados
```

O disco deve ser local, privado e canônico. Diretórios públicos, links e adaptadores não locais são recusados. Entrega já terminal ou processing não é retomada pelo mesmo UUID. Pedido `pending` com 1.020 segundos ou processamento com 900 segundos é reconciliado como failed com mensagem genérica; para gerar novamente, crie outro pedido. Falha de persistência do aviso desfaz a conclusão. Uma falha posterior de broadcast conserva o aviso e o dump. Payloads antigos sem token são recusados sem executar dumper/Storage: pending sem caminho recebe falha genérica e exige novo pedido; processing/terminais são preservados para a reconciliação pelo prazo.

O contrato completo de [orçamentos, recuperação e operação](../stabilization/ops-worker-runtime.md) distingue timeout externo do Listener no Windows e timeout com PCNTL. Restore permanece no escopo OPS-003.

Tipos:

| `export_type` | MySQL/MariaDB | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| `schema` | `doNotDumpData()` | `--schema-only` | recusado |
| `data` | `--no-create-info` | `--data-only` | recusado |
| `full` | default | default | default |

SQLite exige arquivo persistente existente para gerar `full`; `:memory:` ou arquivo ausente falham antes do SDK. Nenhum modo parcial pode entregar dados silenciosamente.

Escopo de tabelas:

- Seleção explícita tem precedência sobre `dump.exclude_tables` da configuração da conexão. Ambos são validados no job, inclusive payloads antigos.
- PostgreSQL qualifica filtros como `public.<tabela>`; filtros não alcançam outras schemas. Um dump completo sem filtro ainda inclui as demais schemas conforme o comportamento padrão do `pg_dump`.
- SQLite com exclusões calcula e valida todos os nomes restantes e passa uma inclusão explícita ao SDK. Conjunto vazio é recusado, pois o SDK retornaria ao dump integral; um nome restante não literal também é recusado. Sem filtros, `.dump` integral é permitido, sem interpolar nomes do catálogo no shell.

### 9.6 `SyncBackupToHistory`

Listener dos eventos do Spatie Backup.

Eventos:

- `BackupWasSuccessful`
- `BackupHasFailed`

No sucesso:

1. Le arquivos dentro da pasta do backup no disco.
2. Seleciona o arquivo mais recente.
3. Cria `BackupHistory` com status `completed`.

Na falha:

1. Cria `BackupHistory` com status `failed`.
2. Salva a mensagem de excecao e preserva `disk = null` quando nenhum disco foi escolhido, sem acessar Storage.

A migration aditiva `2026_10_18_223539_make_backup_history_disk_nullable` permite esse histórico sem alterar a migration original. O rollback verifica primeiro se há linhas com disco nulo e recusa a reversão enquanto existirem; não apaga nem inventa um disco. Antes de uma reversão operacional, preservar o histórico e resolver explicitamente essas linhas conforme o destino real conhecido. Downloads recusam disco ausente antes de resolver Storage, inclusive em linhas inconsistentes marcadas completed.

### 9.7 `PruneDatabaseExports`

Comando:

```bash
php artisan database-exports:prune
```

Reconcilia `pending` abandonado (1.020 segundos) e `processing` vencido (900 segundos), preservando execução ativa mesmo que `expires_at` esteja antigo. Usa varredura por ID e um corte fixo por execução, sem reenfileirar dump; após falha, outro pedido/UUID é necessário.

`completed` expirado só perde a linha após confirmar ausência de todos os candidatos canônicos conhecidos e `.part`. `failed` tem os arquivos limpos, mas conserva diagnóstico por `failed_retention_days` (padrão 7 dias desde `updated_at` terminal); limpar `file_path` não renova esse prazo. Data terminal ausente conserva a linha. Falha física, resíduo ou veto de exclusão conserva o rastreamento e retorna erro, continuando os demais pedidos.

No disco local privado, órfãos reconhecidos em `diretório/UUID.sql`, `diretório/UUID/token-UUID.sql` e respectivos `.part` são elegíveis após `orphan_retention_hours` (24 horas por padrão). Qualquer UUID cadastrado ou caminho referenciado protege o artefato. A comparação de UUID ignora caixa; referências locais no Windows também, sem renomear arquivos. Nomes desconhecidos ficam preservados. Guardas canônicas recusam links/hardlinks, raízes públicas e ancestrais inseguros antes de listar ou excluir; idade/identidade/referências são conferidas novamente. Não há varredura de temporário global, disco remoto, snapshot do filesystem ou atomicidade entre banco e arquivo. Exclusão parcial pode deixar referência a arquivo já ausente para permitir nova reconciliação. O comando de retenção relê sob lock e verifica os eventos Eloquent de atualização/exclusão. A limpeza técnica de uma tentativa terminal (`cleanupArtifacts`), usada pelos callbacks do job, mantém o contrato CAS anterior: confere o snapshot, limpa candidatos e só então libera o caminho se o estado ainda corresponder, sem renovar `updated_at`. Ela não reabre pedidos failed nem acrescenta uma transação ao callback; uma nova geração exige outro UUID.

---

## 10. Frontend

### 10.1 Pagina Inertia

Arquivo:

```text
resources/js/pages/admin/backup/index.tsx
```

Props:

```ts
interface Props {
    backups: PaginatedBackups;
}
```

Responsabilidades:

- Define breadcrumb via `setLayoutProps`.
- Renderiza header `Backups & Dumps`.
- Controla aba ativa: `backups` ou `dumps`.
- Mostra historico de backups vindo do controller.
- Integra dialog e status dos dumps.

### 10.2 Tipos TypeScript

Arquivo:

```text
resources/js/types/database-export.ts
```

Tipos principais:

```ts
export type DatabaseExportStatus =
    'pending' | 'processing' | 'completed' | 'failed';

export type DatabaseExportType = 'schema' | 'data' | 'full';
```

`DatabaseExportRecord` representa a resposta da API.

`StoredDatabaseExport` representa o estado salvo no `localStorage`.

### 10.3 Hook `useDatabaseExport`

Arquivo:

```text
resources/js/components/database-export/use-database-export.ts
```

Responsabilidades:

- Ler e gravar `localStorage`.
- Restaurar historico recente do servidor.
- Criar dump via POST.
- Fazer polling a cada 3 segundos para dumps `pending` ou `processing`.
- Mostrar toast quando concluir ou falhar.
- Marcar dump como baixado.

Constantes:

```ts
const STORAGE_KEY = 'database-exports';
const POLL_INTERVAL_MS = 3000;
const MAX_EXPORT_AGE_MS = 24 * 60 * 60 * 1000;
```

Retorno:

```ts
{
    activeExports,
    hasActiveExports,
    startExport,
    markAsDownloaded,
}
```

### 10.4 `DatabaseExportDialog`

Arquivo:

```text
resources/js/components/database-export/database-export-dialog.tsx
```

UI para iniciar dump.

Controles:

- Toggle de conteudo: `schema`, `data`, `full`.
- Checkbox para limitar a tabelas especificas.
- Lista de tabelas carregada por `GET /api/v1/database-exports/tables`.
- Botao `Gerar Dump`.

### 10.5 `DatabaseExportStatus`

Arquivo:

```text
resources/js/components/database-export/database-export-status.tsx
```

Popover com dumps recentes.

Estados visuais:

| Status | UI |
| --- | --- |
| `pending` | Indicador "Na fila..." |
| `processing` | Spinner/pulso |
| `completed` | Check e link de download |
| `failed` | Erro |

### 10.6 Rotas no frontend

Arquivo:

```text
resources/js/lib/api/api-routes.ts
```

```ts
databaseExports: {
    index: '/api/v1/database-exports',
    store: '/api/v1/database-exports',
    show: (uuid: string) => `/api/v1/database-exports/${uuid}`,
    download: (uuid: string) => `/api/v1/database-exports/${uuid}/download`,
    tables: '/api/v1/database-exports/tables',
},
```

### 10.7 Navegacao

Arquivos:

```text
config/navigation.php
resources/js/lib/nav-icons.ts
```

Adicione item:

```php
[
    'slug' => 'backups',
    'label' => 'Backups',
    'icon' => 'Archive',
    'type' => 'link',
    'href' => ['route' => 'backup.index'],
    'order' => 7,
    'enabled' => true,
    'visible' => true,
],
```

E registre `Archive` no mapa de icones.

---

## 11. Seguranca

### 11.1 Autorizacao

O modulo usa role check direto:

```php
$request->user()?->hasRole('developer', 'admin')
```

Isso protege:

- Tela `/admin/backups`
- Listagem de dumps
- Criacao de dumps
- Download de backups
- Download de dumps
- Listagem de tabelas

Para outro projeto, adapte para Policy/Gate se esse for o padrao.

### 11.2 Ownership

Dumps pertencem a um usuario:

```php
abort_if(! $databaseExport->isOwnedBy($request->user()), 403);
```

Mesmo outro admin nao baixa dump criado por outro usuario.

Backups automaticos nao tem owner; qualquer usuario autorizado pela role pode baixar.

### 11.3 Storage privado

Nunca use disco publico para dumps ou backups sensiveis.

Correto:

```php
'disk' => 'local'
```

Evite:

```php
'disk' => 'public'
```

### 11.4 Arquivos sensiveis

O backup de arquivos exclui:

- `.env`
- `.env.*`
- `.config` e `.config.*`
- `auth.json`, `.npmrc`, `.aws` e `.ssh`
- `bootstrap/cache`
- `database/*.sqlite*` (dump lógico recupera o banco)
- `.git`
- `vendor`
- `node_modules`
- `storage/framework`
- `storage/logs` e `storage/debugbar`

Ainda assim, backups podem conter codigo, configs e dados sensiveis. Em producao, use senha de arquivo:

```dotenv
BACKUP_ARCHIVE_PASSWORD=uma-senha-forte
```

`encryption=default` sem senha não cifra o ZIP. Guardar senha de arquivo e `APP_KEY` separadamente; a segunda é necessária para recuperar dados Laravel cifrados. Os arquivos do projeto usam caminhos relativos, com normalização dos separadores Windows e validação antes de restauração. Não executar scripts do arquivo nem extrair sobre o projeto existente. [Procedimento, inventário e prova de recuperação OPS-003](../stabilization/ops-backup-recovery.md).

### 11.5 Expiracao

Dumps manuais expiram por `expires_at`. Apos expirar:

- API nao retorna `download_url`.
- Download retorna 410.
- `database-exports:prune` remove arquivo e registro.

---

## 12. Comandos operacionais

### Rodar migrations

```bash
php artisan migrate
```

### Rodar backup manualmente

```bash
php artisan backup:run
```

### Verificar saude do backup

```bash
php artisan backup:monitor
```

### Limpar backups antigos do Spatie

```bash
php artisan backup:clean
```

### Limpar dumps expirados

```bash
php artisan database-exports:prune
```

### Ver comandos registrados

```bash
php artisan list database-exports
```

### Ver agenda

```bash
php artisan schedule:list
```

### Worker de fila

Necessario para processar dumps sob demanda:

```bash
php artisan queue:work --timeout=900 --tries=3 --backoff=30 --sleep=1
```

Em desenvolvimento:

```bash
php artisan queue:listen --timeout=960 --tries=3 --backoff=30 --sleep=1
```

---

## 13. Testes

Arquivo:

```text
tests/Feature/Backup/DatabaseExportApiTest.php
```

Cobertura atual:

- Admin lista dumps.
- Usuario comum recebe 403.
- Admin cria dump e job e despachado.
- SQLite recusa `schema` com 422, sem criar ou despachar dump.
- Admin limita dump a tabelas especificas.
- Tipo invalido retorna 422.
- Usuario comum nao cria dump.
- Admin consulta proprio dump.
- Admin nao consulta dump de outro usuario.
- Dump expirado retorna 410.
- Admin lista tabelas.
- Arquivo ausente retorna 404.
- `file_path` ausente retorna 404.
- Dump completo MySQL aplica `exclude_tables`.

Executar:

```bash
php artisan test --compact tests/Feature/Backup/DatabaseExportApiTest.php
```

BKP-001 acrescenta `BackupContractTest` (histórico, migration, aliases e recusas sem efeitos) e `DatabaseDumpContentTest` opt-in. O perfil nativo exige `BKP_DUMP_NATIVE_TESTS=1`, `AUTHORITY_MYSQL_TESTS=1`, `AUTHORITY_MYSQLD_BINARY`, `BKP_PG_BINARY_DIRECTORY` e `BKP_SQLITE_BINARY_DIRECTORY` explicitamente aprovados em um shell saneado com APP_ENV testing e SQLite :memory: principal. Requer PDO mysql/pgsql e SQLite3 habilitados somente no runtime privado. Ele cria suas próprias instâncias MySQL/PostgreSQL e arquivo SQLite, valida identidade e conteúdo de dumps integrais/com seleção/com exclusão, confere a fonte e encerra os processos. Nunca apontar esse perfil para um banco existente. Sem opt-in, seus casos ficam skipped; ele não foi acrescentado ao coordenador CI automaticamente.

No Windows, o vínculo PostgreSQL exige o PID do executável aprovado como filho do processo Symfony vivo, porque Symfony cria um intermediário CMD. Pasta, porta, usuário e marcador SQL continuam obrigatórios. O cenário `mariadb alias on mysql` cobre a branch compartilhada no MySQL, não comprova um servidor MariaDB independente. Restore/recuperação operacional permanecem em OPS-003.

Checks complementares:

```bash
vendor/bin/pint --dirty --format agent
npm run types:check
npx prettier --check resources/js/pages/admin/backup/index.tsx resources/js/components/database-export/database-export-dialog.tsx resources/js/components/database-export/database-export-status.tsx resources/js/components/database-export/use-database-export.ts resources/js/types/database-export.ts resources/js/lib/nav-icons.ts
npx eslint resources/js/pages/admin/backup/index.tsx resources/js/components/database-export/database-export-dialog.tsx resources/js/components/database-export/database-export-status.tsx resources/js/components/database-export/use-database-export.ts resources/js/types/database-export.ts resources/js/lib/nav-icons.ts
```

---

## 14. Como replicar em outro projeto

### 14.1 Pre-requisitos

- Laravel com queue configurada.
- Autenticacao funcionando.
- Um sistema de roles, gates ou policies.
- Storage privado.
- Binarios de dump instalados (`mysqldump`, `pg_dump` ou `sqlite3`).
- Inertia + React se quiser copiar a UI.

### 14.2 Instalar pacote

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

Revise o `config/backup.php` publicado antes de ir para producao.

### 14.3 Copiar backend

Copie:

```text
app/Modules/Backup/
config/database-exports.php
```

Registre provider:

```php
// bootstrap/providers.php
App\Modules\Backup\Providers\BackupServiceProvider::class,
```

Se o projeto nao usa estrutura modular, adapte namespaces para:

```text
app/Http/Controllers
app/Http/Requests
app/Http/Resources
app/Jobs
app/Models
app/Listeners
app/Console/Commands
database/migrations
routes/web.php
routes/api.php
```

### 14.4 Adaptar autorizacao

Este projeto usa:

```php
$request->user()?->hasRole('developer', 'admin')
```

Em outro projeto, substitua por uma destas abordagens:

```php
$request->user()?->can('manage backups')
```

ou:

```php
Gate::authorize('manage-backups');
```

ou uma Policy dedicada.

### 14.5 Copiar frontend

Copie:

```text
resources/js/components/database-export/
resources/js/pages/admin/backup/
resources/js/types/database-export.ts
```

Adicione rotas em `api-routes.ts`.

Registre icone `Archive` em `nav-icons.ts`.

Adicione item de menu em `config/navigation.php` ou no sistema de navegacao equivalente.

### 14.6 Configurar schedule

```php
use Illuminate\Support\Facades\Schedule;

foreach (['backup:run' => '02:00', 'backup:clean' => '03:00', 'backup:monitor' => '04:00'] as $command => $time) {
    Schedule::command($command)
        ->dailyAt($time)->timezone('UTC')
        ->createMutexNameUsing('framework/schedule-backup-lifecycle')
        ->withoutOverlapping()->onOneServer();
}
Schedule::command('database-exports:prune')
    ->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
```

Mantenha uma única declaração por tarefa em `routes/console.php`. Para executar o scheduler no Windows local, siga o [contrato de agenda OPS](../stabilization/ops-scheduler-runtime.md), incluindo cache persistente e processo externo.

### 14.7 Configurar queue worker

Em producao, use Supervisor, Laravel Forge, Laravel Cloud ou outro process manager:

```bash
php artisan queue:work --sleep=1 --tries=3 --backoff=30 --timeout=900
```

Exemplo para produção com PCNTL e supervisor. O job de dump limita-se a uma tentativa e 900 segundos, com dumper de 840 segundos. `retry_after`/visibilidade precisa exceder o orçamento externo: mínimo de 1.020 segundos. Para Windows local use o Listener de 960 segundos descrito no [contrato OPS](../stabilization/ops-worker-runtime.md); nenhum serviço de produção foi operado nesta validação.

### 14.8 Rodar migrations e checks

```bash
php artisan migrate
php artisan list database-exports
php artisan schedule:list
php artisan test --compact tests/Feature/Backup/DatabaseExportApiTest.php
```

---

## 15. Checklist de producao

- [ ] `spatie/laravel-backup` instalado.
- [ ] Provider do modulo registrado.
- [ ] Migrations executadas.
- [ ] `database-exports:prune` aparece em `php artisan list database-exports`.
- [ ] Os oito eventos de manutenção, incluindo `backup:run`, `backup:clean` e `backup:monitor`, aparecem em `php artisan schedule:list`.
- [ ] Processo externo do scheduler configurado para o ambiente adotado; no Windows, seguir o contrato OPS-002.
- [ ] Queue worker configurado e supervisionado.
- [ ] Disco de backups e dumps e privado.
- [ ] `.env`, `.env.*` e `.git` estao excluidos do backup.
- [ ] `BACKUP_ARCHIVE_PASSWORD` definido em producao.
- [ ] Binarios `mysqldump`, `pg_dump` ou `sqlite3` disponiveis.
- [ ] Roles/policies revisadas.
- [ ] Download de backups restrito a usuarios confiaveis.
- [ ] Tabelas grandes configuradas em `dump.exclude_tables`.
- [ ] Retencao de backups revisada em `config/backup.php`.
- [ ] Expiracao de dumps revisada em `config/database-exports.php`.
- [ ] Testes da feature passando.

---

## 16. Problemas comuns

### Dump fica `pending`

Causa provavel: worker de fila parado.

Solucao:

```bash
php artisan queue:work --timeout=900 --tries=3 --backoff=30 --sleep=1
```

### Job falha dizendo que `mysqldump` nao existe

Causa provavel: binario nao esta no `PATH`.

Solucao:

- Instale o cliente do banco.
- Garanta que o usuario do PHP/worker consegue executar o binario.

### Download retorna 403

Causas provaveis:

- Usuario nao tem role `developer` ou `admin`.
- Dump pertence a outro usuario.

### Download retorna 404

Causas provaveis:

- Status nao e `completed`.
- `file_path` esta vazio.
- Arquivo foi removido do storage.

### Download retorna 410

Causa: dump expirado.

Solucao: gerar novo dump.

### Dump completo ficou grande demais

Solucoes:

- Adicionar tabelas em `database.connections.*.dump.exclude_tables`.
- Gerar dump apenas de tabelas especificas.
- Reduzir retencao em `database-exports.expires_after_hours`.
- Dimensionar o disco local privado e o conjunto de orcamentos antes de gerar; S3 e opcao apenas para backup automatico, nao para este job de dump.

### Backup automatico nao aparece no historico

Causas provaveis:

- `backup:run` nao executou.
- Scheduler nao esta rodando.
- Evento do Spatie nao foi disparado.
- Provider do modulo nao esta registrado.

Verificacao:

```bash
php artisan backup:run
php artisan schedule:list
php artisan tinker --execute 'App\Modules\Backup\Models\BackupHistory::count();'
```



### Retenção agendada e histórico (OPS-002)

A estratégia configurada conserva a política do SDK, mas admite apenas nomes finais reconhecidos no namespace direto. ZIP em staging, subpastas e nomes customizados fora do padrão são preservados. Exclusão sem ausência confirmada falha imediatamente. Após cleanup, somente caminhos ausentes do disco/namespace do evento são limpos no histórico; a auditoria de sucesso, nome/tamanho e falhas é preservada, incluindo alterações concorrentes por CAS. O monitor verifica idade/volume conforme SDK; integridade e restauração não são comprovadas por esses checks. [Detalhes e operação](../stabilization/ops-scheduler-runtime.md).
