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

O export é **enfileirado como Job**. O request HTTP apenas cria o registro na tabela `exports` e retorna imediatamente. O processamento acontece no worker de queue, usando `chunk(500)` na ordenação solicitada, com desempate pela chave primária, para manter memória limitada ao lote e **OpenSpout** para escrita streaming. O frontend consulta os status em rodadas sequenciais, com descanso de 3 segundos entre rodadas, e notifica o usuário com toast quando o arquivo estiver pronto.

### Capacidades

| Recurso | Detalhe |
|---------|---------|
| Formatos | CSV, XLSX, TSV |
| Tamanho | Ilimitado (chunk processing) |
| Memória | ~constante, independente do volume |
| Estado | Persiste por identidade em localStorage — F5 restaura somente a conta atual |
| Segurança | Policy e escopo da origem, colunas permitidas, revalidação no job, disco privado e download do proprietário |
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
        ├── Valida modelo/colunas e autoriza policy + escopo da origem
        ├── Cria registro exports (status=pending)
        ├── Dispatch ProcessExport job
        └── Retorna ExportResource (201)
                │
                ▼
        [localStorage] Persiste StoredExport no namespace da conta atual
                │
                ▼
        [Rodada sequencial + descanso 3s] GET /api/v1/exports/{uuid}
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
        ├── Relê solicitante e revalida elegibilidade, policy, modelo e colunas
        ├── Recusa permanente → failed genérico, sem novo arquivo/notificação
        ├── markAsProcessing()
        ├── resolveExportColumns() — intersecta com $exportable
        ├── buildExportQuery(actor: ...) — escopo autorizado + search/filters/sort
        ├── $query->count() → total_records
        ├── OpenSpout writer (temp file)
        ├── chunk(500) na ordem solicitada + chave primária → escreve linhas + incrementProcessed()
        ├── Storage::put() para disco privado
        └── completeProcessing(token) + aviso AppNotification na mesma transação
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

// Após policy e applyExportScope configurados; $actor é o solicitante User.
// Constrói a query autorizada com search/filters/sort (usa scopes existentes).
Product::buildExportQuery(
    actor: $actor,
    filters: ['status' => 'active'],
    search: 'tênis',
    sort: 'name',
    direction: 'asc'
);
```

> **Dependência**: `buildExportQuery` chama `->search()`, `->apiFilters()` e `->apiSort()`. O model deve usar o trait `Searchable` (ou equivalente) que fornece esses scopes.

`authorizedExportQuery(User $actor)` é o ponto comum de autorização usado pelo pedido, pelo catálogo de colunas e pelo processamento. Ele exige a classe na allowlist, `viewAny` na policy real e um `protected static applyExportScope(User $actor, Builder $query): Builder` implementado pelo model. O hook padrão recusa a exportação; adicionar apenas o trait ou a classe à configuração não concede acesso.

User reutiliza `AuthorityService::scopeFunctionalUsers`, a mesma regra da listagem administrativa: hierarquia, catálogo delegável e permissões dos alvos continuam limitando as linhas. Não usar `scopeReadableUsers`, que pertence à administração de autoridade. Client usa o conjunto global existente, após `ClientPolicy::viewAny` (`view-clients`); não há tenant ou proprietário de cliente inferido. A query autorizada alimenta tanto a contagem quanto os chunks. Filtros e busca só a restringem.

O mapa `$exportable` é a allowlist de colunas da origem. `resolveExportColumns()` permanece uma operação de interseção para seus consumidores puros; as fronteiras HTTP e job validam explicitamente nomes e formato da seleção antes de usá-la. Campos secretos não entram no mapa dos models atuais.

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

O job usa `claimProcessing()` para adquirir a lease, `updateProcessing()` para progresso e `completeProcessing()` para conclusão + aviso transacionais. As transições verificam token e prazo; `failed()` é cercado pela geração e identidade da entrada da fila.

Os helpers antigos abaixo permanecem para compatibilidade com outros consumidores e fixtures; não adquirem lease e não substituem o ciclo cercado do job:

```php
$export->markAsProcessing();
// Define status=processing e started_at=now()

