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
- Listagem paginada de 50 arquivos por pagina, com busca remota por nome, nome original, nome fisico, tag e extensao.
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

`showCoverOption`: oferece a acao de definir imagem como capa quando o DTO tambem permite `permissions.can_set_cover`.

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
GET    /uploads/{upload}/preview
GET    /uploads/{upload}/download
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
GET /api/v1/uploads?parentable_type=supplier&parentable_id=1&tag=contract&collection=docs&search=contrato&page=2&per_page=50
```

Resposta esperada pelo frontend: resposta paginada Laravel Resource, com `data` contendo uma lista de uploads.

O componente usa `meta.total`, `meta.current_page` e `meta.last_page`; as paginas seguem `created_at DESC, id DESC`, inclusive quando varios arquivos tem a mesma data. A busca imediata consulta os cinco campos no servidor dentro do mesmo parent/tag/collection. `%`, `_` e o escape `!` sao texto literal, sem permitir curingas; igualdade e sensibilidade a maiusculas seguem a collation do banco. `tag`/`collection` nulos ou vazios na listagem significam ausencia desse filtro; o texto `0` permanece um filtro valido. Essa semantica de listagem nao altera a identidade nula exata da deduplicacao de upload.

Mudar busca ou pagina limpa selecao, preview e confirmacoes antes da nova consulta. A selecao multipla inclui apenas a pagina atual; depois de excluir a ultima linha de uma pagina final, o componente volta a ultima pagina existente. Erros de listagem aparecem com acao de tentar novamente, separados de uma lista vazia. Trocar parent, tag, collection ou defaultTag remonta o corpo e cancela a fila anterior, sem redirecionar arquivos pendentes para outro destino.

As mutacoes nao alteram listas de forma otimista. Falhas conservam cada resposta e seus metadados na propria queryKey; nao existe rollback compartilhado entre filtros nem restauracao concorrente que possa ressuscitar outra exclusao bem-sucedida. Upload, metadata, exclusao simples/em lote, capa e favorito invalidam as listas do parent depois do sucesso. A fila usa o parent capturado pela requisicao. O servidor recalcula pertencimento, ordem, contagem e paginas, evitando inserir um upload em colecoes ou buscas que nao correspondem a ele. As protecoes de sessao continuam descartando callbacks antigos.

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
    can_set_cover: boolean
  }
}
```

Campos criticos:

- `preview_url`: usado para preview de imagem/documento.
- `download_url`: usado para download protegido.
- `permissions.can_update`: controla renomear, tag e favorito.
- `permissions.can_delete`: controla exclusao.
- `permissions.can_set_cover`: autoriza a acao de capa quando `showCoverOption` esta ativo.
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
- `UploadItem` deve respeitar `permissions.can_update`, `permissions.can_delete` e `permissions.can_set_cover`.
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

A leitura privada exige usuario com e-mail verificado, parent existente e permissao atual de visualizar esse parent. Ser dono (`user_id`) ou uploader (`uploader_id`) nao permite ler arquivos de um parent inacessivel ou removido. As `public_rules` explicitas do preview continuam sendo a excecao de leitura documentada abaixo.

Atualizar metadata, alternar favorito e apagar exigem essa mesma leitura e, adicionalmente, propriedade do arquivo ou permissao de atualizar o parent. Um leitor do parent nao pode gerenciar anexos alheios. Revogar a leitura do parent tambem revoga a gestao dos proprios anexos; restaurar o acesso legitimo recupera o fluxo. Nao ha bypass por nome de papel.

Definir capa usa a habilidade separada `setCover`: exige leitura e permissao de atualizar o parent, mesmo para o dono do arquivo, pois a operacao remove a capa dos outros anexos do mesmo parent. O DTO fornece `permissions.can_update`, `permissions.can_delete` e `permissions.can_set_cover` calculados pelas Gates. A UI combina `showCoverOption` com `can_set_cover`; nunca calcula autoridade pelo papel ou pela propriedade no navegador. A demo nao ativa `showCoverOption` por padrao.

Bulk e criacao de ZIP conferem as policies de todos os alvos antes de escrever/enfileirar. As queries carregam o parent e, no caso de TimelineComment, seu `commentable`, preservando a prevencao de lazy loading. O worker de ZIP tambem relê o solicitante elegivel (e-mail verificado e sem troca de senha pendente) e a Gate `view` de cada parent antes de ler os arquivos. O download pronto conserva o snapshot materializado do proprietario; nao reavalia a policy historica dos anexos.

