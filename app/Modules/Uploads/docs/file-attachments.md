# FileAttachments: documentacao de portabilidade

Este documento explica como o modulo de anexos funciona neste projeto e como levar a mesma solucao para outro sistema com o minimo de acoplamento.

## O que existe hoje

Existem duas pecas com nomes parecidos:

- `resources/js/pages/admin/dev/components/file-attachments.tsx`: pagina de referencia/playground para testar e documentar o componente.
- `resources/js/modules/uploads/components/file-attachments.tsx`: componente real usado pela aplicacao.

Para migrar para outro sistema, a peca importante e o modulo `resources/js/modules/uploads`. A pagina em `pages/admin/dev` pode ser copiada apenas se voce quiser manter uma tela interna de demonstracao.

## Responsabilidade do componente

`FileAttachments` e um componente React que lista, envia, edita, visualiza, baixa e remove arquivos associados a um registro de qualquer model do backend.

Ele recebe o destino do anexo por:

```tsx
<FileAttachments
  modelType="supplier"
  modelId={supplier.id}
/>
```

`modelType` nao e o nome da classe PHP. E um alias registrado em `config/uploads.php`. O backend resolve esse alias para a classe real.

## Funcionalidades

- Upload por drag and drop ou seletor de arquivo.
- Fila com um POST por arquivo.
- Progresso individual por arquivo.
- Concorrencia configuravel.
- Retry, cancelamento e exibicao de erros por arquivo.
- Validacao frontend de tamanho maximo e quantidade maxima.
- Listagem com busca local por nome, tag e extensao.
- Renomear arquivo.
- Alterar tag.
- Definir imagem como capa.
- Marcar/desmarcar favorito.
- Preview protegido.
- Download protegido.
- Selecao multipla.
- Exclusao em lote.
- Geracao de ZIP em background.
- Variantes visuais: `inline`, `modal`, `drop-overlay` e `toggle`.
- Modo somente leitura.

## API publica do componente

```ts
export type FileAttachmentsVariant =
  | 'inline'
  | 'modal'
  | 'drop-overlay'
  | 'toggle'

export interface FileAttachmentsProps {
  modelType: string
  modelId: string | number
  tag?: string | null
  collection?: string | null
  defaultTag?: string | null
  maxFiles?: number
  maxSize?: number
  accept?: string
  showCoverOption?: boolean
  readOnly?: boolean
  title?: string | null
  description?: string | null
  className?: string
  variant?: FileAttachmentsVariant
  attachLabel?: string
  concurrency?: number
  listMaxHeight?: string | number
}
```

### Props principais

`modelType`: alias do model no backend. Exemplo: `supplier`, `client`, `financial-entry`.

`modelId`: ID numerico ou UUID do registro dono dos anexos.

`tag`: filtra a lista por uma tag fixa e tambem forca essa tag em novos uploads.

`defaultTag`: tag usada no upload quando `tag` nao foi informada. Boa para formularios onde a tag inicial e conhecida, mas o usuario ainda pode mudar depois.

`collection`: agrupamento adicional opcional. Funciona como segundo filtro alem da tag.

`maxFiles`: limite de arquivos por selecao/envio.

`maxSize`: tamanho maximo em MB por arquivo. Padrao: `50`.

`accept`: valor repassado para o input file. Exemplo: `image/*,application/pdf`.

`showCoverOption`: habilita acao de definir imagem como capa.

`readOnly`: oculta upload, edicao, favorito e exclusao. Mantem preview/download.

`title`: titulo do card. Use `null` para esconder o cabecalho.

`variant`: controla a apresentacao do upload.

`concurrency`: quantidade de uploads simultaneos. Padrao: `3`.

## Exemplos de uso

Uso minimo:

```tsx
import { FileAttachments } from '@/modules/uploads'

<FileAttachments
  modelType="supplier"
  modelId={supplier.id}
/>
```

Com tag fixa:

```tsx
<FileAttachments
  modelType="financial-entry-installment"
  modelId={installment.id}
  tag="payment_receipt"
  title="Comprovante da baixa"
  accept="image/*,application/pdf"
/>
```

Somente leitura:

```tsx
<FileAttachments
  modelType="client"
  modelId={client.id}
  readOnly
  title="Documentos"
/>
```

Upload em modal:

```tsx
<FileAttachments
  modelType="supplier"
  modelId={supplier.id}
  variant="modal"
  attachLabel="Anexar documento"
/>
```

## Arquitetura frontend

Modulo principal:

```txt
resources/js/modules/uploads
├── api
│   ├── endpoints.ts
│   ├── keys.ts
│   ├── mutations.ts
│   ├── queries.ts
│   └── types.ts
├── components
│   ├── bulk-action-bar.tsx
│   ├── drop-overlay.tsx
│   ├── file-attachments.tsx
│   ├── tag-combobox.tsx
│   ├── upload-dropzone.tsx
│   ├── upload-item.tsx
│   ├── upload-queue-item.tsx
│   ├── upload-quick-preview.tsx
│   └── zip-download-panel.tsx
├── hooks
│   ├── use-upload-queue.ts
│   ├── use-uploads.ts
│   ├── use-zip-poller.ts
│   └── use-zip-request.ts
├── lib
│   └── filter-files.ts
├── stores
│   └── zip-download-store.ts
└── index.ts
```

### Dependencias frontend

O modulo depende de:

- React.
- `@tanstack/react-query`.
- Axios atraves de `@/lib/http`.
- Store de toast em `@/stores/toast-store`.
- Zustand para o painel de ZIP.
- Componentes de UI do projeto: `Button`, `Input`, `Checkbox`, `Dialog`, `Collapsible`, `DeleteConfirmModal`.
- `lucide-react`.
- Utilitario `cn` em `@/lib/utils`.

Ao portar para outro sistema, a melhor abordagem e manter a logica do modulo e adaptar somente as bordas:

- Trocar `@/lib/http` pelo cliente HTTP do novo sistema.
- Trocar `@/stores/toast-store` pelo mecanismo de notificacao local.
- Trocar os componentes de UI pelos equivalentes do novo design system.
- Ajustar `uploadsEndpoints` caso o prefixo da API seja diferente.

## Contrato esperado da API

Base atual:

```txt
/api/v1/uploads
```

Endpoints:

```txt
GET    /api/v1/uploads
POST   /api/v1/uploads
GET    /api/v1/uploads/tags
GET    /api/v1/uploads/{upload}
GET    /api/v1/uploads/{upload}/preview
GET    /api/v1/uploads/{upload}/download
PUT    /api/v1/uploads/{upload}
DELETE /api/v1/uploads/{upload}
POST   /api/v1/uploads/{upload}/set-cover
POST   /api/v1/uploads/{upload}/toggle-favorite
DELETE /api/v1/uploads/bulk
POST   /api/v1/uploads/zip-requests
GET    /api/v1/uploads/zip-requests/{uuid}
GET    /api/v1/uploads/zip-requests/{uuid}/download
```

### Listagem

Request:

```txt
GET /api/v1/uploads?parentable_type=supplier&parentable_id=1&tag=contract&collection=docs
```

Resposta esperada pelo frontend: resposta paginada Laravel Resource, com `data` contendo uma lista de uploads.

Cada item deve ter este formato:

```ts
interface Upload {
  id: number
  name: string
  original_name: string | null
  file_name: string
  extension: string | null
  mime_type: string
  size: number | null
  size_human: string
  url: string
  download_url: string
  preview_url: string
  icon: string
  is_image: boolean
  is_cover: boolean
  is_favorite: boolean
  tag: string | null
  collection: string | null
  source: string
  created_at: string | null
  updated_at: string | null
  uploader?: { id: number | null; name: string | null }
  permissions: {
    can_update: boolean
    can_delete: boolean
  }
}
```

Campos criticos:

- `preview_url`: usado para preview de imagem/documento.
- `download_url`: usado para download protegido.
- `permissions.can_update`: controla renomear, tag, favorito e capa.
- `permissions.can_delete`: controla exclusao.
- `icon`: deve ser um destes valores conhecidos pelo frontend: `image`, `video`, `music`, `file-text`, `file-spreadsheet`, `file`.

### Upload

Request:

```txt
POST /api/v1/uploads
Content-Type: multipart/form-data
```

Campos:

```txt
parentable_type
parentable_id
files[]
name?
tag?
collection?
is_cover?
```

Resposta:

```json
{
  "message": "Arquivo enviado com sucesso.",
  "data": []
}
```

`data` deve conter os uploads criados no mesmo formato da listagem.

### Atualizacao

```txt
PUT /api/v1/uploads/{id}
```

Payload:

```json
{
  "name": "Contrato assinado",
  "tag": "contract",
  "collection": "docs"
}
```

Resposta: um `Upload`.

### Tags

```txt
GET /api/v1/uploads/tags?parentable_type=supplier
```