$export->markAsCompleted($path, $fileName, $fileSize, $totalRecords);
// Define status=completed, file_path, file_name, file_size, total_records,
// processed_records=total, completed_at=now(), expires_at=now()+config

$export->markAsFailed('Mensagem de erro');
// Define status=failed e error_message

$export->incrementProcessed(500);
// Incrementa processed_records sem adquirir lease
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

Implementa `ShouldQueue`, com orçamento de 600 segundos, três tentativas e backoff de 30/120/300 segundos. A exclusão de execuções usa lease persistida no registro, sem `ShouldBeUnique` no despacho.

**Fluxo do `handle()`:**

1. Relê o export; entrega terminal, geração de despacho antiga ou lease ainda ativa retorna sem efeitos.
2. Relê solicitante, allowlist, colunas e acesso atual da origem antes de consultar os dados. Recusa permanente termina com mensagem pública genérica.
3. Adquire uma lease de 600 segundos sob lock curto, com novo UUID de execução, identidade da entrada da fila e ordinal. Somente o vencedor descarta o candidato anterior e reinicia o progresso.
4. Gera CSV/XLSX/TSV em temporário exclusivo, preservando ordenação, filtros e chunks existentes. Cada transição/progresso verifica token e prazo.
5. Fecha o writer e o stream, publica no candidato privado `exports/{uuid}/{token}.{format}` e confere o tamanho publicado.
6. Sob lock curto, confirma conclusão e persiste o aviso AppNotification na mesma conexão/transação. Falha ao salvar o aviso desfaz a conclusão; broadcast só ocorre após commit e não rebaixa o resultado.
7. Remove temporário e candidato sem conclusão confirmada. Exclusão do pedido ou do proprietário cancela o processamento, sem recriar registro ou aviso.

Falha ordinária expira apenas a própria lease e preserva a política de retentativa da fila. `failed()` reidratado exige geração/identidade/ordinal compatíveis e lease vencida; um callback antigo não cancela outra execução. A mensagem pública não expõe a exceção interna. Entrega já concluída não recria arquivo nem aviso.

`php artisan exports:recover` recupera leases abandonadas e pedidos `pending` sem entrega por pelo menos 1.020 segundos (ou `retry_after` maior), com nova geração de despacho e envio após commit. Exige fila durável database/redis/beanstalkd. Uma falha no despacho na fila deixa `pending` recuperável novamente; repetir imediatamente não duplica um pedido fresco. A recuperação não reconstrói avisos de pedidos antigos concluídos, pois podem ter sido removidos pelo usuário.

A revalidação ocorre antes do processamento; não constitui revogação contínua de acesso durante os chunks. Status e download continuam sujeitos a proprietário, estado e expiração. A [OPS-001](../../stabilization/tasks/OPS-001.md) valida concorrência/queda no Windows com MySQL descartável; retenção geral de órfãos permanece em [EXP-002](../../stabilization/tasks/EXP-002.md). Consulte os [orçamentos e comandos operacionais](../../stabilization/ops-worker-runtime.md) antes de operar filas.

**Formatos suportados:**

| Formato | Writer | Observação |
|---------|--------|------------|
| `csv` | `OpenSpout\Writer\CSV\Writer` | Separador `,` |
| `tsv` | `OpenSpout\Writer\CSV\Writer` | Separador `\t` |
| `xlsx` | `OpenSpout\Writer\XLSX\Writer` | Streaming, baixo uso de memória |

#### Texto não confiável e tipos das células

Cabeçalhos e valores passam pela mesma serialização em `ProcessExport`:

- XLSX: strings são entregues integralmente a células de texto explícitas, inclusive nomes que começam com `=`. Não são inferidas fórmulas a partir dos dados. O writer conserva CR literal no XML; leitores XML normalizam CR e CRLF para LF conforme a [especificação XML, §2.11](https://www.w3.org/TR/xml/#sec-line-ends). Portanto o roundtrip dessas quebras de linha não é uma promessa de identidade binária.
- CSV/TSV: strings que começam com `=`, `+`, `-`, `@` ou as variantes `＝`, `＋`, `－`, `＠` recebem um apóstrofo antes do conteúdo original. A mesma proteção vale após whitespace ASCII inicial (TAB, LF, VT, FF, CR e espaço); TAB, CR ou LF no primeiro byte também recebem o prefixo. Os bytes originais são mantidos depois do marcador.
- Números PHP (`int`/`float`), inclusive negativos e zero, e booleanos mantêm seus tipos intencionais. Strings numéricas não são convertidas: `0012` permanece texto; `-0012` recebe o marcador no CSV/TSV e permanece texto original no XLSX. Datas conservam a formatação existente e valores nulos continuam vazios.
- CSV usa vírgula e TSV usa TAB, ambos com aspas duplas e escape de aspas do writer. Separadores, aspas e quebras de linha dentro de um valor pertencem ao mesmo campo. TSV não usa NUL como delimitador de aspas.

O marcador faz parte do valor em CSV/TSV; consumidores programáticos devem considerar esse contrato. Esses formatos não possuem tipos de célula. A proteção do arquivo gerado não garante comportamento universal de importadores, remoção de prefixos ou novos arquivos salvos pelo Excel: a [OWASP](https://community.owasp.org/attacks/CSV_Injection) documenta essas diferenças. Prefira XLSX quando precisar preservar texto com tipo explícito. Os testes inspecionam os arquivos gerados (XML/tipos no XLSX e bytes/parse no CSV/TSV); não executam fórmulas nem alegam validação em Excel.

---

### 4.6 Notificação

`ProcessExport` mantém o envio de `ExportReadyNotification` após concluir o arquivo privado. O único canal ativo é `ExportReadyChannel`, um adaptador Laravel que relê o export persistido, exige estado `completed` e destinatário proprietário e delega ao `CreateNotificationAction`/`CreateNotificationDTO` existentes.

O aviso é criado em `app_notifications`, com tipo `success`, categoria `export`, título “Exportação concluída”, vínculo ao `Export` e link autenticado de download. Os dados de exibição contêm apenas `export_uuid`, `file_name`, `format` e `total_records`. Não há ação executável adicional: o link usa a rota privada existente, que revalida proprietário, conclusão, expiração e presença do arquivo.

Sino, página e API consomem esse mesmo registro. O evento `NotificationCreated` existente usa o canal privado do destinatário; realtime é opcional. Com realtime desligado, abrir o sino ou recarregar a página consulta os registros persistidos. Uma `BroadcastException` não apaga o aviso já salvo.

Não é necessário criar a tabela Laravel `notifications` para este fluxo, nem existe segundo centro de avisos. O canal `mail` não está ativo e o método de e-mail antigo foi removido: esta entrega não promete envio de e-mail de exportação. Uma futura ativação exige implementação e testes pelo módulo de Email, sem apenas adicionar `mail` ao array `via`.

Conclusão e aviso persistido agora são atômicos na mesma conexão. Se a inserção do aviso falhar, a conclusão é desfeita e a tentativa pode ser repetida. Uma falha de broadcast posterior ao commit conserva ambos. Não há outbox nem garantia universal de entrega de evento realtime; consultar sino/página recupera o estado persistido. Avisos históricos ausentes em pedidos já concluídos não são reconstruídos automaticamente.

---

### 4.7 API

Todas as rotas da API estão sob `/api/v1/exports` com middleware `auth:sanctum`. O consumidor SPA atual usa sessão/cookies. Requisições autenticadas por PAT são recusadas com `403`: exports não possui scope aprovado no catálogo da [SEC-014](../../stabilization/tasks/SEC-014.md#contrato-técnico-adotado-no-lote).

Criar uma exportação e consultar colunas exigem `viewAny` e o escopo da origem, além de autenticação e da classe na allowlist. Recusas retornam `403` sem criar registro ou job. Modelo inválido e seleção inválida retornam `422`. Na execução, o job relê a elegibilidade e o acesso do solicitante conforme a seção 4.5. Consultar status/histórico e baixar arquivos mantém os limites do proprietário; allowlist e ownership não substituem autorização da origem.

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
  "filters": { "email_verified_at": { "operator": "not_null" } },
  "search": "joão",
  "sort": "name",
  "sort_direction": "asc"
}
```

| Campo | Obrigatório | Descrição |
|-------|-------------|-----------|
| `model_class` | Sim | Classe na allowlist, com policy `viewAny` e hook de escopo autorizado |
| `format` | Sim | `csv`, `xlsx` ou `tsv` |
| `columns` | Não | Omitido, null ou lista vazia = mapa `$exportable`; lista explícita de nomes únicos válidos, sem seleção parcial silenciosa |
| `filters` | Não | Filtros ativos da tabela |
| `search` | Não | Termo de busca |
| `sort` | Não | Coluna de ordenação |
| `sort_direction` | Não | `asc` ou `desc` |

#### `GET /api/v1/exports/{uuid}`

Retorna o status atual de um export. Retorna 403 se não for do usuário, 404 se não existir.

Usado pelo polling do frontend a cada 3 segundos.

#### `GET /api/v1/exports/columns?model_class=App\Models\User`

Retorna as colunas exportáveis do model somente para um solicitante autorizado à origem. Sem policy/capacidade/escopo válido retorna `403`; modelo fora da allowlist retorna `422`.

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

Varre por ID estável, sem saltar registros ao excluir. Em cada pedido, relê o estado sob lock e usa um único instante de corte por execução:

- `completed` com `expires_at <= corte`: remove arquivos canônicos conhecidos e só depois confirma a exclusão da linha.
- `failed`: limpa os arquivos e conserva diagnóstico por `failed_retention_days` (padrão 7 dias desde `updated_at` terminal). Limpar o caminho não renova esse prazo; sem data terminal, conserva a linha.
- `pending` e `processing`: preservados, inclusive abandonados. A recuperação pertence a `exports:recover`, conforme o [contrato OPS](../../stabilization/ops-worker-runtime.md).

Falha, recusa, resíduo de arquivo ou veto à exclusão mantém rastreamento no banco e faz o comando retornar erro; os outros pedidos continuam sendo tratados. Banco e filesystem não formam uma transação atômica: após exclusão parcial, o caminho conservado permite tentar novamente mesmo que alguns arquivos já estejam ausentes.

No disco **local privado**, limpa também órfãos reconhecidos com pelo menos `orphan_retention_hours` (padrão 24 horas): `diretório/UUID.csv|tsv|xlsx`, `diretório/UUID/token-UUID.ext` e respectivos `.part`. Qualquer UUID ainda cadastrado ou caminho referenciado protege o arquivo. UUIDs são comparados sem diferença de caixa; referências físicas locais no Windows também, preservando os nomes originais para IO e a distinção de caixa de chaves remotas. Nomes desconhecidos são preservados; links, hardlinks, diretório público e namespaces inseguros são recusados. A enumeração valida os ancestrais antes de descer e relê idade, identidade e referências antes de remover. A carência reduz corridas; não representa snapshot nem garantia universal de execução exatamente uma vez.

Em disco remoto privado, a limpeza fica restrita ao caminho registrado e seu `.part`, com confirmação de ausência; não há listagem ou coleta de órfãos remotos. Temporários em `sys_get_temp_dir()` não são varridos por prefixo: esse diretório pode ser compartilhado por outros projetos. A limpeza normal por token e a recuperação OPS permanecem responsáveis por seus caminhos conhecidos.

Agendado em `routes/console.php`:

```php
Schedule::command('exports:prune')
    ->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
```

Mantenha uma única declaração da tarefa. A execução automática no Windows local requer processo externo e cache persistente conforme o [contrato de agenda OPS](../../stabilization/ops-scheduler-runtime.md).

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

O hook publica o acompanhamento compartilhado do `ExportTrackingProvider`, montado dentro da árvore privada. O mesmo provider mantém o fluxo separado de dumps; não existe singleton de jobs entre contas ou requests SSR.

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
| `markAsDownloaded` | `(uuid: string, expectedDownloadUrl?: string) => boolean` | Valida a ação atual, marca como baixado e some dos recentes; `false` impede navegação antiga ou expirada |

**Comportamento interno:**

1. **Inicialização**: o primeiro consumidor habilitado restaura e valida somente o namespace da identidade elegível, elimina duplicatas e remove entradas expiradas. A chave global legada não é migrada. A criação do provider e o SSR não leem storage, registram listeners nem iniciam timers/rede.
2. **Polling**: há um proprietário por fluxo/conta, compartilhado entre toolbars. Cada rodada consulta sequencialmente os UUIDs pendentes; a próxima começa 3 segundos após a rodada terminar. Um merge não cancela a própria rodada. O último consumidor, a desmontagem e a invalidação cancelam timers/requests; uma ativação antiga não agenda trabalho para a seguinte. `useExport()` manual continua habilitado por padrão; a toolbar passa `enabled=false` quando não oferece exportação.
3. **Pruning automático**:
   - Exports com `expires_at <= agora` são removidos por timer no prazo, inclusive terminais com a UI aberta e sem novo GET
   - Exports pending/processing sem `expires_at` são removidos após 1 hora (`MAX_EXPORT_AGE_MS`) — previne polling infinito se o worker parar
4. **Aviso e download**: cada transição de pending/processing para terminal produz um único aviso por fluxo. Toast e links dos dois indicadores revalidam consumidor/ativação, escopo, status completed, prazo e URL atual antes de navegar; os anchors cancelam o evento se o hook retorna `false`. `downloadedAt` se propaga a todos os consumidores.
5. **Recusas**: 404 remove somente o UUID; 403 encerra esse job como failed sem URL e mostra um único aviso. Um 403 no índice de dumps suspende o fluxo e retira seus downloads, sem encerrar a identidade. 401/419 em status, índice ou criação invalidam a árvore privada e exibem fora dela “Sua sessão precisa ser revalidada.”, com “Atualizar sessão” para reload manual. Não há retry automático de autenticação nem logout global por 403. Erros transitórios aguardam o descanso da rodada; histórico vazio com 500 também é tentado novamente serialmente.
6. **Persistência**: F5 na mesma conta restaura seus exports. Logout, troca de identidade ou perda de elegibilidade invalidam o escopo e purgam o namespace de saída. JSON inválido ou storage indisponível não autoriza usar o histórico de outra conta; a persistência é opcional e o estado em memória continua compartilhado. Escritas ocorrem fora de render e de updaters React.

O índice de dumps é reconciliado na ativação, na mesma fila dos status: atualiza UUIDs existentes e acrescenta novos, preservando `downloadedAt`. Versões das alterações locais e tombstones de 404 impedem que uma resposta de histórico antiga sobrescreva uma criação ou recoloque um UUID removido. Uma resposta tardia de POST após desmontar seu consumidor é cancelada para a UI, sem gravar storage, avisar ou fechar outro diálogo; isso não desfaz um pedido já aceito no servidor.

**Chave do localStorage:** `private-state:{id}:data-table-exports`, em que `{id}` é `auth.user.id`. O hook de dumps (`useDatabaseExport`) segue o mesmo contrato com `private-state:{id}:database-exports`. Visitantes, contas sem e-mail verificado e contas com troca obrigatória de senha não restauram nem consultam esses dados privados.

Os hooks dependem de `PrivateStateLayout`, declarado nos layouts de `app.tsx`, abaixo do PageContext Inertia. O layout mantém a autoridade da identidade; apenas sua subárvore privada recebe uma nova geração. O componente App do Inertia não é remontado na troca de conta. Cada árvore possui seu próprio QueryClient, store ZIP e acompanhamento de exportações/dumps, inclusive em renders SSR separados; não há estado privado global compartilhado entre requests. Listeners do tracker são registrados em efeito com cleanup, sem registrar stores de renders abandonados no escopo.

A invalidação cancela requests do cliente `@/lib/http`, combinando o sinal do escopo com o sinal do chamador. Polls, retornos de criação, atualizações de estado e ações de toast também verificam se o escopo capturado continua atual. Cancelamento obsoleto rejeita como `ApiError` de tipo `canceled`; não retorna sucesso vazio para disparar `onClose` ou `onExportStarted`. Erros funcionais do escopo ativo continuam sendo tratados pelo consumidor. Uma operação aceita pelo servidor não é desfeita ao cancelar o navegador. Transportes que usam `fetch` ou Axios diretamente precisam do próprio cancelamento e descarte de callbacks; o interceptor deste cliente não os cobre.

Ao voltar/avançar para props de outra identidade, a árvore privada fica oculta antes de renderizar esses dados e a página é revalidada por HTTP. A revalidação preserva o namespace da conta confirmada; o histórico não pode confirmar a identidade antiga. O particionamento organiza o estado privado da aplicação, sem substituir policies do backend ou criptografar o localStorage.

Para limpar manualmente em desenvolvimento, substitua `123` pelo ID da conta desejada e remova somente sua chave:

```javascript
localStorage.removeItem('private-state:123:data-table-exports')
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

Sem isso, o endpoint retornará HTTP 422. A allowlist não implementa a autorização: configure também a policy `viewAny` e o hook `applyExportScope`, reutilizando a regra real da listagem. O hook padrão recusa; não copie uma query global para um domínio que possui limites por alvo. O exemplo de declaração de colunas acima só fica exportável depois dessa configuração. Inclua testes de recusa e igualdade entre as linhas visíveis e exportadas, e de perda de acesso antes do job.

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
| `allowed_models` | `[User::class, Client::class]` | Classes admitidas; policy e hook de escopo continuam obrigatórios |
| `disk` | `'local'` | Disco do Laravel Storage. Use `'local'` ou um disco S3 privado |
| `directory` | `'exports'` | Subdiretório dentro do disco |
| `expires_after_hours` | `1` | Horas até o arquivo expirar após conclusão |
| `chunk_size` | `500` | Máximo de registros carregados por lote ordenado |

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
| `GET` | `/api/v1/exports` | Sanctum (sessão SPA) | Lista exports do usuário (últimos 10, não-expirados) |
| `POST` | `/api/v1/exports` | Sanctum (sessão SPA) | Cria export + dispatch job |
| `GET` | `/api/v1/exports/columns` | Sanctum (sessão SPA) | Lista colunas exportáveis de um model |
| `GET` | `/api/v1/exports/{uuid}` | Sanctum (sessão SPA) | Status de um export específico |
| `GET` | `/exportacoes` | Web | Histórico (Inertia) |
| `GET` | `/exports/{uuid}/download` | Web | Download do arquivo |

As quatro rotas API recusam autenticação por PAT conforme a seção 4.7. Histórico e download continuam rotas web; esta documentação não declara suporte bearer para elas.

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

**`tests/Feature/Export/ExportApiControllerTest.php`**:
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

**`tests/Feature/Export/ProcessExportTest.php`**:
- Gera CSV e marca como completed
- Marca como failed em exceção
- Exporta todas as colunas quando nenhuma é selecionada
- Confere texto não confiável, cabeçalhos, tipos e fronteiras dos três formatos nos arquivos gerados

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

Use os testes existentes como referência, com armazenamento, notificações, HTTP, Mail e filas isolados pela PREP-001. Prepare um solicitante verificado, sem troca de senha pendente, com os papéis/capacidades reais exigidos pela policy do domínio. Configure a classe e o hook reais; uma conta recém-criada sem permissão não é um exemplo de sucesso autorizado.

Crie linhas permitidas e proibidas para esse solicitante e compare os identificadores efetivamente visíveis com o conteúdo do arquivo, incluindo filtros e contagem. Verifique recusa de colunas/modelo não admitidos e ausência de registro/job nas recusas HTTP. Entre despacho e execução, retire a permissão ou elegibilidade e exija `failed` genérico sem novo arquivo/notificação; restaure o acesso e prove uma nova solicitação legítima. Preserve também a leitura dos três formatos e as assertions de proteção de texto da SEC-017.

---

## 10. Dicas e Armadilhas

### Worker de Queue é obrigatório

O export NÃO processa automaticamente em ambiente local sem o worker rodando:

```bash
php artisan queue:listen --timeout=960 --tries=3 --backoff=30 --sleep=1 # Windows local
php artisan queue:work --timeout=900 --tries=3 --backoff=30 --sleep=1 # Produção com PCNTL
```

Consulte [operação de workers](../../stabilization/ops-worker-runtime.md): `retry_after` mínimo de 1.020 segundos, supervisor, reinício e limites de IO no Windows. Estes comandos não foram executados em produção.

### Ordenação e completude entre lotes

`buildExportQuery()` preserva a ordenação validada por `apiSort` e acrescenta a chave primária qualificada em ordem ascendente como desempate. Se a própria chave é a ordenação principal descendente, ela continua descendente. O job lê com `chunk()` e escreve via OpenSpout, mantendo somente o lote atual em memória. Isso também conserva campos de ordenação nulos e não pressupõe chave chamada `id`.

Não substituir por `chunkById()` mantendo uma ordenação diferente: o avanço por ID pode omitir ou repetir registros; a ordenação ID descendente também não deve virar ascendente. A completude e a ordem determinística pressupõem conjunto e valores ordenados estáveis durante a leitura. A paginação usa offset, pode custar mais nos lotes finais e não representa um snapshot de alterações concorrentes. O job não modifica a origem. Qualquer contrato futuro de snapshot precisa de decisão e prova próprias.

`total_records` começa como estimativa obtida antes da primeira leitura. Cada linha de dados é contada somente depois de `addRow()` retornar; o cabeçalho não entra no progresso. O delta é publicado ao terminar o lote, inclusive os aceites parciais antes de uma exceção. Ao concluir, `total_records` e `processed_records` passam a refletir a quantidade efetiva, que pode diferir da estimativa inicial. Linhas aceitas pelo writer durante processing não significam arquivo já publicado ou durável após queda.

Uma falha ordinária fecha o writer antes de tentar remover seu temporário. A retomada serial de um export ainda processing revalida acesso, zera o progresso e recria o arquivo; não anexa linhas da tentativa anterior. A conclusão só é marcada após o fechamento e a publicação no disco privado. A [OPS-001](../../stabilization/tasks/OPS-001.md) acrescenta leases, recuperação e provas reais de dois workers/restart/queda; retenção geral de artefatos abandonados continua em [EXP-002](../../stabilization/tasks/EXP-002.md).

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

Se o problema aparecer em desenvolvimento, remova somente o histórico de exports da conta afetada (substitua `123` pelo seu ID):

```javascript
localStorage.removeItem('private-state:123:data-table-exports')
```

### Lease e geração de despacho

Cada aquisição grava um token próprio e prazo. Geração de despacho antiga não processa o pedido após recuperação; callbacks antigos não encerram outra aquisição. O lock do banco protege somente transições, progresso e conclusão, sem manter transação durante a geração inteira. A prova cobre os cenários locais descritos em OPS-001 e não promete processamento exatamente uma vez para qualquer driver ou efeito externo.

### Segurança: allowlist de models

O campo `model_class` no payload da API é validado contra `config('exports.allowed_models')`. Nunca remova essa validação — sem ela, um usuário malicioso poderia exportar qualquer model da aplicação.

Essa validação também é repetida no job e não substitui a policy/escopo. Um model apenas configurado, sem o hook autorizado, continua recusado; não transforme esse fallback em uma query global.

### Expiração vs. deleção

O arquivo expira (`expires_at`) antes de ser deletado do storage. Entre a expiração e a limpeza diária (`exports:prune`):
- O frontend não exibe mais o link de download (pois `download_url` fica null na API)
- Download retorna HTTP 410 (Gone)
- O arquivo ainda ocupa espaço no disco até a limpeza

Se precisar de expiração mais agressiva, reduza `expires_after_hours` e aumente a frequência do `exports:prune`.

### Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|---------------|---------|
| Export fica `pending` para sempre | Worker não está rodando | Worker e recuperação conforme [contrato OPS](../../stabilization/ops-worker-runtime.md) |
| Erro 500 no job | Model não usa scopes `search`/`apiFilters`/`apiSort` | Adicionar trait Searchable ao model |
| Export concluído mas não aparece no sino | Adaptador/módulo AppNotification ausente ou persistência do aviso falhou | Conferir `ExportReadyChannel`, migrations e logs; falha atual ao persistir aviso desfaz conclusão, sem reparar histórico automaticamente |
| Botão "Exportar" não aparece | `features={{ export: true }}` ausente ou `exportConfig` não passado | Verificar props do DataTable |
| Colunas não carregam no dialog | Modelo inválido (`422`) ou origem sem autorização (`403`) | Conferir allowlist, policy/capacidade e hook de escopo; não conceder acesso para ocultar o erro |
| Download retorna 403 | Export pertence a outro usuário | Verificar autenticação |
| Download retorna 410 | Arquivo expirado | Gerar nova exportação |

---

## 11. Checklist para Implementar em Outro Projeto

Use esta lista ao portar o sistema para um novo projeto Laravel + Inertia (React).

### Dependências

```bash
# PHP
composer require openspout/openspout

# Migrations de exports e do módulo AppNotification já instalado
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
- [ ] `app/Notifications/Channels/ExportReadyChannel.php` e módulo Notifications existente (DTO, action, model, migration e evento privado)
- [ ] `app/Http/Resources/ExportResource.php`
- [ ] `app/Http/Requests/CreateExportRequest.php`
- [ ] `app/Http/Controllers/Api/ExportApiController.php`
- [ ] `app/Http/Controllers/ExportController.php`
- [ ] `app/Console/Commands/PruneExports.php`
- [ ] Registrar o comando em `routes/console.php`:
  ```php
  Schedule::command('exports:prune')
      ->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
  ```
- [ ] Adicionar rotas em `routes/api.php` e `routes/web.php`
- [ ] Para cada model exportável: `Exportable`, `$exportable`, allowlist, policy `viewAny` e `applyExportScope` com a mesma regra da listagem

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

- [ ] Worker supervisionado conforme [contrato OPS](../../stabilization/ops-worker-runtime.md)
- [ ] Processo externo e cache persistente do scheduler conforme [contrato de agenda OPS](../../stabilization/ops-scheduler-runtime.md)
- [ ] Tabela `app_notifications` e migration de lease aplicadas
- [ ] Disco de storage não é público
- [ ] Model adicionado ao `allowed_models`
- [ ] Policy e escopo da origem comprovados no pedido, nas colunas e no job, incluindo recusa sem efeitos e revogação antes de executar
- [ ] `features={{ export: true }}` no DataTable da página
- [ ] Testes passando: `php artisan test --compact tests/Feature/Export tests/Unit/Export`


### Contrato dos dumps de banco (BKP-001)

O job de dumps usa driver/configuração da conexão resolvida, inclusive aliases. SQLite aceita somente `full` em arquivo persistente existente; modos parciais recebem 422 e payloads antigos falham antes do dumper. MySQL/MariaDB e PostgreSQL conservam os três modos. Filtros literais e exclusões são validados no job; PostgreSQL filtra somente `public`, SQLite recusa conjunto efetivo vazio ou nomes ambíguos. [Contrato detalhado e histórico sem disco](../../backups/backup-dumps-module.md#93-createdatabaseexportrequest).


### Agenda e limpeza coordenada (OPS-002)

`exports:prune` e `database-exports:prune` conservam retenção/leases e agora usam UTC explícito, lock de duração e eleição por minuto na agenda diária. ZIP continua a cada hora, com os mesmos mecanismos. Não se inicia scheduler por declarar a agenda: o processo externo e o cache database compartilhado precisam do [contrato Windows de operação](../../stabilization/ops-scheduler-runtime.md). Os testes não operam bancos ou filas existentes. A configuração PHP/Carbon preserva o fuso efetivo São Paulo; os eventos mantêm os horários UTC anteriores.