Ao adaptar para outro sistema, preserve as policies do parent e a separacao entre leitura, propriedade e efeitos compartilhados. Perfil, tenant e escopo organizacional devem seguir o contrato daquele sistema.

### `modelType` como alias

Nao envie nome de classe do backend pelo frontend. O alias e melhor porque:

- evita expor namespace interno;
- permite renomear classes sem quebrar o frontend;
- reduz risco de resolver models indevidos.

### Preview e download

As URLs reais de arquivo sao `GET /uploads/{id}/preview` e `GET /uploads/{id}/download`; listagem e mutacoes continuam em `/api/v1/uploads`. O DTO fornece essas URLs. Preview consulta as public_rules existentes ou autoriza view pela policy; download exige autenticacao web e view. A permissao e verificada antes de acessar o disco.

O controller inspeciona ate 64 KiB dos bytes por stream e fecha essa leitura antes de transmitir a resposta. MIME persistido, MIME do adapter e extensao sozinhos nao concedem inline. Para inline, exige MIME detectado da allowlist e coerencia de extension, original_name quando presente, file_name e path. A lista explicita no controller contempla PDF, imagens raster JPEG/PNG/GIF/WebP/AVIF/BMP e formatos conhecidos de audio/video. SVG, HTML, XML, JavaScript, texto, arquivos desconhecidos/divergentes e falhas de classificacao saem como `attachment`/`application/octet-stream`. Arquivos privados arbitrarios continuam aceitos pelo upload; essa regra governa a resposta, inclusive para registros antigos.

Download sempre usa `attachment`; preserva o MIME detectado seguro quando coerente e usa octet-stream nos demais casos. Preview/download enviam `X-Content-Type-Options: nosniff` e `Content-Security-Policy: script-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'`. Nomes/disposition usam o encoder do filesystem/Symfony. A amostra limitada classifica o tipo, sem afirmar validacao integral ou saneamento de todo PDF/arquivo. Registros e bytes antigos nao sao reescritos na leitura.

Esses headers pertencem as rotas do controller. Publicacao direta por URL de disco publico/S3 precisa configuracao e validacao proprias; nao herda essa protecao. Service usa disk local privado por padrao e public_rules vazia, mas a migration antiga defaulta disk=public em inserts que omitam o campo. A verificacao de perfis de storage/publicacao e acompanhada em QA-001; nao declarar URL direta protegida por este contrato.

Mesmo que o arquivo esteja em disco publico, mantenha `preview_url` e `download_url` passando por rota protegida quando houver dados sensiveis. O componente ja usa essas URLs.

### Duplicidade

O `UploadService::upload()` reutiliza um registro ativo somente quando pai (tipo e ID), collection, tag e hash SHA-256 coincidem. `null` em collection/tag significa `IS NULL`, nunca um filtro curinga; string vazia e null permanecem distintos no service. Valores nao nulos seguem a igualdade/collation do banco, como a listagem: MySQL com `utf8mb4_unicode_ci` pode considerar `Docs`/`docs` e `Receipt`/`receipt` equivalentes. Um match retorna o registro existente sem regravar arquivo, nome ou capa; registros soft-deleted nao participam.

Chamadas concorrentes cooperativas ao service sao serializadas por lock do pai persistido, em transacao na conexao efetiva de `Uploaded`. O pai deve usar a mesma instancia de conexao/sessao; conexoes distintas sao recusadas antes da escrita, sem redirecionar a persistencia do anexo para outro banco. O lookup tambem usa leitura com lock para enxergar um vencedor ja confirmado mesmo quando o chamador abriu uma transacao REPEATABLE READ com snapshot anterior. A transacao faz uma unica tentativa; nao ha replay automatico de escrita de arquivo. Nao existe constraint unica nova: inserts diretos, mudancas de metadados e duplicatas historicas ficam fora desta garantia.

Falha de escrita reportada como `false` nao cria registro. Falha ordinaria ao criar o registro antes do COMMIT reverte metadados/capa e tenta remover somente o novo arquivo; uma falha de limpeza permanece visivel ao chamador. Nao ha garantia de limpeza concluida: `delete()` pode retornar false ou lancar, e `putFileAs()` pode lancar depois de uma escrita parcial fora do bloco compensado, deixando residuo sem limpeza automatica. Isso nao torna banco e filesystem uma transacao atomica: falha de COMMIT com resultado incerto ou rollback posterior de uma transacao externa nao autoriza apagar automaticamente um arquivo que pode estar referenciado. Deadlocks/contencao de locks podem falhar a requisicao sem retentativa implicita; a reconciliacao de residuos exige diagnostico proprio.

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