Resposta:

```json
{
  "data": [
    { "value": "contract", "label": "Contrato" },
    { "value": "document", "label": "Documento" }
  ]
}
```

## Backend atual em Laravel

O backend fica em:

```txt
app/Modules/Uploads
```

Arquivos principais:

- `Config/uploads.php`: disco, limite, prefixo de rotas, middleware e aliases.
- `Providers/UploadsServiceProvider.php`: registra config, rotas e policy.
- `Routes/api.php`: endpoints do modulo.
- `Models/Uploaded.php`: model dos anexos.
- `Services/UploadService.php`: resolve model, salva arquivo, lista, atualiza, capa, favorito e delete.
- `Traits/HasUploads.php`: adiciona relacao `morphMany` nos models donos de anexos.
- `Contracts/HasUploadTags.php`: contrato para sugestoes de tags.
- `Policies/UploadedPolicy.php`: autorizacao.
- `Http/Resources/UploadedResource.php`: formato consumido pelo frontend.
- `Jobs/GenerateZipJob.php`: geracao de ZIP.

## Como habilitar um model para anexos

No model:

```php
use App\Modules\Uploads\Contracts\HasUploadTags;
use App\Modules\Uploads\Traits\HasUploads;

class Supplier extends Model implements HasUploadTags
{
    use HasUploads;

    public static function uploadTagSuggestions(): array
    {
        return [
            'contract' => 'Contrato',
            'document' => 'Documento',
            'other' => 'Outro',
        ];
    }
}
```

No config:

```php
'aliases' => [
    'supplier' => \App\Models\Supplier::class,
],
```

No frontend:

```tsx
<FileAttachments modelType="supplier" modelId={supplier.id} />
```

## Estrategia recomendada para portar

### 1. Portar primeiro o contrato, nao a tela

Antes de copiar UI, garanta que o outro sistema consegue responder ao contrato da API. O componente e relativamente independente quando recebe:

- lista paginada no formato esperado;
- upload multipart;
- update/delete;
- URLs protegidas de preview/download;
- permissoes por item.

### 2. Copiar o modulo frontend inteiro

Copie:

```txt
resources/js/modules/uploads
```

Depois ajuste imports que comecam com `@/`:

- `@/lib/http`
- `@/lib/utils`
- `@/components/ui/*`
- `@/components/delete-confirm-modal`
- `@/stores/toast-store`

Evite copiar apenas `file-attachments.tsx`, porque ele depende de hooks, componentes auxiliares, tipos, endpoints e store de ZIP.

### 3. Adaptar o cliente HTTP

O modulo espera helpers com comportamento parecido com:

```ts
http.get/post/put/delete
httpGet<T>(url, config)
httpPost<T>(url, data)
httpPut<T>(url, data)
httpDelete<T>(url, config?)
toApiError(error)
getErrorMessage(error)
```

Se o outro sistema ja usa Axios, a adaptacao e pequena. Se usa `fetch`, crie um adapter fino mantendo as mesmas assinaturas ou ajuste `api/queries.ts`, `api/mutations.ts`, `use-upload-queue.ts` e `use-zip-request.ts`.

### 4. Adaptar UI sem mexer na regra de negocio

Os componentes visuais podem ser trocados aos poucos. Preserve estes comportamentos:

- `UploadDropzone` deve chamar `onDrop(accepted, rejected)`.
- `UploadItem` deve respeitar `permissions.can_update` e `permissions.can_delete`.
- `UploadQuickPreview` deve usar `preview_url` e navegar entre itens.
- `BulkActionBar` deve chamar exclusao em lote e ZIP com os IDs selecionados.

### 5. Portar ou recriar o backend

Se o outro sistema tambem for Laravel, o caminho mais rapido e copiar `app/Modules/Uploads`, migrations relacionadas e registrar o provider.

Se o outro backend nao for Laravel, nao tente replicar a estrutura PHP. Replique apenas o contrato HTTP e as regras:

- relacionamento polimorfico entre anexo e dono;
- armazenamento do arquivo em disco ou storage remoto;
- metadados do arquivo;
- autorizacao por usuario/perfil;
- endpoints de preview/download protegidos;
- resposta no formato `Upload`.

### 6. Decidir se o ZIP entra na primeira versao

ZIP em background aumenta a complexidade porque exige:

- tabela/model de solicitacao de ZIP;
- job/worker;
- storage privado para arquivos gerados;
- polling no frontend;
- painel global `ZipDownloadPanel`.

