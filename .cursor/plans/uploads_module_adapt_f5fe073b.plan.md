---
name: Uploads Module Adapt
overview: Adaptar o módulo de uploads de referência para um módulo Laravel real em `app/Modules/Uploads/` (namespace próprio, ServiceProvider) com suporte a tags híbridas (sugestões por model + valor livre), cache TanStack Query parent-scoped, e integração tipo-segura via hooks `useUploads(modelType, modelId)`.
todos:
  - id: bootstrap-module
    content: Criar UploadsServiceProvider (Config, Migrations, Routes, Policy) + Config/uploads.php + registrar em bootstrap/providers.php
    status: completed
  - id: migration
    content: Criar Database/Migrations/create_uploads_table (renomeia de `uploadeds`) com campos `tag`, `collection` e índices compostos
    status: completed
  - id: model-uploaded
    content: Criar Models/Uploaded.php no namespace App\Modules\Uploads\Models, remover `hasRole` hardcoded do canDestroy
    status: completed
  - id: contracts-traits
    content: Criar Contracts/HasUploadTags + Traits/HasUploads (delegando ao UploadService) + Policies/UploadedPolicy
    status: completed
  - id: service
    content: Implementar Services/UploadService com resolveModel via config, checagem de idempotência por file_hash, suporte a tag
    status: completed
  - id: controller-requests
    content: Http/Controllers/UploadController + Http/Requests/{Index,Store,Update}UploadRequest (valida alias em config) + endpoint /uploads/tags
    status: completed
  - id: resource-routes
    content: Http/Resources/UploadedResource (expor tag, permissions.can_update) + Routes/api.php registrada pelo provider com prefix/middleware via config
    status: completed
  - id: cleanup-duplicates
    content: Remover arquivos duplicados em app/Http/..., app/Models/Uploaded.php, app/Services/UploadService.php, app/Supports/Traits/HasUploads.php, app/Modules/Uploads/App/..., app/Modules/Uploads/routes/, app/Modules/Uploads/docs/, app/Modules/Uploads/views/
    status: completed
  - id: fe-module-api
    content: Criar resources/js/modules/uploads/api/{types,keys,queries,mutations}.ts com cache parent-scoped e optimistic updates
    status: completed
  - id: fe-module-components
    content: Criar resources/js/modules/uploads/components/{file-attachments,upload-item,upload-dropzone,tag-combobox}.tsx + hooks/use-uploads.ts + index.ts barrel
    status: completed
  - id: integrate-models
    content: Adicionar HasUploads + implements HasUploadTags em FinancialEntry e Supplier com uploadTagSuggestions()
    status: completed
  - id: integrate-pages
    content: Incluir <FileAttachments> via `@/modules/uploads` na página show de FinancialEntry e na edit/show de Supplier
    status: completed
isProject: false
---

# Plano de Implementação - Módulo Uploads

## 1. Análise Sênior da Referência

A referência em [app/Modules/Uploads/docs/doc-component-file-attachments.md](app/Modules/Uploads/docs/doc-component-file-attachments.md) é funcional mas tem **problemas estruturais**:

- **Duplicação**: os arquivos foram copiados em `app/` e `app/Modules/Uploads/App/` usando o MESMO namespace `App\...` — isso gera colisão de autoload. É preciso escolher UM local.
- **`UploadService` com aliases hardcoded** de models que nem existem neste projeto (`PurchaseRequisition`, `Contract`, `PaymentRequest`).
- **Permissão via `hasRole('admin'|'developer')`** hardcoded no model, ignorando o módulo `Permissions` já existente com `Gate::before`.
- **Rota de listagem** fragmentada (`/tables/uploads` vs `/api/v1/uploads`) — a própria doc reconhece isso como problema (seção 9.6).
- **Validação de tipo de model fraca**: aceita qualquer string em `parentable_type`.
- **Sem `tag`**, apenas `collection` (que confunde: collection = "bucket", não rótulo legível).
- **Sem idempotência**: uploads duplicados do mesmo arquivo (mesmo `file_hash`) não são detectados.
- **`maxSize`** divergente: front em MB, back em KB.
- **Front usa `useState`/`useEffect`** direto (contra a regra do projeto) — vamos migrar para TanStack Query com cache parent-scoped.