## Selecao do ZIP

O POST existente aceita exatamente um modo: `ids` (selecao explicita, maximo de 200) ou `all_matching` (todos os resultados dos filtros ativos). O botao "Baixar todos" usa o segundo modo, incluindo resultados fora da pagina atual; fica indisponivel durante a consulta e quando existem mais de 200 resultados, com orientacao para refinar a busca. O ZIP da selecao multipla continua enviando IDs da pagina atual.

```json
{
  "all_matching": {
    "parentable_type": "supplier",
    "parentable_id": 1,
    "tag": "contract",
    "collection": "docs",
    "search": "contrato"
  },
  "label": "Contratos"
}
```

`all_matching` compartilha a consulta de listagem, exige autorizacao atual do parent e a Gate `view` de cada arquivo antes de criar o pedido. O servidor busca no maximo 201 linhas: mais de 200 recebe 422 com pedido para refinar os filtros; nenhuma linha recebe 422. Nao ha truncamento silencioso nem promessa de ZIP ilimitado. Os IDs autorizados sao materializados no pedido e seguem para o mesmo `GenerateZipJob`; arquivos adicionados depois nao entram nesse snapshot. Enviar ambos os modos, aliases desconhecidos, identificador invalido ou chaves de filtro nao previstas recebe 422.

## Ciclo de ZIP e retencao (ZIP-001)

A solicitacao existente cria um UUID novo em `pending`. Apenas uma entrega pode assumir `processing`; reentregas de `processing`, `ready` ou `failed` nao alteram o pedido nem o arquivo. Um pedido pendente abandonado vence em 120 segundos. O processamento tem deadline cooperativo de 60 segundos, o mesmo timeout declarado pelo job, uma tentativa e `failOnTimeout`. No Windows isso nao prova interrupcao automatica de I/O bloqueante: o prazo e conferido entre operacoes e antes da publicacao, e abandonados precisam de reconciliacao.

O job usa streams dos discos originais, sem exigir caminhos locais. Cada tentativa serializa seu token e grava no disco `local` privado configurado em `zip-requests/{requestUUID}/{attemptUUID}/archive.zip`. Staging e `archive.part` ficam no mesmo namespace. Todos os membros, copias (incluindo tamanho quando conhecido), inclusoes e fechamento precisam confirmar sucesso antes de publicar; falta de um membro invalida o conjunto. ZIP pronto nao e sobrescrito por reentrega. Erros retornados ao cliente sao genericos; detalhes ficam no log privado.

O ZIP fica disponivel por duas horas apos concluir. Os valores estao em `uploads.zip.processing_timeout`, `pending_timeout` e `retention_hours`. Legado `ready` sem `expires_at` usa `updated_at + retention_hours`, sem renovar por consulta. O layout legado `zip-requests/{requestUUID}.zip` ainda e aceito dentro do disco privado configurado; arquivos fora dele, paths invalidos, publicos ou redirecionados ficam indisponiveis e exigem nova solicitacao. Nao se move ou apaga arquivo original para recuperar ZIP.

Status/download conferem o proprietario antes de acessar o disco. Consultar status reconcilia prazo e arquivo pronto ausente/invalido. Download nao executa o gerador: `pending`, `failed` ou expirado recusam 422; um registro pronto cujo arquivo sumiu recusa 404; outro proprietario recebe 404. Para recuperar, repetir o POST existente com a selecao autorizada cria outro UUID, preservando a falha anterior. Nao ha endpoint de retry do mesmo pedido.

`php artisan uploads:prune-zips --no-interaction` percorre pedidos em lotes de 100, reconcilia abandonados/expirados e limpa somente artefatos canônicos identificados. Falhas de remocao conservam `zip_path`, geram log e exit diferente de zero para uma nova tentativa de limpeza. Registros `failed` ficam preservados para observacao. A tarefa esta declarada por hora com `withoutOverlapping`; a operacao continua de scheduler/workers e orcamentos globais pertencem a OPS-001/OPS-002. O lote ZIP-001 valida a declaracao, sem operar scheduler ou producao.
