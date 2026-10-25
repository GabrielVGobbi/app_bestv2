> **Cópia histórica.** O contrato vigente deste projeto está na [documentação canônica de Exports](../.start_kit.obs/Exports/async-export-system.md), selecionada por [AGENTS.md](../../AGENTS.md). Esta cópia foi preservada como referência anterior; seus exemplos não substituem autorização, isolamento, filas ou downloads atuais. Para manutenção, altere a fonte canônica.

# Sistema de Exportação Assíncrona

Documentação completa do sistema de exportação assíncrona integrado ao DataTable. Permite exportar qualquer model em CSV, XLSX ou TSV, processando em background sem travar o browser, com rastreamento de progresso em tempo real e download seguro por link temporário.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Fluxo Completo](#2-fluxo-completo)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Backend](#4-backend)
   - [Configuração](#41-configuração)
   - [Banco de Dados](#42-banco-de-dados)
   - [Trait Exportable](#43-trait-exportable)
   - [Model Export](#44-model-export)
   - [Job ProcessExport](#45-job-processexport)
   - [Notificação](#46-notificação)
   - [API](#47-api)
   - [Download](#48-download)
   - [Limpeza Automática](#49-limpeza-automática)
5. [Frontend](#5-frontend)
   - [Tipos TypeScript](#51-tipos-typescript)
   - [Hook useExport](#52-hook-useexport)
   - [Componente ExportDialog](#53-componente-exportdialog)
   - [Componente ExportStatus](#54-componente-exportstatus)
   - [Página de Histórico](#55-página-de-histórico)
6. [Como Adicionar Exportação a um Novo Model](#6-como-adicionar-exportação-a-um-novo-model)
7. [Referência de Configuração](#7-referência-de-configuração)
8. [Referência da API](#8-referência-da-api)
9. [Testes](#9-testes)
10. [Dicas e Armadilhas](#10-dicas-e-armadilhas)
11. [Checklist para Implementar em Outro Projeto](#11-checklist-para-implementar-em-outro-projeto)

---

## 1. Visão Geral

### Problema resolvido

Exportações síncronas (gerar o arquivo no request HTTP) falham com conjuntos grandes de dados porque:
- Timeout do PHP/servidor geralmente em 30–60s
- Consumo de memória proporcional ao total de registros
- O browser trava aguardando a resposta

### Solução

O export é **enfileirado como Job**. O request HTTP apenas cria o registro na tabela `exports` e retorna imediatamente. O processamento acontece no worker de queue, usando `chunkById(500)` para manter memória constante e **OpenSpout** para escrita streaming. O frontend faz polling a cada 3 segundos no endpoint de status e notifica o usuário com toast quando o arquivo estiver pronto.

### Capacidades

| Recurso | Detalhe |
|---------|---------|
| Formatos | CSV, XLSX, TSV |
| Tamanho | Ilimitado (chunk processing) |
| Memória | ~constante, independente do volume |
| Estado | Persiste em localStorage — sobrevive a page refresh |
| Segurança | Arquivos em disco privado, download autenticado, allowlist de models |
| Expiração | Configurável (padrão: 1 hora) |
| Limpeza | Automática via `exports:prune` (agendado diariamente) |

---

## 2. Fluxo Completo

```
Usuário clica "Exportar"
        │
        ▼
[Dialog] Escolhe colunas + formato
        │
        ▼
POST /api/v1/exports
        │
        ├── Cria registro exports (status=pending)
        ├── Dispatch ProcessExport job
        └── Retorna ExportResource (201)
                │
                ▼
        [localStorage] Persiste StoredExport
                │
                ▼
        [setInterval 3s] Polling GET /api/v1/exports/{uuid}
                │
                ├── status=processing → atualiza barra de progresso
                │
                └── status=completed
                        │
                        ├── toast.success com botão "Baixar"
                        ├── Clique "Baixar" → download + remove dos recentes
                        └── Fica disponível em /exportacoes por 1h

[Queue Worker]
        │
        ▼
ProcessExport::handle()
        │
        ├── markAsProcessing()
        ├── resolveExportColumns() — intersecta com $exportable
        ├── buildExportQuery() — aplica search/filters/sort
        ├── $query->count() → total_records
        ├── OpenSpout writer (temp file)
        ├── chunkById(500) → escreve linhas + incrementProcessed()
        ├── Storage::put() para disco privado
        ├── markAsCompleted()
        └── notify(ExportReadyNotification)
```

---

## 3. Estrutura de Arquivos

```
app/
├── Concerns/
│   └── Exportable.php                          # Trait para models exportáveis
├── Console/Commands/
│   └── PruneExports.php                        # Limpeza diária de exports expirados
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── ExportApiController.php         # API: columns, store, show, index
│   │   └── ExportController.php                # Web: histórico (Inertia) + download
│   ├── Requests/
│   │   └── CreateExportRequest.php             # Validação de payload
│   └── Resources/
│       └── ExportResource.php                  # JSON transform
├── Jobs/
│   └── ProcessExport.php                       # Job assíncrono principal
├── Models/
│   └── Export.php                              # Registro de cada exportação
└── Notifications/
    └── ExportReadyNotification.php             # Notificação no banco ao concluir

config/
└── exports.php                                 # Configuração central

database/
├── factories/
│   └── ExportFactory.php                       # Factory com states
└── migrations/
    └── 2026_05_06_220142_create_exports_table.php

routes/
├── api.php                                     # /api/v1/exports/*
└── web.php                                     # /exportacoes + /exports/{uuid}/download

resources/js/
├── types/
│   └── export.ts                               # Tipos TypeScript
├── components/data-table/
│   ├── hooks/
│   │   └── use-export.ts                       # Hook: localStorage + polling + toasts
│   └── shell/
│       ├── data-table-export-dialog.tsx        # Dialog: seleção de colunas e formato
│       └── data-table-export-status.tsx        # Popover: progresso + downloads pendentes
└── pages/app/exports/
    └── index.tsx                               # Página /exportacoes (histórico)

tests/
├── Feature/Export/
│   ├── ExportApiControllerTest.php
│   ├── ExportDownloadTest.php
│   └── ProcessExportTest.php
└── Unit/Export/
    ├── ExportableTraitTest.php
    └── ExportModelTest.php
```

---

## 4. Backend

### 4.1 Configuração

**`config/exports.php`**

```php
return [
    // Models que podem ser exportados. Adicione aqui para liberar.
    'allowed_models' => [
        \App\Models\User::class,
    ],

    // Disco do Laravel Storage (DEVE ser privado, nunca 'public')
    'disk' => 'local',

    // Diretório dentro do disco
    'directory' => 'exports',

    // Horas até o arquivo expirar após conclusão
    'expires_after_hours' => 1,

    // Registros por chunk no processamento do job
    'chunk_size' => 500,
];
```

> **Segurança**: o `disk` deve ser sempre privado. Nunca use `'public'` — o download é feito pelo Laravel via stream autenticado, não por URL direta do storage.

---

### 4.2 Banco de Dados

A tabela `exports` registra cada solicitação de exportação:

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `uuid` | string unique | Identificador público (usado nas rotas) |
| `user_id` | FK | Dono da exportação |
| `model_class` | string | Classe do model exportado |
| `status` | string | `pending` → `processing` → `completed`/`failed` |
| `format` | string | `csv`, `xlsx` ou `tsv` |
| `columns` | json | Colunas selecionadas (vazio = todas) |
| `filters` | json | Filtros da tabela no momento da exportação |
| `sort_column` | string | Coluna de ordenação |
| `sort_direction` | string | `asc` ou `desc` |
| `search` | string | Termo de busca |
| `file_path` | string | Caminho no Storage (preenchido ao concluir) |
| `file_name` | string | Nome amigável do arquivo |
| `file_size` | bigint | Tamanho em bytes |
| `total_records` | bigint | Total de registros exportados |
| `processed_records` | bigint | Registros já escritos (progresso) |
| `error_message` | text | Mensagem de erro se `status=failed` |
| `started_at` | datetime | Quando o job começou |
| `completed_at` | datetime | Quando o arquivo foi gerado |
| `expires_at` | datetime | Quando o arquivo expira |

---

### 4.3 Trait Exportable

**`app/Concerns/Exportable.php`**

Adicione este trait a qualquer model que precise de exportação.

```php
use App\Concerns\Exportable;

class Product extends Model
{
    use Exportable;

    protected array $exportable = [
        'name'        => 'Nome',
        'sku'         => 'SKU',
        'price'       => 'Preço',
        'stock'       => 'Estoque',
        'created_at'  => 'Criado em',
    ];
}
```

O array `$exportable` é `[chave_do_atributo => label_para_o_cabeçalho]`.

**Métodos disponíveis:**

```php
// Retorna o array $exportable completo
Product::getExportableColumns();
// → ['name' => 'Nome', 'sku' => 'SKU', ...]

// Intersecta com os solicitados (retorna todos se vazio)
Product::resolveExportColumns(['name', 'price']);
// → ['name' => 'Nome', 'price' => 'Preço']

// Constrói a query com search/filters/sort (usa scopes existentes)
Product::buildExportQuery(
    filters: ['status' => 'active'],
    search: 'tênis',
    sort: 'name',
    direction: 'asc'
);
```

> **Dependência**: `buildExportQuery` chama `->search()`, `->apiFilters()` e `->apiSort()`. O model deve usar o trait `Searchable` (ou equivalente) que fornece esses scopes.

---

### 4.4 Model Export

**`app/Models/Export.php`**

Constantes de status:

```php
Export::STATUS_PENDING     // 'pending'
Export::STATUS_PROCESSING  // 'processing'
Export::STATUS_COMPLETED   // 'completed'
Export::STATUS_FAILED      // 'failed'
```

Métodos de ciclo de vida (chamados pelo job):

```php
$export->markAsProcessing();
// Define status=processing e started_at=now()

$export->markAsCompleted($path, $fileName, $fileSize, $totalRecords);
// Define status=completed, file_path, file_name, file_size, total_records,
// processed_records=total, completed_at=now(), expires_at=now()+config

$export->markAsFailed('Mensagem de erro');
// Define status=failed e error_message

$export->incrementProcessed(500);
// Incrementa processed_records (chamado a cada chunk)
```

Métodos de consulta:

```php
$export->isExpired();   // true se expires_at < now()
$export->isActive();    // true se pending ou processing
$export->isOwnedBy($user); // true se user_id === $user->id
```

O model usa `HasUuidIdentifier` — a route key é `uuid`, não `id`. Isso significa que rotas como `/exports/{export}/download` resolvem pelo UUID.

---

### 4.5 Job ProcessExport

**`app/Jobs/ProcessExport.php`**

Implementa `ShouldQueue` e `ShouldBeUnique`. O `uniqueId()` retorna o UUID do export, prevenindo que o mesmo job seja enfileirado duas vezes.

**Fluxo do `handle()`:**

```
1. Fresh do model (garante dados atualizados)
2. Só processa se status=pending (idempotente)
3. markAsProcessing()
4. resolveExportColumns() — filtra colunas
5. buildExportQuery() — aplica filtros/busca/ordenação
6. count() → update total_records
7. Cria writer OpenSpout (CSV/XLSX/TSV) em arquivo temp
8. Escreve linha de cabeçalho
9. chunkById(500) → escreve linhas + incrementProcessed()
10. writer.close()
11. Storage::put() para o disco configurado
12. markAsCompleted()
13. notify(ExportReadyNotification)
14. finally: deleta arquivo temp
```

**Tratamento de falha:**

```php
public function failed(Throwable $exception): void
{
    $this->export->markAsFailed($exception->getMessage());
}
```

Se o job falhar (inclusive após todas as tentativas), `failed()` é chamado automaticamente pelo Laravel e o status é atualizado.

**Formatos suportados:**

| Formato | Writer | Observação |
|---------|--------|------------|
| `csv` | `OpenSpout\Writer\CSV\Writer` | Separador `,` |
| `tsv` | `OpenSpout\Writer\CSV\Writer` | Separador `\t` |
| `xlsx` | `OpenSpout\Writer\XLSX\Writer` | Streaming, baixo uso de memória |

---

### 4.6 Notificação

**`app/Notifications/ExportReadyNotification.php`**

Enviada ao usuário via canal `database` quando o export conclui com sucesso. Requer a tabela `notifications` — se não existir, execute:

```bash
php artisan notifications:table
php artisan migrate
```

Os dados armazenados na notificação:

```php
[
    'export_uuid'    => '...',
    'file_name'      => 'export-uuid.csv',
    'format'         => 'csv',
    'total_records'  => 1500,
]
```

**Para habilitar email** (descomentado no arquivo):

```php
public function via(object $notifiable): array
{
    return ['database', 'mail'];
}

public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Exportação concluída')
        ->line("Seu arquivo {$this->export->file_name} está pronto.")
        ->action('Baixar arquivo', route('exports.download', $this->export->uuid))
        ->line('O link expirará em ' . config('exports.expires_after_hours') . ' hora(s).');
}
```

---

### 4.7 API

Todas as rotas da API estão sob `/api/v1/exports` com middleware `auth:sanctum`.

#### `GET /api/v1/exports`

Lista os últimos 10 exports não expirados do usuário autenticado.

```json
{
  "data": [
    {
      "uuid": "abc-123",
      "status": "completed",
      "format": "csv",
      "model_class": "App\\Models\\User",
      "file_name": "export-abc-123.csv",
      "file_size": 48200,
      "total_records": 1500,
      "processed_records": 1500,
      "download_url": "https://app.com/exports/abc-123/download",
      "expires_at": "2026-05-06T23:00:00.000000Z",
      "created_at": "2026-05-06T22:00:00.000000Z",
      "completed_at": "2026-05-06T22:00:45.000000Z"
    }
  ]
}
```

> `download_url` só é retornado quando `status=completed` e o export não expirou.
> `error_message` só é retornado quando `status=failed`.

#### `POST /api/v1/exports`

Cria uma nova exportação. Retorna HTTP 201.

**Payload:**

```json
{
  "model_class": "App\\Models\\User",
  "format": "csv",
  "columns": ["name", "email"],
  "filters": { "role": "admin" },
  "search": "joão",
  "sort": "name",
  "sort_direction": "asc"
}
```

| Campo | Obrigatório | Descrição |
|-------|-------------|-----------|
| `model_class` | Sim | Deve estar no `allowed_models` |
| `format` | Sim | `csv`, `xlsx` ou `tsv` |
| `columns` | Não | Vazio = exporta todas as colunas do `$exportable` |
| `filters` | Não | Filtros ativos da tabela |
| `search` | Não | Termo de busca |
| `sort` | Não | Coluna de ordenação |
| `sort_direction` | Não | `asc` ou `desc` |

#### `GET /api/v1/exports/{uuid}`

Retorna o status atual de um export. Retorna 403 se não for do usuário, 404 se não existir.

Usado pelo polling do frontend a cada 3 segundos.

#### `GET /api/v1/exports/columns?model_class=App\Models\User`

Retorna as colunas exportáveis do model.

```json
{
  "data": [
    { "key": "name", "label": "Nome" },
    { "key": "email", "label": "Email" },
    { "key": "created_at", "label": "Criado em" }
  ]
}
```

---

### 4.8 Download

**`GET /exports/{export}/download`** — rota web, autenticada.

Guarda comportamento antes de stream:

| Condição | Resposta |
|----------|----------|
| Não é dono | 403 |
| Status ≠ `completed` | 404 |
| Arquivo expirado | 410 |
| Arquivo ausente no disco | 404 |

O download usa `Storage::disk()->download()` — streaming via `StreamedResponse`, sem carregar o arquivo inteiro em memória.

---

### 4.9 Limpeza Automática

**`php artisan exports:prune`**

Remove exports com `expires_at < now()`:
1. Deleta o arquivo físico do storage
2. Deleta o registro da tabela

Agendado em `routes/console.php`:

```php
Schedule::command('exports:prune')->daily();
```

Para o scheduler rodar automaticamente, configure um cron no servidor:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Ou use o worker do Laravel Cloud/Forge.

---

## 5. Frontend

### 5.1 Tipos TypeScript

**`resources/js/types/export.ts`**

```typescript
// Status do export no servidor
type ExportStatus = 'pending' | 'processing' | 'completed' | 'failed';

// Formato do arquivo
type ExportFormat = 'csv' | 'xlsx' | 'tsv';

// Resposta da API (ExportResource)
interface ExportRecord {
    uuid: string;
    status: ExportStatus;
    format: ExportFormat;
    model_class?: string;
    file_name: string | null;
    file_size: number | null;
    total_records: number | null;
    processed_records: number;
    error_message?: string | null;   // Só presente quando failed
    download_url?: string | null;    // Só presente quando completed + não expirado
    expires_at: string | null;
    created_at: string;
    completed_at: string | null;
}

// O que é salvo no localStorage
interface StoredExport {
    uuid: string;
    status: ExportStatus;
    modelClass: string;
    format: ExportFormat;
    file_name: string | null;
    download_url: string | null;
    total_records: number | null;
    processed_records: number;
    expires_at: string | null;
    createdAt: number;        // Unix ms — usado para pruning de pending expirados
    downloadedAt?: number;    // Definido quando o usuário baixa — some dos recentes
}

// Config passada para o DataTable
interface DataTableExportConfig {
    modelClass: string;          // 'App\\Models\\User'
    columnsEndpoint: string;     // '/api/v1/exports/columns'
    enabled?: boolean;           // default: true
}
```

---

### 5.2 Hook useExport

**`resources/js/components/data-table/hooks/use-export.ts`**

O hook central que gerencia todo o ciclo de vida do export no frontend.

```typescript
import { useExport } from '@/components/data-table/hooks/use-export';

const { activeExports, hasActiveExports, startExport, markAsDownloaded } = useExport();
```

**Retorno:**

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `activeExports` | `StoredExport[]` | Exports visíveis no indicador (excluindo os já baixados) |
| `hasActiveExports` | `boolean` | Há algum pending ou processing |
| `startExport` | `(payload) => Promise<void>` | Inicia um export via API |
| `markAsDownloaded` | `(uuid: string) => void` | Marca como baixado e some dos recentes |

**Comportamento interno:**

1. **Inicialização**: lê o localStorage e prune exports expirados
2. **Polling**: inicia um `setInterval(3000)` enquanto houver pending/processing; para automaticamente quando todos concluem
3. **Pruning automático**:
   - Exports com `expires_at` passado são removidos (arquivos expirados)
   - Exports pending/processing sem `expires_at` são removidos após 1 hora (`MAX_EXPORT_AGE_MS`) — previne polling infinito se o worker parar
4. **Toast ao concluir**: `toast.success` com botão "Baixar" que chama `markAsDownloaded` ao clicar
5. **Remoção em 404**: se o servidor retornar 404 para um UUID, ele é removido do localStorage
6. **Persistência**: toda atualização de estado é sincronizada no localStorage — sobrevive a page refresh

**Chave do localStorage:** `data-table-exports`

Para limpar manualmente em desenvolvimento:
```javascript
localStorage.removeItem('data-table-exports')
```

---

### 5.3 Componente ExportDialog

**`resources/js/components/data-table/shell/data-table-export-dialog.tsx`**

Dialog modal com:
- Seleção de formato via `ToggleGroup` (CSV padrão)
- Lista de colunas com checkboxes (busca as colunas do endpoint da API)
- "Selecionar todas / Limpar"
- Opção "Usar filtros ativos" (aparece só se há filtros ativos)
- Estado de loading durante a submissão
- Erro inline em caso de falha

As colunas são buscadas via `useQuery` do TanStack Query com `staleTime: 5min` — só busca ao abrir o dialog pela primeira vez.

**Prop `enabled`:** se `false`, o DataTable não renderiza o botão de exportação.

---

### 5.4 Componente ExportStatus

**`resources/js/components/data-table/shell/data-table-export-status.tsx`**

Popover no toolbar que só aparece quando há exports não-baixados no `activeExports`.

Exibe para cada export:
- Ícone de status (spinner animado / check verde / X vermelho)
- Nome do arquivo ou "Preparando..."
- Badge de formato
- Contagem de registros ou progresso
- Barra de progresso para processing
- Link "Baixar arquivo" para completed (chama `onDownload` ao clicar → some dos recentes)

Rodapé com link "Ver histórico completo" → `/exportacoes`.

---

### 5.5 Página de Histórico

**`/exportacoes`** — rota web autenticada, renderizada via Inertia.

Exibe os últimos 50 exports do usuário (incluindo expirados) com:
- Badge de status colorido
- Model exportado + badge de formato
- Nome do arquivo (fonte monospace)
- Número de registros
- Tamanho do arquivo
- Data de criação e expiração
- Botão "Baixar" (disponível apenas para completed não-expirado)

Para exports expirados, a célula de download mostra "Expirado". Para exports failed/pending/processing, mostra "—".

---

## 6. Como Adicionar Exportação a um Novo Model

São exatamente **3 passos**:

### Passo 1 — Adicionar o trait ao Model

```php
<?php

namespace App\Models;

use App\Concerns\Exportable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Exportable; // ← adicionar

    // Declarar as colunas exportáveis: ['atributo' => 'Label']
    protected array $exportable = [
        'name'        => 'Nome',
        'sku'         => 'SKU',
        'price'       => 'Preço (R$)',
        'stock'       => 'Estoque',
        'category'    => 'Categoria',
        'created_at'  => 'Criado em',
    ];
}
```

> O model **deve** ter os scopes `search()`, `apiFilters()` e `apiSort()` (via trait Searchable ou equivalente), pois `buildExportQuery` os chama.

### Passo 2 — Adicionar ao allowlist

**`config/exports.php`:**

```php
'allowed_models' => [
    \App\Models\User::class,
    \App\Models\Product::class, // ← adicionar
],
```

Sem isso, o endpoint retornará HTTP 422.

### Passo 3 — Configurar o DataTable da página

```typescript
import type { DataTableExportConfig } from '@/components/data-table';

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Models\\Product',    // double-escaped
    columnsEndpoint: '/api/v1/exports/columns',
};

// No JSX:
<DataTable<Product>
    // ... props existentes ...
    features={{ export: true }}         // ← habilitar feature
    exportConfig={exportConfig}         // ← passar config
/>
```

Pronto. O botão "Exportar" aparecerá no toolbar do DataTable.

---

## 7. Referência de Configuração

### `config/exports.php`

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `allowed_models` | `[User::class]` | Classes que podem ser exportadas (allowlist de segurança) |
| `disk` | `'local'` | Disco do Laravel Storage. Use `'local'` ou um disco S3 privado |
| `directory` | `'exports'` | Subdiretório dentro do disco |
| `expires_after_hours` | `1` | Horas até o arquivo expirar após conclusão |
| `chunk_size` | `500` | Registros por iteração no chunkById |

### Usando S3 para armazenamento

```php
'disk' => 's3',  // Certifique-se que o bucket é privado (sem ACL pública)
```

O `ExportController::download` usa `Storage::disk()->download()`, que funciona com qualquer driver do Laravel — inclusive S3, gerando uma stream direta sem pré-download.

### Ajustando o chunk_size

| Volume esperado | Recomendação |
|-----------------|--------------|
| < 10k registros | 500 (padrão) |
| 10k–100k registros | 500–1000 |
| > 100k registros | 1000–2000 (testar memória) |

---

## 8. Referência da API

### Endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| `GET` | `/api/v1/exports` | Sanctum | Lista exports do usuário (últimos 10, não-expirados) |
| `POST` | `/api/v1/exports` | Sanctum | Cria export + dispatch job |
| `GET` | `/api/v1/exports/columns` | Sanctum | Lista colunas exportáveis de um model |
| `GET` | `/api/v1/exports/{uuid}` | Sanctum | Status de um export específico |
| `GET` | `/exportacoes` | Web | Histórico (Inertia) |
| `GET` | `/exports/{uuid}/download` | Web | Download do arquivo |

### Adicionando Rotas API

Em `routes/api.php`, dentro do grupo `auth:sanctum`:

```php
Route::prefix('v1')->group(function () {
    // ... rotas existentes ...

    Route::prefix('exports')->as('exports.')->group(function () {
        Route::get('/', [ExportApiController::class, 'index'])->name('index');
        Route::post('/', [ExportApiController::class, 'store'])->name('store');
        Route::get('/columns', [ExportApiController::class, 'columns'])->name('columns');
        Route::get('/{export}', [ExportApiController::class, 'show'])->name('show');
    });
});
```

> A rota `/columns` **deve vir antes** de `/{export}` para não ser interpretada como um UUID.

---

## 9. Testes

### Executar todos os testes de export

```bash
php artisan test --compact tests/Feature/Export tests/Unit/Export
```

### Executar um teste específico

```bash
php artisan test --compact --filter=test_job_generates_csv_and_marks_export_completed
```

### Cobertura de testes

**`tests/Feature/Export/ExportApiControllerTest.php`** (7 testes):
- Cria export e faz dispatch do job
- Rejeita model não permitido (422)
- Requer autenticação (401)
- Retorna export pelo owner (200)
- Nega acesso de não-owner (403)
- Lista só exports do usuário atual
- Exclui exports expirados do listing

**`tests/Feature/Export/ExportDownloadTest.php`** (4 testes):
- Stream do arquivo para o owner
- 403 para não-owner
- 404 para export não-concluído
- 410 para export expirado

**`tests/Feature/Export/ProcessExportTest.php`** (3 testes):
- Gera CSV e marca como completed
- Marca como failed em exceção
- Exporta todas as colunas quando nenhuma é selecionada

**`tests/Unit/Export/ExportableTraitTest.php`** (cobertura do trait):
- `getExportableColumns()`
- `resolveExportColumns()` com seleção parcial
- `resolveExportColumns()` retorna todas quando vazio

**`tests/Unit/Export/ExportModelTest.php`** (cobertura do model):
- `isExpired()` — expirado e não-expirado
- `isActive()` — pending, processing, completed, failed
- `isOwnedBy()` — owner e não-owner
- `markAsProcessing()`, `markAsCompleted()`, `markAsFailed()`, `incrementProcessed()`

### Escrevendo testes para um novo model exportável

```php
public function test_exports_products(): void
{
    Storage::fake('local');
    Notification::fake();

    Product::factory()->count(10)->create();
    $user = User::factory()->create();

    $export = Export::factory()->create([
        'user_id'     => $user->id,
        'model_class' => Product::class,
        'format'      => 'csv',
        'columns'     => ['name', 'sku'],
    ]);

    (new ProcessExport($export))->handle();

    $export->refresh();

    $this->assertEquals(Export::STATUS_COMPLETED, $export->status);
    $this->assertEquals(10, $export->total_records);
    Storage::disk('local')->assertExists($export->file_path);
    Notification::assertSentTo($user, ExportReadyNotification::class);
}
```

---

## 10. Dicas e Armadilhas

### Worker de Queue é obrigatório

O export NÃO processa automaticamente em ambiente local sem o worker rodando:

```bash
php artisan queue:listen   # Desenvolvimento (reinicia ao alterar código)
php artisan queue:work     # Produção
```

Em produção (Forge, Envoyer, etc.), configure o worker como daemon supervisionado.

### `chunkById` requer coluna `id`

O `chunkById()` do Eloquent requer que o model tenha uma coluna `id` auto-increment. Se usar UUID como primary key, use `chunk()` no lugar:

```php
// Em ProcessExport.php, se o model não tem id auto-increment:
$query->chunk(config('exports.chunk_size', 500), function ($records) use (...) {
    // ...
});
```

Porém `chunk()` pode ter inconsistências com dados que mudam durante o processamento. Prefira `chunkById`.

### Colunas acessores (Accessors) no export

O export acessa atributos diretamente via `$record->{$key}`. Accessors do Eloquent funcionam normalmente:

```php
protected array $exportable = [
    'full_name'  => 'Nome completo',  // Accessor: getFullNameAttribute()
    'status_label' => 'Status',       // Accessor que formata o enum
];
```

### Datas formatadas automaticamente

No job, `DateTimeInterface` é formatado automaticamente:

```php
if ($value instanceof \DateTimeInterface) {
    $value = $value->format('Y-m-d H:i:s');
}
```

Se precisar de outro formato, sobrescreva o método ou use um accessor.

### Polling infinito

Se um export ficou `pending` indefinidamente (worker parado), o frontend continuará fazendo polling para sempre. Isso é evitado por `MAX_EXPORT_AGE_MS = 1 hora` em `use-export.ts` — após 1 hora, entradas pending/processing sem `expires_at` são purgadas do localStorage.

Se o problema aparecer em desenvolvimento, limpe o localStorage:
```javascript
localStorage.removeItem('data-table-exports')
```

### ShouldBeUnique evita duplicatas

O job implementa `ShouldBeUnique` com `uniqueId()` retornando o UUID do export. Isso garante que o mesmo export não seja enfileirado duas vezes (ex.: duplo clique no botão). O lock é liberado automaticamente após o job finalizar.

### Segurança: allowlist de models

O campo `model_class` no payload da API é validado contra `config('exports.allowed_models')`. Nunca remova essa validação — sem ela, um usuário malicioso poderia exportar qualquer model da aplicação.

### Expiração vs. deleção

O arquivo expira (`expires_at`) antes de ser deletado do storage. Entre a expiração e a limpeza diária (`exports:prune`):
- O frontend não exibe mais o link de download (pois `download_url` fica null na API)
- Download retorna HTTP 410 (Gone)
- O arquivo ainda ocupa espaço no disco até a limpeza

Se precisar de expiração mais agressiva, reduza `expires_after_hours` e aumente a frequência do `exports:prune`.

### Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|---------------|---------|
| Export fica `pending` para sempre | Worker não está rodando | `php artisan queue:listen` |
| Erro 500 no job | Model não usa scopes `search`/`apiFilters`/`apiSort` | Adicionar trait Searchable ao model |
| Export concluído mas não notifica | Tabela `notifications` não existe | `php artisan notifications:table && php artisan migrate` |
| Botão "Exportar" não aparece | `features={{ export: true }}` ausente ou `exportConfig` não passado | Verificar props do DataTable |
| Colunas não carregam no dialog | Model não está no `allowed_models` | Adicionar ao `config/exports.php` |
| Download retorna 403 | Export pertence a outro usuário | Verificar autenticação |
| Download retorna 410 | Arquivo expirado | Gerar nova exportação |

---

## 11. Checklist para Implementar em Outro Projeto

Use esta lista ao portar o sistema para um novo projeto Laravel + Inertia (React).

### Dependências

```bash
# PHP
composer require openspout/openspout

# Tabela de notificações (se não existir)
php artisan notifications:table
php artisan migrate
```

### Backend (copiar/adaptar)

- [ ] `config/exports.php` — ajustar `allowed_models`, `disk`, `expires_after_hours`
- [ ] Migration `create_exports_table` — rodar `php artisan migrate`
- [ ] `app/Concerns/Exportable.php` — copiar integralmente
- [ ] `app/Models/Export.php` — ajustar namespace do `HasUuidIdentifier` se diferente
- [ ] `database/factories/ExportFactory.php`
- [ ] `app/Jobs/ProcessExport.php` — copiar integralmente
- [ ] `app/Notifications/ExportReadyNotification.php`
- [ ] `app/Http/Resources/ExportResource.php`
- [ ] `app/Http/Requests/CreateExportRequest.php`
- [ ] `app/Http/Controllers/Api/ExportApiController.php`
- [ ] `app/Http/Controllers/ExportController.php`
- [ ] `app/Console/Commands/PruneExports.php`
- [ ] Registrar o comando em `routes/console.php`:
  ```php
  Schedule::command('exports:prune')->daily();
  ```
- [ ] Adicionar rotas em `routes/api.php` e `routes/web.php`
- [ ] Para cada model exportável: adicionar `use Exportable` + `$exportable` + entrar no `allowed_models`

### Frontend (copiar)

- [ ] `resources/js/types/export.ts`
- [ ] `resources/js/components/data-table/hooks/use-export.ts`
- [ ] `resources/js/components/data-table/shell/data-table-export-dialog.tsx`
- [ ] `resources/js/components/data-table/shell/data-table-export-status.tsx`
- [ ] `resources/js/pages/app/exports/index.tsx`
- [ ] Adicionar rotas de exports ao objeto `apiRoutes` em `lib/api/api-routes.ts`:
  ```typescript
  exports: {
      index:   '/api/v1/exports',
      store:   '/api/v1/exports',
      show:    (uuid: string) => `/api/v1/exports/${uuid}`,
      columns: '/api/v1/exports/columns',
  },
  ```
- [ ] Adicionar `export?: boolean` em `DataTableFeatures` (tipos do DataTable)
- [ ] Adicionar `DataTableExportConfig` interface aos tipos
- [ ] Adicionar `exportConfig?` prop ao DataTable, shell e toolbar
- [ ] Integrar `useExport` e os dois componentes no toolbar
- [ ] Adicionar link `/exportacoes` na navegação

### Verificação

- [ ] Worker rodando: `php artisan queue:listen`
- [ ] Cron configurado para o scheduler
- [ ] Tabela `notifications` existe
- [ ] Disco de storage não é público
- [ ] Model adicionado ao `allowed_models`
- [ ] `features={{ export: true }}` no DataTable da página
- [ ] Testes passando: `php artisan test --compact tests/Feature/Export tests/Unit/Export`