## 2. Decisões Arquiteturais

- **Estrutura modular real**: tudo sob `app/Modules/Uploads/` com namespace `App\Modules\Uploads\...` (seguindo o padrão do [PermissionsServiceProvider](app/Modules/Permissions/Providers/PermissionsServiceProvider.php)).
- **Tags híbridas**: cada model registra tags sugeridas via método estático `uploadTagSuggestions()`; o backend valida apenas comprimento (até 50 chars); o frontend usa combobox com autocomplete + digitação livre.
- **Registro de aliases via config**: `config/uploads.php` com mapa `alias => Model::class` em vez de array hardcoded no service.
- **Policy real**: `UploadedPolicy` registrada no ServiceProvider, aproveitando o `Gate::before` existente do módulo Permissions (roles `admin`/`developer` já passam).
- **Único endpoint de listagem**: `GET /api/v1/uploads?parentable_type=...&parentable_id=...`. Remove o dualismo com `/tables/uploads`.
- **Idempotência opcional**: endpoint de upload retorna 200 com o registro existente quando `file_hash` já existe para o mesmo `parentable` + `tag`.

## 3. Estrutura de Arquivos (package-like, portável)

**Princípios aplicados:**
- PascalCase consistente em TODAS as pastas (`Config/`, `Routes/`, `Database/` — em vez de misturar com minúsculas).
- Nada de aninhamento desnecessário (`Requests/Uploads/` vira só `Requests/`, `Support/Traits/` vira `Traits/` no raiz).
- Pastas de 1º nível = **um conceito** cada: Config, Contracts, Database, Http, Models, Policies, Providers, Routes, Services, Traits, Docs.
- Espelhamento back ↔ front: mesmo nome de módulo, mesma organização interna, facilitando "copy-paste" para outros projetos.
- Barrel `index.ts` no front e `README.md` na raiz do módulo back documentando o ponto de entrada.

### Backend — `app/Modules/Uploads/`

```
app/Modules/Uploads/
├── Config/
│   └── uploads.php                          # aliases, disk, max_size, tags por default
├── Contracts/
│   └── HasUploadTags.php                    # interface p/ models que expõem tags sugeridas
├── Database/
│   ├── Migrations/
│   │   └── 2025_12_18_133014_create_uploads_table.php
│   └── Factories/
│       └── UploadedFactory.php              # (opcional, p/ testes)
├── Http/
│   ├── Controllers/
│   │   └── UploadController.php             # API única (sem subpasta Api/)
│   ├── Requests/
│   │   ├── IndexUploadRequest.php
│   │   ├── StoreUploadRequest.php
│   │   └── UpdateUploadRequest.php
│   └── Resources/
│       └── UploadedResource.php
├── Models/
│   └── Uploaded.php
├── Policies/
│   └── UploadedPolicy.php                   # nível raiz (convenção Laravel)
├── Providers/
│   └── UploadsServiceProvider.php
├── Routes/
│   └── api.php                              # carregado com prefix /api/v1/uploads
├── Services/
│   └── UploadService.php                    # toda a lógica de IO/DB
├── Traits/
│   └── HasUploads.php                       # use App\Modules\Uploads\Traits\HasUploads;
└── Docs/
    └── README.md                            # guia de instalação + contratos da API
```

