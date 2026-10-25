> **Especificação histórica de requisitos.** O pedido original abaixo não descreve capacidades automaticamente ativas. Consulte o [contrato implementado de Notifications](../.start_kit.obs/Notifications/notifications-module.md) e a [autoridade PREP-002](../stabilization/tasks/PREP-002.md#contrato-adotado). Mantenha os contratos atuais na documentação do módulo; este arquivo preserva a origem dos requisitos.

> Exportação pronta (NTF-001): o canal ativo grava no centro AppNotification existente, com evento privado e link autenticado de download. E-mail não está ativo. A integração não requer tabela Laravel notifications paralela; contrato vigente na [documentação de exports](../.start_kit.obs/Exports/async-export-system.md#46-notificação).

> Estado das ações executáveis em NTF-002: aprovação/recusa são exemplos indisponíveis até integração explícita de handler real autorizado. O executor serializa a mesma notificação e confirma efeitos DB da mesma conexão junto do estado terminal; não cria garantia universal de efeitos externos. O contrato vigente e os exemplos implementáveis estão na [documentação do módulo](../.start_kit.obs/Notifications/notifications-module.md#47-ações-executáveis). Os requisitos originais abaixo não significam que exemplos demonstrativos estejam ativos.


Preciso que você implemente um módulo completo, profissional e reutilizável de notificações 

A estrutura precisa ser bem componentizada, escalável, segura e fácil de reaproveitar em vários projetos futuros.



## 2. Objetivo principal

Criar um módulo de notificações que suporte:

- Notificações de sistema
- Notificações entre usuários
- Notificações com links de acesso
- Notificações com botões de ação
- Notificações de menções usando `@usuario`
- Notificações estilo rede social
- Notificações lidas e não lidas
- Dropdown de notificações no header
- Página completa de notificações
- Componentes React reutilizáveis
- Backend desacoplado
- Estrutura preparada para realtime no futuro
- Fácil adaptação para outros projetos

---

## 3. Exemplos de notificações que o módulo deve suportar

### 3.1 Notificações de sistema

Exemplos:

- Seu relatório foi exportado com sucesso.
- Sua importação terminou.
- Seu pagamento foi aprovado.
- O arquivo solicitado está pronto para download.
- Sua assinatura foi renovada.
- Houve uma falha no processamento do arquivo.

### 3.2 Notificações entre usuários

Exemplos:

- João comentou em sua requisição.
- Maria respondeu sua análise.
- Carlos atribuiu uma tarefa para você.
- Ana enviou uma solicitação para você.

### 3.3 Notificações de menção

Exemplos:

- Pedro mencionou você em um comentário.
- Você foi mencionado na requisição #123.
- Maria mencionou você em uma observação.

### 3.4 Notificações estilo rede social

Exemplos:

- Ana começou a seguir você.
- Lucas curtiu sua publicação.
- Marina comentou no seu post.
- João respondeu seu comentário.

### 3.5 Notificações com ações

Exemplos:

- Aprovar solicitação
- Recusar solicitação
- Baixar arquivo
- Responder comentário
- Ver detalhes
- Revisar pendência
- Confirmar leitura

---

## 4. Diretriz arquitetural

O módulo deve ser tratado como um domínio próprio da aplicação, não apenas como uma tabela de notificações.

A estrutura precisa conter:

- Models
- Migrations
- Enums
- DTOs
- Services
- Actions
- Controllers
- Requests
- Resources

- Support classes
- Componentes React
- Hooks React
- Services frontend
- Types TypeScript
- Página Inertia

A arquitetura precisa permitir que eu copie o módulo para outro projeto com o mínimo de alterações possível.





### 7.2 Índices obrigatórios

Adicionar índices para:

```txt
uuid
recipient_id
sender_id
read_at
created_at
type
category
notifiable_type + notifiable_id
recipient_id + read_at
recipient_id + created_at
recipient_id + read_at + created_at
```

A listagem deve ser performática mesmo com grande volume de notificações.

---
## 8. Model Notification

Criar o model:

```php
app/Modules/Notifications/Models/Notification.php
```

O model deve conter:

- `$fillable`
- `$casts`
- relacionamento `recipient()`
- relacionamento `sender()`
- relacionamento `notifiable()`
- scope `unread()`
- scope `read()`
- scope `forRecipient()`
- scope `ofType()`
- scope `ofCategory()`
- método `markAsRead()`
- método `markAsClicked()`
- método `isRead()`
- método `isUnread()`
- uso de UUID no create

---

## 9. Enums

Criar enums para evitar strings soltas no projeto.

### 9.1 NotificationType

Criar:

```php
app/Modules/Notifications/Enums/NotificationType.php
```

Valores iniciais:

```php
SYSTEM
USER
MENTION
SOCIAL
ACTION
WARNING
ERROR
SUCCESS
INFO
```

### 9.2 NotificationCategory

Criar:

```php
app/Modules/Notifications/Enums/NotificationCategory.php
```

Valores iniciais:

```php
GENERAL
EXPORT
IMPORT
PAYMENT
COMMENT
MENTION
TASK
POST
FOLLOW
LIKE
MESSAGE
SECURITY
ACCOUNT
SYSTEM
```

### 9.3 NotificationActionType

Criar:

```php
app/Modules/Notifications/Enums/NotificationActionType.php
```

Valores iniciais:

```php
VIEW
APPROVE
REJECT
DOWNLOAD
REPLY
OPEN
CONFIRM
CUSTOM
```

### 9.4 NotificationActionVariant

Criar:

```php
app/Modules/Notifications/Enums/NotificationActionVariant.php
```

Valores iniciais:

```php
PRIMARY
SECONDARY
DESTRUCTIVE
GHOST
OUTLINE
SUCCESS
WARNING
```

---

## 10. DTOs

Criar DTOs para padronizar criação de notificações.





## 12. Actions backend

Criar actions separadas para cada responsabilidade.

---

## 13. Menções no sistema

Criar suporte a menções usando `@usuario`.

### 13.1 MentionService

Criar:

```php
app/Modules/Notifications/Services/MentionService.php
```

Responsabilidades:

- Extrair menções de um texto
- Identificar usuários mencionados
- Remover duplicidades
- Evitar notificar o próprio autor
- Retornar uma collection de usuários mencionados

Exemplo de uso:

```php
$mentionedUsers = app(MentionService::class)->usersFromText(
    text: $comment->body,
    author: auth()->user()
);
```


A action deve criar uma notificação para cada usuário mencionado.

A mensagem deve ser parecida com:

```txt
João mencionou você em um comentário.
```

---

## 14. Notificações com ações executáveis

Algumas notificações devem ter botões que executam ações reais no backend.

Exemplo de estrutura de action na notificação:

```json
[
  {
    "label": "Aprovar",
    "type": "approve_request",
    "variant": "primary",
    "requires_confirmation": true,
    "confirmation_message": "Tem certeza que deseja aprovar esta solicitação?"
  },
  {
    "label": "Recusar",
    "type": "reject_request",
    "variant": "destructive",
    "requires_confirmation": true,
    "confirmation_message": "Tem certeza que deseja recusar esta solicitação?"
  }
]
```

O frontend nunca deve executar regras sensíveis por conta própria.

Fluxo correto:

1. Usuário clica em uma ação da notificação
2. Frontend envia `notification_id` e `action_type`
3. Backend valida se a notificação pertence ao usuário
4. Backend valida se a ação existe na notificação
5. Backend valida policies/permissões
6. Backend resolve uma classe responsável pela ação
7. Backend executa a ação
8. Backend retorna resposta padronizada
9. Frontend atualiza a lista

---

## 15. Estrutura para ações executáveis

Criar contrato:

```php
app/Modules/Notifications/Actions/Executable/Contracts/NotificationExecutableAction.php
```

Interface esperada:

```php
interface NotificationExecutableAction
{
    public function execute(Notification $notification, User $user, array $payload = []): mixed;
}
```

Criar resolver:

```php
app/Modules/Notifications/Services/NotificationActionResolver.php
```

O resolver deve mapear `action_type` para uma classe executável.

Exemplo:

```php
'approve_request' => ApproveRequestNotificationAction::class,
'reject_request' => RejectRequestNotificationAction::class,
'download_export' => DownloadExportNotificationAction::class,
```

Criar exemplos de actions:

```txt
ApproveRequestNotificationAction.php
RejectRequestNotificationAction.php
DownloadExportNotificationAction.php
```

Essas actions podem ser exemplos genéricos, mas precisam mostrar claramente como validar permissão e executar regra.

---

## 16. Controller backend

Criar:

```php
app/Modules/Notifications/Http/Controllers/NotificationController.php
```

Métodos esperados:

```php
index()
unread()
unreadCount()
markAsRead(Notification $notification)
markAllAsRead()
destroy(Notification $notification)
executeAction(ExecuteNotificationActionRequest $request, Notification $notification)
```

---

## 17. Requests

Criar:

```php
app/Modules/Notifications/Http/Requests/ExecuteNotificationActionRequest.php
```

Validação:

```php
action_type required string
payload nullable array
```

---

## 18. Resource

Criar:

```php
app/Modules/Notifications/Http/Resources/NotificationResource.php
```



## 19. Rotas backend

Criar rotas agrupadas com middleware de autenticação.


Se o projeto usar Wayfinder, também preparar o código para funcionar bem com os helpers de rota.

---

## 20. Segurança backend

Aplicar as seguintes regras:

- Usuário só pode ver as próprias notificações
- Usuário só pode marcar como lida uma notificação dele
- Usuário só pode excluir uma notificação dele
- Usuário só pode executar ações de notificações dele
- Backend nunca deve confiar apenas no payload enviado pelo frontend
- Validar se a ação solicitada existe dentro da notificação
- Validar se a entidade relacionada ainda existe
- Validar policy da entidade relacionada quando necessário
- Não expor dados sensíveis no campo `data`
- Não salvar URLs externas sem validação
- Usar policies ou gates quando fizer sentido
- Aplicar rate limit se necessário

---

## 21. Performance backend

Implementar pensando em performance:

- Paginar notificações
- Eager loading de sender e notifiable quando necessário
- Contagem eficiente de não lidas
- Índices corretos na tabela
- Evitar N+1
- Não carregar payloads pesados desnecessariamente
- Permitir filtros por tipo, categoria e status de leitura

---

## 22. Frontend React — Tipos TypeScript



## 23. Frontend React — Service

Criar:


O service precisa ter funções:

```ts
getNotifications(params?: NotificationFilters)
getUnreadNotifications()
getUnreadCount()
markAsRead(notificationId: number)
markAllAsRead()
deleteNotification(notificationId: number)
executeAction(notificationId: number, actionType: string, payload?: Record<string, unknown>)
```



## 26. Página de notificações

Criar página Inertia:

A página deve conter:

- Título “Notificações”
- Lista paginada
- Filtros
- Botão “Marcar todas como lidas”
- Estado vazio
- Skeleton/loading
- Tratamento de erro
- Layout limpo e responsivo

---

## 27. Integração no Header

---

## 28. Design esperado

Usar uma UI limpa, moderna e reutilizável.

Diretrizes:

- Layout minimalista
- Bom espaçamento
- Estados visuais claros
- Diferenciar notificação lida e não lida
- Usar ícones quando fizer sentido
- Suportar modo claro/escuro, se o projeto já suportar
- Não acoplar as cores a um projeto específico
- Usar Tailwind CSS
- Evitar componentes visualmente pesados

---

## 29. Realtime futuro

Não precisa implementar realtime agora, mas deixar preparado para:

- Laravel Broadcasting
- Laravel Reverb
- Laravel Echo
- Pusher
- WebSocket

Explique onde entraria:

- Event `NotificationCreated`
- Broadcast para canal privado do usuário
- Atualização automática do badge
- Inserção da nova notificação no dropdown sem reload

Exemplo futuro:

```php
broadcast(new NotificationCreated($notification))->toOthers();
```

Canal esperado:

```txt
private-user.{userId}.notifications
```

---


O módulo precisa ser fácil de reaproveitar.

Para isso:

- Evite nomes específicos do projeto atual
- Use entidades genéricas nos exemplos
- Deixe claro onde adaptar nomes de model
- Centralize action resolvers
- Centralize enums
- Centralize componentes React
- Não misture regra de negócio específica dentro do controller
- Não deixe o frontend decidir regras sensíveis
- Não dependa de uma tabela específica além de users e notifications

---

## 35. Entregáveis obrigatórios

Entregue todos os arquivos completos, com caminho e código.

Para cada arquivo, use este formato:

`
Não entregue apenas teoria.

Quero implementação prática.

---

## 36. Cuidados importantes

Antes de escrever código, verifique:

- Se o projeto usa Inertia com React
- Se o projeto usa  client HTTP
- Se há alias `@/`
- Se o projeto usa Wayfinder
- Se o projeto usa TanStack Query
- Se o layout já possui Header
- Se existe padrão de Button, Card, Dropdown ou Badge no projeto

Se já existirem componentes base no projeto, reutilize-os.

Se não existirem, crie componentes simples com Tailwind.

---

## 37. Resultado esperado

Ao final, eu quero conseguir:

1. Criar notificações facilmente no backend
2. Exibir notificações no header
3. Exibir badge de não lidas
4. Abrir dropdown com notificações recentes
5. Acessar página completa de notificações
6. Marcar uma notificação como lida
7. Marcar todas como lidas
8. Excluir notificação
9. Executar ações seguras pelo backend
10. Criar notificações de menção automaticamente
11. Adicionar novos tipos de notificação sem refatorar o módulo inteiro
12. Copiar esse módulo para outros projetos Laravel + React/Inertia

---

## 38. Observação final

Priorize clareza, organização e manutenção.

O módulo precisa parecer uma base profissional de produto SaaS, não apenas uma implementação simples de CRUD.


Se houver mais de uma abordagem possível, escolha a mais robusta e explique rapidamente o motivo antes de entregar os arquivos.

pode me fazer perguntas se tiver duvidas, opniões e feedbacks são bem vindas, não precisa concorodar tudo comigo mas me pergunte e resolvemos juntos 