Para uma migracao mais segura, voce pode portar em duas fases:

1. Upload, listagem, preview, download, update e delete.
2. Selecao multipla, delete em lote e ZIP.

## Checklist de migracao

- [ ] Copiar `resources/js/modules/uploads`.
- [ ] Garantir `QueryClientProvider` do React Query no app.
- [ ] Instalar/validar `@tanstack/react-query`, `axios`, `lucide-react` e `zustand`.
- [ ] Adaptar imports de UI.
- [ ] Adaptar `@/lib/http`.
- [ ] Adaptar `@/stores/toast-store`.
- [ ] Conferir prefixo em `api/endpoints.ts`.
- [ ] Criar endpoints backend com o contrato esperado.
- [ ] Criar tabela/model de uploads.
- [ ] Implementar policy/permissoes.
- [ ] Registrar aliases dos models que terao anexos.
- [ ] Implementar sugestoes de tags, mesmo que retorne lista vazia.
- [ ] Testar upload de arquivo pequeno.
- [ ] Testar permissao de preview/download com outro usuario.
- [ ] Testar arquivo grande acima do limite.
- [ ] Testar `readOnly`.
- [ ] Testar tag fixa e `defaultTag`.
- [ ] Testar exclusao e cache do React Query.
- [ ] Portar ZIP somente depois do fluxo basico estar estavel.

## Pontos de atencao

### Autorizacao

Hoje a policy permite acesso ao dono/uploader:

```php
return $upload->uploader_id === $user->id
    || $upload->user_id === $user->id;
```

Em outro sistema, provavelmente isso precisa considerar perfil, empresa, tenant, permissao do parent model ou escopo organizacional. Esse e o ponto mais importante da adaptacao.

### `modelType` como alias

Nao envie nome de classe do backend pelo frontend. O alias e melhor porque:

- evita expor namespace interno;
- permite renomear classes sem quebrar o frontend;
- reduz risco de resolver models indevidos.

### Preview e download

Mesmo que o arquivo esteja em disco publico, mantenha `preview_url` e `download_url` passando por rota protegida quando houver dados sensiveis. O componente ja usa essas URLs.

### Duplicidade

O `UploadService` atual evita duplicar o mesmo arquivo no mesmo parent/tag usando hash SHA-256. Se isso for importante no outro sistema, replique essa regra.

### IDs e UUID

O backend atual tenta buscar primeiro por PK e, se `modelId` for string, tenta buscar por `uuid`. Se o outro sistema usa UUID como chave principal, adapte `resolveModel`.

### Codificacao

Alguns textos exibidos no snapshot atual aparecem com caracteres quebrados, como `ReferÃªncia`. Antes de reutilizar a pagina dev em outro sistema, salve os arquivos como UTF-8 e revise os textos visiveis.

## Melhor desenho para outro sistema

Para adaptar com baixa manutencao, mantenha estas fronteiras:

- `FileAttachments` continua sendo componente de alto nivel.
- `api/*` concentra contrato HTTP.
- `hooks/*` concentra estado assincromo, fila e polling.
- `components/*` concentra UI.
- Backend entrega sempre o mesmo DTO `Upload`.
- Models do dominio conhecem apenas `HasUploads` e aliases.

Evite misturar regras do dominio dentro do componente. Por exemplo, nao coloque condicoes como "se for fornecedor pode excluir" no React. O backend deve calcular `permissions.can_delete`, e a UI apenas respeita.

## Caminho minimo sem ZIP

Se quiser levar apenas o essencial, comece com:

- `file-attachments.tsx`
- `upload-dropzone.tsx`
- `upload-item.tsx`
- `upload-queue-item.tsx`
- `upload-quick-preview.tsx`
- `tag-combobox.tsx`
- `filter-files.ts`
- `use-upload-queue.ts`
- `use-uploads.ts`
- `api/endpoints.ts`
- `api/keys.ts`
- `api/queries.ts`
- `api/mutations.ts`
- `api/types.ts`

Depois remova temporariamente chamadas de ZIP e `BulkActionBar`, ou mantenha o codigo e implemente os endpoints correspondentes.

## Conclusao

A melhor forma de portar nao e copiar somente o arquivo da tela dev. O ideal e migrar o modulo `uploads` como uma unidade, adaptar as bordas do frontend e garantir que o novo backend respeite o contrato de dados. A parte mais sensivel e a autorizacao: preview, download, delete, favorito, capa e ZIP precisam validar permissao no servidor, nao apenas esconder botoes no React.