**Namespace raiz:** `App\Modules\Uploads\` com PSR-4 herdado do autoload do `app/` (já configurado).

### Frontend — `resources/js/modules/uploads/`

Movido de `features/` + `components/` (que estão espalhados) para um módulo **auto-contido**, espelhando o back:

```
resources/js/modules/uploads/
├── api/
│   ├── types.ts                             # Upload, UploadInput, TagSuggestion, UploadFilters
│   ├── keys.ts                              # query keys factory (uploadsKeys)
│   ├── queries.ts                           # uploadsListOptions, uploadTagsOptions
│   └── mutations.ts                         # useUploadFile, useUpdateUpload, useDeleteUpload, useSetCover
├── components/
│   ├── file-attachments.tsx                 # componente principal (entrada pública)
│   ├── upload-item.tsx                      # linha de arquivo (preview + nome editável + tag + ações)
│   ├── upload-dropzone.tsx                  # área de drop/click isolada
│   └── tag-combobox.tsx                     # sugestões + texto livre (shadcn Command + Popover)
├── hooks/
│   └── use-uploads.ts                       # hook de conveniência: agrega list+mutations p/ um parent
├── docs/
│   └── README.md                            # como consumir o módulo + props
└── index.ts                                 # barrel: export { FileAttachments, useUploads, ... }
```

**Consumo no projeto:**
```tsx
import { FileAttachments } from '@/modules/uploads'
```
ou, para usar mutations diretamente:
```tsx
import { useUploads } from '@/modules/uploads'
```

### Checklist de portabilidade (para replicar em outros projetos)

Para mover este módulo para outro projeto Laravel+React, basta:

1. Copiar `app/Modules/Uploads/` inteira.
2. Copiar `resources/js/modules/uploads/` inteira.
3. Registrar o provider em `bootstrap/providers.php`: `App\Modules\Uploads\Providers\UploadsServiceProvider::class`.
4. Ajustar `Config/uploads.php` com os aliases dos models daquele projeto.
5. Adicionar `use HasUploads` (+ opcionalmente `implements HasUploadTags`) nos models-alvo.
6. Rodar `php artisan migrate` e `php artisan storage:link`.
7. Garantir no front que existe `@tanstack/react-query`, `axios`, `lucide-react` e que o alias `@/modules` aponta para `resources/js/modules`.

Nenhum arquivo fora dessas duas pastas precisa de modificação (exceto o `providers.php` e os models que ganharão o trait).

## 4. Schema do Banco (ajustado)

Tabela `uploads` (renomeada de `uploadeds`):

- `id`, `user_id`, `uploader_id`
- `parentable_type`, `parentable_id` (`nullableMorphs` + index)
- `name` (editável pelo usuário), `original_name`, `file_name` (UUID), `extension`, `mime_type`, `path`, `disk`, `size`, `file_hash` (index)
- `tag` **(novo)** `string(50) nullable` — substitui/complementa `collection`
- `collection` (mantido p/ agrupamento semântico opcional, ex: "documents" vs "photos")
- `is_cover`, `source`, timestamps, `softDeletes`
- Índices: `(parentable_type, parentable_id, tag)`, `(parentable_type, parentable_id, file_hash)`

## 5. Backend - Pontos-Chave

### `Config/uploads.php`
```php
return [
    'disk' => env('UPLOADS_DISK', 'public'),
    'max_size_mb' => 50,
    'route_prefix' => 'api/v1/uploads',
    'route_middleware' => ['auth:sanctum'],
    'aliases' => [
        'financial-entry' => \App\Models\FinancialEntry::class,
        'supplier'        => \App\Models\Supplier::class,
        'client'          => \App\Models\Client::class,
    ],
];
```
Tornar `route_prefix` e `route_middleware` configuráveis deixa o módulo 100% portável para projetos que não usem Sanctum ou tenham outro prefixo de API.

### `HasUploads` trait (adaptado)
- Elimina duplicação com `UploadService` (hoje o mesmo código aparece em ambos). Trait delega ao service:
  ```php
  public function addUpload(UploadedFile $file, ...): Uploaded {
      return app(UploadService::class)->upload($this, $file, ...);
  }
  ```
- Novo método `uploadsByTag(string $tag): MorphMany`.

### `HasUploadTags` contract (opcional, por model)
```php
interface HasUploadTags {
    /** @return array<string, string> value => label */
    public static function uploadTagSuggestions(): array;
}
```
Ex: `FinancialEntry::uploadTagSuggestions()` retorna `['payment_receipt' => 'Comprovante de pagamento', 'invoice' => 'Nota fiscal']`. `Supplier::uploadTagSuggestions()` retorna `['cnpj_card' => 'Cartão CNPJ', 'contract' => 'Contrato']`.

### `UploadController` (endpoints consolidados)
- `GET /api/v1/uploads` — lista por `parentable_type`+`parentable_id`, filtro opcional `tag`, `collection`
- `POST /api/v1/uploads` — upload com `name`, `tag`, `collection`, `is_cover`, checa duplicidade por hash
- `PUT /api/v1/uploads/{upload}` — **atualiza `name` E/OU `tag`** (unificado; atualmente só renomeia)
- `DELETE /api/v1/uploads/{upload}`
- `POST /api/v1/uploads/{upload}/set-cover`
- `GET /api/v1/uploads/{upload}/download`
- `GET /api/v1/uploads/tags?parentable_type=...` — **novo**: retorna sugestões de tag do model

### Requests
- `StoreUploadRequest`: valida alias em `config('uploads.aliases')`, `files.*` max via config, `tag` nullable string max:50.
- `UpdateUploadRequest`: `name` e `tag` ambos `sometimes` (permite update parcial).

### Policy
`UploadedPolicy@delete`/`@update`: retorna true se o user é `uploader_id` OU `user_id`. O `Gate::before` do módulo Permissions já concede bypass para `admin`/`developer`.

## 6. Frontend - Pontos-Chave

### `modules/uploads/api/types.ts`
```ts
export interface Upload {
  id: number
  name: string
  original_name: string
  extension: string
  mime_type: string
  size: number
  size_human: string
  url: string
  icon: string
  is_image: boolean
  is_cover: boolean
  tag: string | null
  collection: string | null
  created_at: string
  uploader?: { id: number; name: string }
  permissions: { can_delete: boolean; can_update: boolean }
}

