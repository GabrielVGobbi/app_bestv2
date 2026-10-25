# Módulo de Notificações

Documentação completa do módulo de notificações. Cobre criação, exibição, leitura, exclusão, ações executáveis, realtime via Reverb/Echo e checklist para adaptar em novos projetos.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Fluxo Completo](#2-fluxo-completo)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Backend](#4-backend)
   - [Migration](#41-migration)
   - [Enums](#42-enums)
   - [DTO](#43-dto)
   - [Model AppNotification](#44-model-appnotification)
   - [Actions](#45-actions)
   - [Services](#46-services)
   - [Ações Executáveis](#47-ações-executáveis)
   - [Controller API](#48-controller-api)
   - [Resource](#49-resource)
   - [Rotas](#410-rotas)
   - [Segurança](#411-segurança)
5. [Frontend](#5-frontend)
   - [Tipos TypeScript](#51-tipos-typescript)
   - [Service](#52-service)
   - [Contexto Global](#53-contexto-global)
   - [Hook useNotifications](#54-hook-usenotifications)
   - [Hook useNotificationBell](#55-hook-usenotificationbell)
   - [Componente NotificationBell](#56-componente-notificationbell)
   - [Componente NotificationItem](#57-componente-notificationitem)
   - [Página Completa](#58-página-completa)
6. [Realtime com Reverb + Echo](#6-realtime-com-reverb--echo)
7. [Toasts Automáticos](#7-toasts-automáticos)
8. [Como Criar uma Notificação no Backend](#8-como-criar-uma-notificação-no-backend)
9. [Como Adicionar uma Nova Ação Executável](#9-como-adicionar-uma-nova-ação-executável)
10. [Como Adicionar ao Header](#10-como-adicionar-ao-header)
11. [Checklist para Novo Projeto](#11-checklist-para-novo-projeto)
12. [Referência da API](#12-referência-da-api)
13. [Armadilhas Conhecidas](#13-armadilhas-conhecidas)

---

## 1. Visão Geral

### Problema resolvido

Um sistema de notificações precisa ser mais do que uma tabela. Precisa suportar tipos variados, ações executáveis pelo backend, realtime via WebSocket, toasts automáticos por urgência e ser reutilizável em qualquer projeto Laravel + React/Inertia.

### Capacidades

| Recurso | Detalhe |
|---------|---------|
| Tipos | system, user, mention, social, action, warning, error, success, info |
| Categorias | general, export, import, payment, comment, mention, task, post, follow, like, message, security, account, system |
| Ações | Botões na notificação executam Actions PHP reais no backend |
| Realtime | Laravel Reverb + Echo — canal privado por usuário |
| Toasts | warning, error, system disparam toast automático ao chegar |
| Bell | Dropdown no header com badge de não lidas, auto-atualiza |
| Página | Lista paginada com filtros por tipo e status de leitura |
| Segurança | Todas as queries escopadas ao `recipient_id` do usuário logado |

---

## 2. Fluxo Completo

```
Backend cria notificação
    → AppNotification::create()
    → broadcast(NotificationCreated) → Reverb
        → Echo escuta canal privado user.{id}.notifications
        → Bell atualiza badge (+1)
        → Toast dispara se type = warning/error/system

Usuário abre dropdown
    → NotificationBell → useNotificationBell (Context)
    → Lista as últimas não lidas

Usuário clica em "marcar como lida"
    → PATCH /api/v1/notifications/{uuid}
    → Estado local atualizado otimisticamente

Usuário clica em ação da notificação
    → POST /api/v1/notifications/{uuid}/execute-action
    → Backend valida ownership + action_status + action_type
    → Resolve NotificationExecutableAction → executa
    → Retorna resultado → frontend atualiza status
```

---

## 3. Estrutura de Arquivos

### Backend

```
app/
├── Events/
│   └── NotificationCreated.php           # Broadcast realtime
├── Http/
│   ├── Controllers/
│   │   ├── NotificationController.php     # Página Inertia
│   │   └── Api/
│   │       └── NotificationApiController.php  # JSON API
│   ├── Requests/
│   │   └── ExecuteNotificationActionRequest.php
│   └── Resources/
│       └── NotificationResource.php
├── Modules/
│   └── Notifications/
│       ├── Actions/
│       │   ├── CreateNotificationAction.php
│       │   ├── MarkAsReadAction.php
│       │   ├── MarkAllAsReadAction.php
│       │   ├── DeleteNotificationAction.php
│       │   └── Executable/
│       │       ├── Contracts/
│       │       │   └── NotificationExecutableAction.php
│       │       ├── ApproveRequestNotificationAction.php
│       │       ├── RejectRequestNotificationAction.php
│       │       └── DownloadExportNotificationAction.php
│       ├── Database/
│       │   └── Migrations/
│       │       └── *_create_app_notifications_table.php
│       ├── DTOs/
│       │   └── CreateNotificationDTO.php
│       ├── Enums/
│       │   ├── NotificationActionType.php
│       │   ├── NotificationActionVariant.php
│       │   ├── NotificationCategory.php
│       │   └── NotificationType.php
│       ├── Models/
│       │   └── AppNotification.php
│       ├── Providers/
│       │   └── NotificationsServiceProvider.php
│       └── Services/
│           ├── MentionService.php
│           ├── NotificationActionResolver.php
│           └── NotificationService.php
└── Supports/
    └── Traits/
        └── HasNotifications.php
```

### Frontend

```
resources/js/
├── contexts/
│   └── notification-bell-context.tsx     # Provider global (montado em app.tsx)
├── hooks/
│   └── use-notifications.ts              # useNotifications + useNotificationBell
├── components/
│   ├── notification-bell.tsx             # Bell com dropdown
│   └── notification-item.tsx            # Item individual
├── pages/
│   └── notifications/
│       └── index.tsx                    # Página completa
├── services/
│   └── notification-service.ts          # Chamadas API
├── types/
│   └── notification.ts                  # Tipos TypeScript
└── lib/
    └── echo.ts                          # Singleton Laravel Echo
```

---

## 4. Backend

### 4.1 Migration

Tabela: **`app_notifications`** (não `notifications` — evita conflito com a tabela nativa do Laravel).

Colunas principais:

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `uuid` | uuid, unique | Identificador público — usado nas rotas de API |
| `recipient_id` | foreignId → users | Quem recebe |
| `sender_id` | foreignId nullable → users | Quem enviou (null = sistema) |
| `type` | string | Enum `NotificationType` |
| `category` | string | Enum `NotificationCategory` |
| `title` | string | Título exibido |
| `body` | text | Corpo da notificação |
| `data` | json, nullable | **Só dados de exibição e IDs auxiliares** — nunca regras de negócio |
| `actions` | json, nullable | Array de `{label, type, variant, requires_confirmation, ...}` |
| `action_status` | string, nullable | null / pending / executed / rejected / expired / cancelled |
| `link` | string, nullable | URL para navegar ao clicar |
| `notifiable_type` / `notifiable_id` | morphs, nullable | Entidade relacionada |
| `read_at` | timestamp, nullable | null = não lida |
| `clicked_at` | timestamp, nullable | Rastreia clique |

Índices compostos obrigatórios para performance:
- `[recipient_id, read_at]`
- `[recipient_id, created_at]`
- `[recipient_id, read_at, created_at]`

### 4.2 Enums

**`NotificationType`** — controla o visual e urgência:
```php
System, User, Mention, Social, Action, Warning, Error, Success, Info
```

**`NotificationCategory`** — filtragem semântica:
```php
General, Export, Import, Payment, Comment, Mention, Task, Post,
Follow, Like, Message, Security, Account, System
```

**`NotificationActionType`** — tipo de ação do botão:
```php
View, Approve, Reject, Download, Reply, Open, Confirm, Custom
```

**`NotificationActionVariant`** — estilo do botão:
```php
Primary, Secondary, Destructive, Ghost, Outline, Success, Warning
```

### 4.3 DTO

```php
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;

// Notificação de sistema
$dto = CreateNotificationDTO::system(
    recipientId: $user->id,
    title: 'Exportação concluída',
    body: 'Seu arquivo está pronto para download.',
    category: NotificationCategory::Export,
    data: ['export_uuid' => $export->uuid, 'file_name' => 'users.xlsx'],
    link: route('exports.index'),
);

// Notificação de usuário para usuário
$dto = CreateNotificationDTO::fromUser(
    senderId: auth()->id(),
    recipientId: $targetUser->id,
    title: 'Nova tarefa atribuída',
    body: 'João atribuiu uma tarefa para você.',
    category: NotificationCategory::Task,
);

// Esquema ilustrativo: approve/reject exigem handlers reais registrados no servidor.
// Sem esses handlers, a execução retorna 422 e preserva a pendência.
$dto = CreateNotificationDTO::withActions(
    senderId: auth()->id(),
    recipientId: $targetUser->id,
    title: 'Solicitação pendente',
    body: 'Você tem uma solicitação aguardando aprovação.',
    category: NotificationCategory::Task,
    actions: [
        ['label' => 'Aprovar', 'type' => 'approve_request', 'variant' => 'primary',
         'requires_confirmation' => true, 'confirmation_message' => 'Confirmar aprovação?'],
        ['label' => 'Recusar', 'type' => 'reject_request', 'variant' => 'destructive',
         'requires_confirmation' => true, 'confirmation_message' => 'Confirmar recusa?'],
    ],
);
```

> **Regra do campo `data`**: apenas informações de exibição e IDs auxiliares (ex: `export_uuid`, `file_name`, `entity_id`). **Nunca** preços, permissões, regras de negócio ou dados sensíveis.

### 4.4 Model AppNotification

Localização: `app/Modules/Notifications/Models/AppNotification.php`

- Table: `app_notifications`
- Usa trait `HasUuidIdentifier` (UUID automático + route key binding)
- Casts: `type → NotificationType`, `category → NotificationCategory`, `data → array`, `actions → array`

**Scopes disponíveis:**
```php
AppNotification::unread()                    // WHERE read_at IS NULL
AppNotification::read()                      // WHERE read_at IS NOT NULL
AppNotification::forRecipient($user)         // WHERE recipient_id = ?
AppNotification::ofType(NotificationType::Warning)
AppNotification::ofCategory(NotificationCategory::Export)
```

**Métodos de instância:**
```php
$notification->markAsRead();       // Seta read_at = now()
$notification->markAsClicked();    // Seta clicked_at = now()
$notification->isRead();           // bool
$notification->isUnread();         // bool
$notification->hasActions();       // bool
$notification->isActionPending();  // action_status === null || pending
```

### 4.5 Actions

Cada ação tem uma única responsabilidade:

| Classe | Responsabilidade |
|--------|-----------------|
| `CreateNotificationAction` | Cria notificação + faz broadcast |
| `MarkAsReadAction` | Seta `read_at` |
| `MarkAllAsReadAction` | Marca todas do usuário como lidas |
| `DeleteNotificationAction` | Deleta a notificação |

```php
// Criar
app(CreateNotificationAction::class)->handle($dto);

// Marcar como lida
app(MarkAsReadAction::class)->handle($notification);

// Marcar todas
app(MarkAllAsReadAction::class)->handle($user);

// Deletar
app(DeleteNotificationAction::class)->handle($notification);
```

### 4.6 Services

**`NotificationService`** — orquestra criação e listagem:
```php
$service = app(NotificationService::class);

$service->create($dto);
$service->notifyMentions($text, $author, $title, $body, $link);
$service->listForUser($user, $filters);        // paginada
$service->unreadCountForUser($user);           // int
$service->recentUnreadForUser($user, $limit);  // Collection
```

**`MentionService`** — extrai `@usuario` do texto:
```php
$service = app(MentionService::class);

// Retorna Collection<User> mencionados (exclui autor)
$users = $service->usersFromText($comment->body, auth()->user());

// Só os usernames
$usernames = $service->extractUsernames($text); // ['joao', 'maria']
```

### 4.7 Ações Executáveis

O contrato existente continua sendo `NotificationExecutableAction::execute(AppNotification $notification, User $user, array $payload = []): mixed`.

O resolver contém somente handlers implementados. O mapa padrão é:

```php
protected array $map = [
    'download_export' => DownloadExportNotificationAction::class,
];
```

`approve_request` e `reject_request` são exemplos de domínio sem implementação. Não são registrados por padrão e seus templates recusam HTTP 422 mesmo se chamados diretamente ou registrados por engano. Não retornam sucesso nem mudam `action_status`. Notificações antigas com esses botões recebem uma recusa explícita. Para ativá-los em um projeto derivado, implemente uma classe real, teste sua entidade/policy/transição e registre-a explicitamente no servidor; copiar o template desativado não basta.

Cada handler deve reler a entidade vinculada, recusar entidade ausente e consultar a policy da operação para o ator. Ser destinatário da notificação não concede autoridade sobre a entidade. Falhas e recusas precisam lançar uma exceção; retornar um array que apenas descreve erro seria interpretado como sucesso pelo contrato `mixed`.

As escritas que devem ser revertidas junto do estado da notificação precisam usar a mesma conexão/transação fornecida pelo model. Para duas notificações diferentes referentes à mesma entidade, o próprio domínio deve proteger sua transição e idempotência. O bloqueio de uma notificação não substitui essa regra.

Efeitos externos exigem integração própria após commit e idempotência/durabilidade conforme o domínio. `afterCommit` impede um envio antes de rollback, mas sozinho não garante entrega após uma queda nem exatamente uma execução externa. Nenhuma mensagem externa faz parte das verificações deste lote.

### 4.8 Controller API e execução atômica

`app/Http/Controllers/Api/NotificationApiController.php` delega a execução ao `NotificationService` existente, preservando a resposta `{ action_type, result }`. O payload aceito como null pelo request é normalizado para `[]`.

O serviço abre uma transação na conexão da notificação e a relê pelo ID sob `lockForUpdate`. Dentro do bloqueio, verifica o destinatário atual, existência de ações, estado null/pending, presença do tipo solicitado e registro de handler. Assim, alterações posteriores ao route binding não são autorizadas por atributos antigos.

O handler executa e o estado terminal é gravado no mesmo commit (`rejected` para `reject_request` implementado; `executed` para os demais). Erro ou recusa revertem as escritas da mesma conexão e conservam o estado anterior. O executor exige sucesso da gravação terminal: veto de observer que retorna `false` também provoca rollback. Não há retry automático do callback. Uma solicitação posterior encontra o estado terminal e recebe HTTP 409 sem chamar novamente o handler. O teste de duas conexões MySQL é a prova de exclusão; SQLite em memória não comprova o lock InnoDB.

As demais mutações mantêm os checks de destinatário já existentes. Autenticação, elegibilidade de conta, limites de token e escopo privado permanecem obrigatórios nas rotas; o serviço não cria um bypass por nome de papel.

### 4.9 Resource

`app/Http/Resources/NotificationResource.php`

O campo `data` é filtrado por uma allowlist explícita:
```php
protected function resolvePublicData(): array
{
    $allowed = ['export_uuid', 'file_name', 'format', 'total_records',
                'preview_url', 'entity_name', 'entity_id'];
    return array_intersect_key($this->data ?? [], array_flip($allowed));
}
```

> Para expor novas chaves do campo `data`, adicionar à allowlist neste método.

### 4.10 Rotas

**API** (`routes/api.php`, dentro de `auth:sanctum`):
```
GET    /api/v1/notifications                      → index (paginado)
GET    /api/v1/notifications/unread               → últimas não lidas
GET    /api/v1/notifications/unread-count         → { count: N }
POST   /api/v1/notifications/mark-all-read        → marcar todas
PATCH  /api/v1/notifications/{notification}       → marcar uma como lida
DELETE /api/v1/notifications/{notification}       → deletar
POST   /api/v1/notifications/{notification}/execute-action → executar ação
```

A SPA atual usa sessão/cookies pelo Sanctum. Para requisições autenticadas por PAT, cada operação exige o scope literal correspondente:

| Operação | Scope do PAT |
|---------|--------------|
| Listar, consultar não lidas e contar não lidas | `view-notifications` |
| Marcar uma ou todas como lidas | `mark-notifications-read` |
| Excluir | `delete-notifications` |

`execute-action` é recusado para PAT, inclusive com esses scopes; NTF-002 não cria novo scope de token nem muda essa recusa. Os três scopes limitam tokens e não são permissões funcionais delegáveis. Consulte o catálogo fechado de nove scopes e as regras de emissão em [SEC-014](../../stabilization/tasks/SEC-014.md#contrato-técnico-adotado-no-lote).

**Web** (`routes/web.php`):
```
GET /notifications → página Inertia (NotificationController@index)
```

### 4.11 Segurança

- Todas as queries filtradas por `recipient_id = auth()->id()`
- Scope de PAT não substitui ownership: consultas e operações continuam limitadas às notificações do destinatário autenticado.
- Sessão SPA mantém as verificações existentes; wildcard legado e token sem scope literal compatível não autorizam operações por PAT.
- E-mail verificado é obrigatório na sessão e no PAT antes das consultas e operações privadas. Alterar o endereço invalida a verificação e bloqueia o mesmo token até confirmar o endereço atual. A autenticação de canais também exige verificação, preservando a autorização do próprio destinatário.
- `abort_unless(ownership, 403)` em toda mutation
- Campo `data` com allowlist — nunca expõe campos não listados
- `action_status` rastreia ciclo de vida — ação já executada retorna 409
- Broadcast usa `ShouldBroadcastNow` (sem dependência de queue worker)

---

## 5. Frontend

### 5.1 Tipos TypeScript

`resources/js/types/notification.ts`

```ts
type NotificationType = 'system' | 'user' | 'mention' | 'social' | 'action'
                      | 'warning' | 'error' | 'success' | 'info';

type NotificationCategory = 'general' | 'export' | 'import' | 'payment'
                           | 'comment' | 'mention' | 'task' | 'post'
                           | 'follow' | 'like' | 'message' | 'security'
                           | 'account' | 'system';

type NotificationActionStatus = null | 'pending' | 'executed' | 'rejected'
                               | 'expired' | 'cancelled';

interface Notification {
    uuid: string;
    type: NotificationType;
    category: NotificationCategory;
    title: string;
    body: string;
    link: string | null;
    actions: NotificationAction[] | null;
    action_status: NotificationActionStatus;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    sender: NotificationSender | null;
    data: Record<string, unknown> | null;
}
```

### 5.2 Service

`resources/js/services/notification-service.ts`

Usa helpers Wayfinder gerados de `NotificationApiController` + funções HTTP de `@/lib/http`.

```ts
import { notificationService } from '@/services/notification-service';

// Listar (paginado, com filtros opcionais)
const result = await notificationService.getNotifications({ type: 'warning', read: 'false' });

// Não lidas recentes (para o bell)
const { data, unread_count } = await notificationService.getUnreadNotifications();

// Contagem
const { count } = await notificationService.getUnreadCount();

// Marcar uma como lida
await notificationService.markAsRead(uuid);

// Marcar todas
await notificationService.markAllAsRead();

// Deletar
await notificationService.deleteNotification(uuid);

// Executar ação
const result = await notificationService.executeAction(uuid, 'download_export', {});
```

### 5.3 Contexto Global

`resources/js/contexts/notification-bell-context.tsx`

**Montado uma vez por árvore privada** em `PrivateStateLayout`, aplicado pelos layouts de `app.tsx` abaixo do PageContext Inertia. Gerencia:
- Fetch inicial das não lidas
- Subscription ao canal Echo (uma única instância)
- Toasts automáticos para tipos urgentes
- Contador canônico e gateway de leitura, exclusão e execução de ações para sino e página
- Revisão compartilhada que invalida listas filtradas/paginadas e descarta GETs anteriores
- Deduplicação de eventos por UUID durante o escopo e de comandos idênticos pendentes

```text
Inertia App / PageContext
└─ PrivateStateLayout (persistente)
   └─ PrivateTree (key da geração)
      └─ QueryClientProvider
         └─ ZipDownloadProvider
            └─ TooltipProvider
               └─ NotificationBellProvider
                  ├─ página e layouts internos
                  └─ Toaster
```

`PrivateStateLayout` lê `auth.user.id`, `email_verified_at` e `must_change_password` via `usePage` e fornece o escopo ao sino por `usePrivateState`. O provider não lê mais o script DOM inicial e não deve voltar para `withApp`, que fica fora do PageContext. Cada árvore cria seu QueryClient e store ZIP próprios, também em renders SSR separados. O App do Inertia permanece montado; somente a subárvore privada é recriada quando muda a identidade ou sua elegibilidade.

Visitantes e contas com verificação de e-mail ou troca obrigatória pendente não iniciam consulta, mutação ou canal de notificações. No início do logout e na mudança de identidade/eligibilidade confirmada por `beforeUpdate`, o escopo anterior é invalidado: requests são cancelados, caches e jobs ZIP são limpos, toasts são dispensados e o namespace persistido `private-state:{id}:` de saída é purgado. Preferências de aparência/sidebar permanecem. Uma falha de logout exige revalidação HTTP antes de voltar a exibir a árvore privada.

O sino sai do canal anterior ao desmontar e mantém guardas de geração e montagem em respostas, mutações, eventos Echo e ações de toast. A conclusão dos dois requisitos permite uma nova consulta das não lidas; alterar o e-mail desativa o acesso até a nova confirmação. Falhas funcionais do escopo ativo mostram erro; cancelamentos e callbacks obsoletos não geram atualização ou toast antigo. O canal só é criado quando realtime está habilitado.

O histórico do navegador não estabelece autoridade de sessão: props de outra identidade são bloqueadas antes de renderizar a subárvore e provocam revalidação HTTP, preservando a persistência própria da conta confirmada. O cliente `@/lib/http` combina o sinal do escopo com o sinal do chamador e rejeita respostas obsoletas como `ApiError` de tipo `canceled`. Isso não desfaz uma operação aceita pelo servidor nem cobre automaticamente `fetch`/Axios usados fora desse cliente. Consumidores com efeitos globais após `await` também precisam conferir seu escopo antes de notificar ou navegar.

### 5.4 Hook useNotifications

Para a **página completa** de notificações. Mantém somente lista, metadados, loading e erro da consulta filtrada; contador e mutações vêm do mesmo `NotificationBellProvider` usado pelo sino. Passa AbortSignal ao service e só publica respostas do escopo, consulta e revisão atuais. Mutações e realtime invalidam ambas as visões e reconsultam o estado do servidor; não há decremento cego nem estado executed otimista. Falhas não confirmam ações; cancelamentos e callbacks de A não aparecem em B.

`useNotifications(filters, onPageChange?)` aceita um callback estável opcional para atualizar a página externa quando a exclusão esvazia a última página. A página completa usa esse callback, controles Anterior/Próxima com limites e loading, e reinicia em 1 ao alterar tipo/leitura. Consumidores antigos sem callback continuam suportados. `refresh` invalida todas as listas montadas, preservando os filtros de cada uma.

```tsx
import { useNotifications } from '@/hooks/use-notifications';

const {
    notifications,
    pagination,
    unreadCount,
    loading,
    error,
    refresh,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    executeAction,
} = useNotifications({ type: 'warning', read: 'false', per_page: 20 });
```

### 5.5 Hook useNotificationBell

Para o **bell do header**. Lê do contexto global — não cria subscription própria.

```tsx
import { useNotificationBell } from '@/hooks/use-notifications';

const {
    unreadCount,
    recentNotifications,
    loading,
    refresh,
    markAsRead,
    markAllAsRead,
} = useNotificationBell();
```

### 5.6 Componente NotificationBell

`resources/js/components/notification-bell.tsx`

Popover com badge de não lidas, skeleton loading, lista das recentes e botão "marcar todas como lidas".

```tsx
import { NotificationBell } from '@/components/notification-bell';

// Adicionar ao header
<NotificationBell />
```

Pode ser montado em múltiplos lugares — todos compartilham o mesmo estado via contexto.

### 5.7 Componente NotificationItem

`resources/js/components/notification-item.tsx`

Item individual com:
- Ícone por tipo/categoria
- Estilo visual diferente para lida/não lida
- Botões de ação com diálogo de confirmação
- Tempo relativo formatado
- Botão de excluir (aparece no hover)

```tsx
import { NotificationItem } from '@/components/notification-item';

<NotificationItem
    notification={notification}
    onMarkAsRead={markAsRead}
    onDelete={deleteNotification}
    onExecuteAction={executeAction}
    compact={false}
/>
```

### 5.8 Página Completa

`resources/js/pages/notifications/index.tsx`

Rota: `/notifications`

Funcionalidades:
- Filtro por tipo (`NotificationType`)
- Filtro por status (todas / não lidas / lidas)
- Skeleton de carregamento
- Estado vazio com ícone
- Botão "Marcar todas como lidas"
- Paginação com navegação
- Contagem de resultados

---

## 6. Realtime com Reverb + Echo

### Setup

```bash
composer require laravel/reverb
php artisan reverb:install
npm install laravel-echo pusher-js
```

`.env`:
```
BROADCAST_CONNECTION=reverb
VITE_REVERB_ENABLED=true
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Evento

`app/Events/NotificationCreated.php` implementa `ShouldBroadcastNow`:
- Canal: `private-user.{recipientId}.notifications`
- Evento: `.notification.created`
- Payload: shape completo da notificação (espelha `NotificationResource`)

> Usa `ShouldBroadcastNow` (não `ShouldBroadcast`) para enviar imediatamente sem queue worker.

### Autorização do canal

`routes/channels.php`:
```php
Broadcast::channel('user.{userId}.notifications', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

### Singleton Echo

`resources/js/lib/echo.ts` mantém uma instância compartilhada, criada somente se `VITE_REVERB_ENABLED=true` e `typeof window !== 'undefined'`. No Node/SSR ou com realtime desligado, o módulo exporta `null` e não abre conexão. Os hooks existentes tratam essa ausência; não inicialize outro Echo em páginas ou componentes.

SSR e realtime podem ser ligados de forma independente. `INERTIA_SSR_ENABLED` e `INERTIA_SSR_URL` controlam o PHP; produção requer bundle e processo SSR, e alterar `VITE_REVERB_*` exige recompilar o cliente. Operação e perfil nativo dos quatro modos: [SSR e realtime opcionais](../../stabilization/ops-optional-runtime.md).

### Subscription (no contexto global)

```ts
// Trecho do listener dentro do efeito do NotificationBellProvider.
channel.listen('.notification.created', (event: { notification: Notification }) => {
    if (!active || !isCurrentScope(scope) || eventsRef.current.has(event.notification.uuid)) {
        return;
    }

    eventsRef.current.add(event.notification.uuid);
    invalidate(scope);
    void refreshBell();
    showRealtimeToast(event.notification, () => isCurrentScope(scope));
});
```

O efeito completo na fonte cria o canal privado e, no cleanup, desativa o listener e sai do canal. O Set de UUIDs dura todo o escopo, inclusive além dos dez itens recentes; o contador vem da reconsulta canônica, sem incremento por evento.

### Iniciar o servidor WebSocket

```bash
php artisan reverb:start
```

### Tolerância a falhas

`CreateNotificationAction` captura `BroadcastException`:
```php
try {
    broadcast(new NotificationCreated($notification))->toOthers();
} catch (BroadcastException) {
    // Reverb indisponível — notificação persiste, broadcast pulado
    report(...);
}
```

A notificação **sempre** é salva no banco, independente do Reverb estar rodando.

---

## 7. Toasts Automáticos

Tipos que disparam toast quando chegam via realtime:

| Tipo | Toast |
|------|-------|
| `warning` | `toast.warning(title, { description: body })` |
| `error` | `toast.error(...)` |
| `system` | `toast.message(...)` |
| outros | Sem toast — apenas bell |

Se a notificação tem `link`, um botão "Ver" aparece no toast.

Para adicionar mais tipos:
```ts
// notification-bell-context.tsx
const TOAST_TYPES = new Set<Notification['type']>(['warning', 'error', 'system']);
// Adicionar: 'action', 'mention', etc.
```

---

### Exportações concluídas — NTF-001

`ExportReadyNotification` usa somente o adaptador `ExportReadyChannel`, que valida o export persistido e seu proprietário e chama o `CreateNotificationAction` existente. O resultado é um aviso `success`/`export` em `app_notifications`, com link privado de download e dados de exibição permitidos; sino, página e evento privado reutilizam o fluxo deste módulo. Não grava um aviso paralelo em `notifications` nem ativa e-mail. Veja o [contrato de exportações](../Exports/async-export-system.md#46-notificação).

Realtime desligado exige atualização/abertura do sino para buscar o aviso persistido. Broadcast indisponível conserva o registro; falha de persistência depois da conclusão do arquivo, concorrência e queda continuam sob OPS-001. O link não concede acesso: a rota de download mantém as verificações atuais de proprietário/estado/expiração/arquivo.

## 8. Como Criar uma Notificação no Backend

```php
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;

// Injetar via DI ou resolver via app()
public function __construct(private CreateNotificationAction $notifyAction) {}

// Notificação simples de sistema
$this->notifyAction->handle(
    CreateNotificationDTO::system(
        recipientId: $user->id,
        title: 'Importação concluída',
        body: 'Seus dados foram importados com sucesso.',
        category: NotificationCategory::Import,
    )
);

// Com dados auxiliares
$this->notifyAction->handle(
    CreateNotificationDTO::system(
        recipientId: $user->id,
        title: 'Relatório pronto',
        body: 'Seu relatório de usuários está disponível.',
        category: NotificationCategory::Export,
        data: ['export_uuid' => $export->uuid, 'file_name' => 'users.xlsx'],
        link: route('exports.index'),
    )
);
```

### Notificações de menção automática

```php
use App\Modules\Notifications\Services\NotificationService;

app(NotificationService::class)->notifyMentions(
    text: $comment->body,
    author: auth()->user(),
    title: auth()->user()->name . ' mencionou você em um comentário.',
    body: $comment->body,
    link: route('comments.show', $comment),
);
```

---

## 9. Como Adicionar uma Nova Ação Executável

Implemente o contrato existente com uma operação real. Este exemplo usa a própria notificação como entidade e reutiliza o fluxo de leitura; ele só é ativado após registro explícito no projeto:

```php
use App\Models\User;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Models\AppNotification;

class ConfirmReadNotificationAction implements NotificationExecutableAction
{
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        abort_unless($notification->recipient_id === $user->id, 403);
        if (! $notification->isRead() && ! $notification->markAsRead()) {
            abort(500, 'Não foi possível confirmar a leitura.');
        }

        return ['confirmed' => $notification->refresh()->isRead()];
    }
}
```

Para um recurso de negócio diferente, carregue sua entidade e autorize a operação com a policy correspondente antes de escrever. Preserve a mesma conexão para as alterações transacionais e implemente a proteção de transição do domínio. Nunca anuncie sucesso com a regra de negócio comentada.

Registre a classe no `$map` do resolver ou pelo ponto de extensão existente:

```php
app(NotificationActionResolver::class)->register('confirm_read', ConfirmReadNotificationAction::class);
```

O registro precisa ocorrer de forma consistente no boot do servidor que atende às solicitações. Só então crie a notificação com o botão `confirm_read`, usando `CreateNotificationDTO::withActions`. Não habilite `approve_request`/`reject_request` sem seus handlers reais. Cubra autorização, entidade ausente, rollback, repetição e concorrência antes de disponibilizar uma ação que altera negócio.

---

## 10. Como Adicionar ao Header

O `<NotificationBell />` já está integrado em:
- `resources/js/components/app-sidebar-header.tsx`
- `resources/js/components/dashboard/dashboard-header.tsx`

Para adicionar a outro layout:

```tsx
import { NotificationBell } from '@/components/notification-bell';

// Dentro do header
<div className="ml-auto flex items-center gap-2">
    <NotificationBell />
    {/* outros itens */}
</div>
```

O `NotificationBellProvider` precisa estar na árvore privada de `PrivateStateLayout`, abaixo do PageContext Inertia (configurado pelos layouts de `app.tsx`).

---

## 11. Checklist para Novo Projeto

### Backend

- [ ] Copiar pasta `app/Modules/Notifications/` inteira
- [ ] Copiar `app/Events/NotificationCreated.php`
- [ ] Copiar `app/Http/Controllers/NotificationController.php`
- [ ] Copiar `app/Http/Controllers/Api/NotificationApiController.php`
- [ ] Copiar `app/Http/Requests/ExecuteNotificationActionRequest.php`
- [ ] Copiar `app/Http/Resources/NotificationResource.php`
- [ ] Copiar `app/Supports/Traits/HasNotifications.php`
- [ ] Registrar `NotificationsServiceProvider` em `bootstrap/providers.php`
- [ ] Adicionar trait `HasNotifications` no model `User`
- [ ] Adicionar rotas API em `routes/api.php` (dentro de `auth:sanctum`)
- [ ] Adicionar rota web em `routes/web.php`
- [ ] Adicionar canal em `routes/channels.php`
- [ ] Rodar `php artisan migrate`
- [ ] Rodar `php artisan wayfinder:generate`

### Adaptar se necessário

- [ ] Ajustar `NotificationResource::resolvePublicData()` — allowlist do campo `data`
- [ ] Adicionar Actions Executáveis específicas do projeto
- [ ] Registrar novas ações no `NotificationActionResolver::$map`
- [ ] Verificar que o model `User` usa `HasUuidIdentifier` (ou adaptar o route binding)

### Frontend

- [ ] Copiar `resources/js/types/notification.ts`
- [ ] Copiar `resources/js/services/notification-service.ts`
- [ ] Copiar `resources/js/hooks/use-notifications.ts`
- [ ] Copiar `resources/js/contexts/notification-bell-context.tsx`
- [ ] Copiar `resources/js/components/notification-bell.tsx`
- [ ] Copiar `resources/js/components/notification-item.tsx`
- [ ] Copiar `resources/js/pages/notifications/index.tsx`
- [ ] Copiar `resources/js/lib/echo.ts`
- [ ] Preservar `<NotificationBellProvider>` em `PrivateStateLayout`, abaixo do PageContext Inertia
- [ ] Adicionar `<NotificationBell />` ao header
- [ ] Instalar dependências: `npm install laravel-echo pusher-js`
- [ ] Ajustar importações Wayfinder (ou trocar por URLs hardcoded se Wayfinder não estiver disponível)

### Reverb (se realtime for necessário)

- [ ] `composer require laravel/reverb`
- [ ] `php artisan reverb:install`
- [ ] Configurar variáveis `REVERB_*` no `.env`
- [ ] Configurar variáveis `VITE_REVERB_*` no `.env`
- [ ] Iniciar: `php artisan reverb:start`

### Adaptar Wayfinder

Se o projeto novo **não usar Wayfinder**, substituir no `notification-service.ts`:

```ts
// Com Wayfinder (atual)
import * as NotificationApi from '@/actions/App/Http/Controllers/Api/NotificationApiController';
const url = NotificationApi.index.url();

// Sem Wayfinder — substituir por:
const url = '/api/v1/notifications';
```

---

## 12. Referência da API

As respostas abaixo pressupõem sessão SPA autorizada ou, nas seis operações compatíveis com PAT, o scope indicado na seção 4.10. PAT sem scope compatível recebe `403`; `execute-action` recebe `403` para qualquer PAT.

### GET /api/v1/notifications

Query params opcionais: `type`, `category`, `read` (true/false), `per_page`, `page`

```json
{
  "data": [ { "uuid": "...", "title": "...", ... } ],
  "meta": { "current_page": 1, "last_page": 3, "total": 47 }
}
```

### GET /api/v1/notifications/unread

Retorna as não lidas recentes (padrão: 10).

```json
{
  "data": [ ... ],
  "unread_count": 12
}
```

### GET /api/v1/notifications/unread-count

```json
{ "count": 12 }
```

### PATCH /api/v1/notifications/{uuid}

Marca como lida. Retorna `NotificationResource` atualizado.

### POST /api/v1/notifications/mark-all-read

```json
{ "marked": 12 }
```

### DELETE /api/v1/notifications/{uuid}

`204 No Content`

### POST /api/v1/notifications/{uuid}/execute-action

Body:
```json
{ "action_type": "download_export", "payload": {} }
```

`payload` omitido ou nulo é normalizado para `{}`. O exemplo exige uma notificação de exportação do próprio usuário. `approve_request` e `reject_request` são indisponíveis por padrão (422).

Respostas de erro:
- `403` — notificação não pertence ao usuário ou o handler recusa a autorização sobre a entidade
- `404` — entidade vinculada inexistente
- `409` — ação já executada (`action_status` não é pending)
- `422` — notificação sem ações, tipo inválido ou handler indisponível

---

## 13. Armadilhas Conhecidas

### 401 após troca de senha

**Causa**: `Sanctum\Http\Middleware\AuthenticateSession` armazena `password_hash_web` na session. Quando a senha muda no banco, o hash da session fica desatualizado. Nas próximas chamadas API, o hash não bate → 401.

**Fix** (já aplicado em `ForcePasswordChangeController` e `SecurityController`):
```php
$user->update(['password' => $request->password]);

$guard = auth()->guard('web');
$request->session()->put(
    'password_hash_web',
    $guard instanceof SessionGuard && method_exists($guard, 'hashPasswordForCookie')
        ? $guard->hashPasswordForCookie($user->getAuthPassword())
        : $user->getAuthPassword()
);
```

### Toast duplicado

**Causa**: múltiplos `<NotificationBell />` montados = múltiplos listeners Echo.

**Fix**: `NotificationBellProvider` montado **uma vez por árvore privada** em `PrivateStateLayout`. Todos os componentes consomem via Context — nunca criam subscription própria.

### `usePage` fora do contexto Inertia

**Causa**: `NotificationBellProvider` estava em `withApp` (acima do componente Inertia).

**Fix**: `PrivateStateLayout` é um layout abaixo do PageContext e lê a identidade com `usePage`; o sino consome `usePrivateState` dentro dessa árvore. Não recolocar o provider em `withApp` nem usar o script DOM inicial como autoridade de uma navegação SPA. O escopo do sino e o descarte de callbacks antigos estão descritos na seção 5.3.

### BroadcastException derrubando o request

**Causa**: `broadcast()` lança exceção se o Reverb não estiver rodando.

**Fix**: try/catch em `CreateNotificationAction`. A notificação é sempre salva no banco.

### Wayfinder desatualizado após nova rota

Rodar após qualquer alteração nas rotas:
```bash
php artisan wayfinder:generate
```


### Regressões UI-001 (Windows local)

A fixture `tests/Fixtures/Frontend/notification-state-browser.html`, servida com `tests/Fixtures/Frontend/notification-state.vite.ts` numa cópia descartável e cache privado (`UI001_VITE_CACHE_DIR`), monta React/Inertia, página, sino e hooks reais. O botão Executar regressões UI-001 cobre 12 contratos, substituindo somente o service HTTP, Echo e o evento beforeUpdate da resposta sintética. O perfil separado `AUTHORITY_HTTP_NOTIFICATION_UI_TESTS=1` do harness de autoridade valida Laravel/MySQL/CSRF reais, duas contas e F5 em HTTP local. O realtime nessa prova usa alias de teste e evento publicado por stdin; não comprova WebSocket/Reverb nem SSR de produção. Evidência e limites: [UI-001](../../stabilization/tasks/UI-001.md).