export interface TagSuggestion { value: string; label: string }
```

### `modules/uploads/api/keys.ts` + `queries.ts`
Query key **parent-scoped** (factory isolada em `keys.ts` p/ reutilização):
```ts
export const uploadsKeys = {
  all: ['uploads'] as const,
  list: (type: string, id: string|number, filters?: UploadFilters) =>
    ['uploads', type, String(id), filters] as const,
  tags: (type: string) => ['uploads', 'tags', type] as const,
}

export function uploadsListOptions(type, id, filters?) { ... }
export function uploadTagsOptions(type) { ... }
```

### `modules/uploads/api/mutations.ts`
- `useUploadFile(type, id)`: POST multipart, **onSuccess faz `setQueryData`** prepending o novo item na lista cacheada.
- `useUpdateUpload(type, id)`: PUT (`{ name?, tag? }`), **optimistic update** atualizando o item em cache e rollback em erro (espelha o padrão de [create-resource-hooks.ts](resources/js/lib/create-resource-hooks.ts)).
- `useDeleteUpload(type, id)`: DELETE com optimistic removal + rollback.
- `useSetCoverUpload(type, id)`: POST + atualiza todos os items em cache (`is_cover=false` em todos, `true` no atual).

### `modules/uploads/hooks/use-uploads.ts`
Hook de conveniência que agrega tudo que uma página normalmente precisa:
```ts
export function useUploads(modelType: string, modelId: string | number, filters?: UploadFilters) {
  const list = useQuery(uploadsListOptions(modelType, modelId, filters))
  const tags = useQuery(uploadTagsOptions(modelType))
  const upload = useUploadFile(modelType, modelId)
  const update = useUpdateUpload(modelType, modelId)
  const remove = useDeleteUpload(modelType, modelId)
  return { list, tags, upload, update, remove }
}
```

### `modules/uploads/components/file-attachments.tsx`
Props: `modelType`, `modelId`, `tag?` (filtro), `collection?`, `maxFiles`, `maxSize`, `accept`, `showCoverOption`, `readOnly`, `defaultTag?` (tag pré-selecionada ao subir).

Fluxo (sem `useState` para dados do servidor):
- `useUploads(modelType, modelId, { tag })` centraliza list + mutations
- `<UploadDropzone>` isolado cuida de drag&drop e chama `upload.mutate`
- `<UploadItem>` com edição inline de nome (blur → `update.mutate({ id, data: { name } })`)
- Edição de tag via `<TagCombobox>` → `update.mutate({ id, data: { tag } })`
- `useState` APENAS para UI ephemeral (dialog aberto, id em edição)

### `modules/uploads/components/tag-combobox.tsx`
- Usa `Command` / `Popover` do shadcn.
- Props: `value`, `suggestions: TagSuggestion[]`, `onChange(value: string)`.
- Permite selecionar sugestão OU digitar valor custom.

### `modules/uploads/index.ts` (barrel público)
```ts
export { FileAttachments } from './components/file-attachments'
export { TagCombobox } from './components/tag-combobox'
export { useUploads } from './hooks/use-uploads'
export * from './api/types'
```

## 7. Integração nos Models-Alvo

### `app/Models/FinancialEntry.php`
```php
use App\Modules\Uploads\Traits\HasUploads;
use App\Modules\Uploads\Contracts\HasUploadTags;

class FinancialEntry extends Model implements HasUploadTags {
    use HasFactory, SoftDeletes, HasUuidIdentifier, HasCreatedBy, HasUploads;

    public static function uploadTagSuggestions(): array {
        return [
            'payment_receipt' => 'Comprovante de pagamento',
            'invoice'         => 'Nota fiscal',
            'boleto'          => 'Boleto',
        ];
    }
}
```

### `app/Models/Supplier.php`
```php
use App\Modules\Uploads\Traits\HasUploads;
use App\Modules\Uploads\Contracts\HasUploadTags;

class Supplier extends Model implements HasUploadTags {
    use HasFactory, SoftDeletes, SearchableTrait, HasUploads;

    public static function uploadTagSuggestions(): array {
        return [
            'cnpj_card'        => 'Cartão CNPJ',
            'contract'         => 'Contrato',
            'bank_certificate' => 'Comprovante bancário',
        ];
    }
}
```

### Uso no frontend (ex.: [financial-entries/show.tsx](resources/js/pages/app/financial-entries/show.tsx))
```tsx
import { FileAttachments } from '@/modules/uploads'

<FileAttachments
  modelType="financial-entry"
  modelId={entry.id}
  defaultTag="payment_receipt"
/>
```

## 8. Registro do ServiceProvider

Adicionar em [bootstrap/providers.php](bootstrap/providers.php):
```php
App\Modules\Uploads\Providers\UploadsServiceProvider::class,
```

O provider faz:
- `$this->mergeConfigFrom(__DIR__.'/../Config/uploads.php', 'uploads')`
- `$this->loadMigrationsFrom(__DIR__.'/../Database/Migrations')`
- `Route::prefix(config('uploads.route_prefix'))->middleware(config('uploads.route_middleware'))->group(__DIR__.'/../Routes/api.php')`
- `Gate::policy(Uploaded::class, UploadedPolicy::class)`
- `$this->publishes([...])` opcional p/ publicar config em `config/uploads.php` do host se quiser customizar.

## 9. Diagrama de Fluxo

```mermaid
flowchart LR
  UI[FileAttachments.tsx] -->|POST multipart| API[UploadController@store]
  API --> REQ[StoreUploadRequest valida alias + size]
  REQ --> SVC[UploadService resolve model]
  SVC --> CHECK{file_hash existe?}
  CHECK -->|sim| EXIST[retorna Uploaded existente]
  CHECK -->|nao| STORE[Storage disk + cria Uploaded]
  STORE --> RES[UploadedResource]
  EXIST --> RES
  RES -->|201| UI
  UI -->|setQueryData prepend| CACHE[TanStack Cache scoped por parent]
```

## 10. Limpeza de Arquivos Duplicados

Os arquivos criados em `app/Http/...`, `app/Models/Uploaded.php`, `app/Services/UploadService.php`, `app/Supports/Traits/HasUploads.php` (namespace `App\...`) serão **removidos** — tudo ficará sob `app/Modules/Uploads/` com namespace próprio.

## 11. Out of Scope

- Drag-reorder dos uploads
- Versioning de arquivos
- Upload resumível (tus/chunked)
- Extração OCR/preview de PDF server-side
