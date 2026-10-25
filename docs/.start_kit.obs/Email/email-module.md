# Módulo de Email

Documentação completa do módulo de envio de email assíncrono. Cobre arquitetura, kill switch runtime, idempotência, retry automático, webhooks de entrega, templates Blade e checklist para adicionar novos templates ou integrar em novas features.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Fluxo Completo](#2-fluxo-completo)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Backend](#4-backend)
   - [Configuração](#41-configuração)
   - [AppSettings — kill switch](#42-appsettings--kill-switch)
   - [Enums](#43-enums)
   - [SendEmailDTO](#44-sendemaildto)
   - [EmailLog Model](#45-emaillog-model)
   - [EmailService](#46-emailservice)
   - [ProcessEmailJob](#47-processemailjob)
   - [DynamicMailable](#48-dynamicmailable)
   - [Webhooks de Entrega](#49-webhooks-de-entrega)
   - [Actions](#410-actions)
   - [Controller — Logs UI](#411-controller--logs-ui)
   - [Rotas](#412-rotas)
5. [Frontend](#5-frontend)
   - [Tipos TypeScript](#51-tipos-typescript)
   - [Página email-logs](#52-página-email-logs)
   - [Kill switch panel](#53-kill-switch-panel)
6. [Templates Blade](#6-templates-blade)
7. [Como Integrar em uma Nova Feature](#7-como-integrar-em-uma-nova-feature)
8. [Como Criar um Novo Template](#8-como-criar-um-novo-template)
9. [Como Adicionar um Novo Provider de Webhook](#9-como-adicionar-um-novo-provider-de-webhook)
10. [Variáveis de Ambiente](#10-variáveis-de-ambiente)
11. [Referência da API](#11-referência-da-api)
12. [Testes](#12-testes)
13. [Armadilhas Conhecidas](#13-armadilhas-conhecidas)
14. [Dev Tools](#14-dev-tools)

---

## 1. Visão Geral

### Problema resolvido

Envio de email precisa ser assíncrono (não bloqueia o request), rastreável, resiliente a falhas e controlável sem deploy. O módulo resolve isso com:

- Job assíncrono com retry automático (backoff exponencial)
- Log completo em `email_logs` (quem recebeu, quando, status, erros)
- Kill switch em runtime por módulo via banco + cache
- Idempotência local com chave única, geração e claim atômico; entrega externa exige reconciliação quando incerta
- Agnóstico de provider — troca de SMTP/Resend/SES apenas no `.env`

### Capacidades

| Recurso | Detalhe |
|---------|---------|
| Envio | Queue assíncrono via `ProcessEmailJob` |
| Retry | Até 3 tentativas pré-transporte com backoff 30s → 2min → 5min; resultado incerto exige reconciliação |
| Kill switch | Global + por categoria, runtime, sem deploy, TTL cache 60s |
| Idempotência | Chave SHA-256 + geração de despacho + claim atômico; sem promessa de entrega externa exatamente uma vez |
| Providers | Qualquer driver Laravel (SMTP, SES, Resend, Postmark, Mailgun) |
| Templates | Blade com variáveis injetadas, layout base responsivo |
| Webhooks | Strategy por provider — atualiza status para `delivered`/`bounced` |
| Staging | `EMAIL_TO_OVERRIDE` redireciona todos os envios para um único endereço |
| Alertas | Falha segura notifica somente operadores autorizados pelo Gate de logs, com conteúdo minimizado |
| UI | `/email-logs` — logs paginados, filtros, reenvio, painel de configurações |
| Dev Tools | `/admin/dev/components/email` — playground + preview + documentação |

---

## 2. Fluxo Completo

```
Caller (Controller / Job / Notification)
        │
        ▼
EmailServiceInterface::queue(SendEmailDTO)
        │
        ├── Kill switch? (AppSettings global + módulo) → null se bloqueado
        ├── EMAIL_TO_OVERRIDE? → sobrescreve destinatário
        ├── Idempotência → busca EmailLog com mesma chave
        │       ├── Existe e não é failed seguro → null (duplicata/incerto)
        │       ├── Existe failed seguro, sem aceitação → CAS queued + nova geração
        │       └── Não existe → cria novo EmailLog
        │
        ▼
Publicação após commit                     ← status: queued + dispatch_token
        │
        ▼  (queue worker)
ProcessEmailJob::handle()
        │
        ├── CAS queued + token → processing, attempts++ atômico
        ├── DynamicMailable::render() antes do transporte
        ├── Agenda UTC ainda futura? → liberar sem claim/envio
        ├── Mail::mailer($log->mailer)->to(...)->send(mailable)
        │
        ├── Sucesso → EmailLog::markAsSent()    → status: sent
        └── Falha pré-transporte → queued + throw (retry limitado)
                Falha após entrar no mailer → processing (reconciliação, sem novo envio)
                        │
                        └── Após 3 tentativas → ProcessEmailJob::failed()
                                ├── CAS queued + token → failed seguro
                                └── Notifica operadores pelo Gate, sem dados do e-mail/exceção
```

**Webhooks (assíncrono, pós-entrega):**

```
POST /api/email/webhook/{provider}
        │
        ▼
EmailWebhookController → EmailWebhookService::handle($provider, $request)
        │
        ├── Resolve handler: 'resend' → ResendWebhookHandler
        ├── verifySignature($request) → 401 se falhar
        └── handle($request)
                ├── Localiza EmailLog por message_id
                ├── email.delivered → markAsDelivered()   → status: delivered
                └── email.bounced   → markAsBounced()     → status: bounced
```

---

## 3. Estrutura de Arquivos

```
app/
├── Modules/
│   └── Email/
│       ├── Actions/
│       │   ├── SendEmailAction.php         ← wrapper para compatibilidade
│       │   └── ResendEmailAction.php       ← reenfileira emails com status failed
│       ├── Contracts/
│       │   ├── EmailServiceInterface.php   ← queue() + sendNow()
│       │   └── WebhookHandlerInterface.php ← verifySignature() + handle()
│       ├── DTOs/
│       │   └── SendEmailDTO.php            ← readonly, withTo() para override
│       ├── Enums/
│       │   ├── EmailStatus.php             ← queued → processing → sent/failed/bounced/delivered
│       │   └── EmailCategory.php          ← auth, export, notification, system
│       ├── Exceptions/
│       │   ├── EmailSendException.php
│       │   └── TemplateNotFoundException.php
│       ├── Jobs/
│       │   └── ProcessEmailJob.php         ← tries=3, backoff=[30,120,300]
│       ├── Mail/
│       │   └── DynamicMailable.php         ← $emailTemplate e $replyToAddress (não $template/$replyTo)
│       ├── Models/
│       │   └── EmailLog.php
│       ├── Services/
│       │   ├── EmailService.php            ← implementa EmailServiceInterface
│       │   └── EmailWebhookService.php     ← registry de providers + routing
│       ├── Webhooks/
│       │   ├── ResendWebhookHandler.php    ← SVIX signature
│       │   ├── SesWebhookHandler.php       ← SNS + auto-subscription
│       │   └── PostmarkWebhookHandler.php  ← X-Postmark-Webhook-Token
│       ├── Http/Controllers/
│       │   ├── EmailLogController.php      ← index, resend, updateSettings
│       │   └── EmailWebhookController.php  ← POST /api/email/webhook/{provider}
│       └── Providers/
│           └── EmailServiceProvider.php    ← bind EmailServiceInterface → EmailService (singleton)
│
├── Support/
│   └── AppSettings.php                     ← key-value DB + cache 60s (reutilizável)
│
config/
└── email.php                               ← defaults + EMAIL_TO_OVERRIDE

database/
├── migrations/
│   ├── ..._create_app_settings_table.php
│   └── ..._create_email_logs_table.php
└── factories/
    └── EmailLogFactory.php

resources/
├── views/emails/
│   ├── layouts/base.blade.php              ← HTML responsivo inline CSS, max-width 600px
│   └── exports/
│       └── ready.blade.php                 ← variáveis: $userName, $downloadUrl, $expiresAt
└── js/
    ├── types/app/email-log.ts
    └── pages/app/email-logs/index.tsx

routes/
├── web.php   ← /email-logs (auth)
└── api.php   ← /api/email/webhook/{provider} (público)
```

---

## 4. Backend

### 4.1 Configuração

**`config/email.php`**

```php
return [
    'defaults' => [
        'enabled'              => env('EMAIL_ENABLED', true),
        'auth_enabled'         => env('EMAIL_AUTH_ENABLED', true),
        'export_enabled'       => env('EMAIL_EXPORT_ENABLED', true),
        'notification_enabled' => env('EMAIL_NOTIFICATION_ENABLED', true),
        'system_enabled'       => env('EMAIL_SYSTEM_ENABLED', true),
    ],
    'to_override' => env('EMAIL_TO_OVERRIDE'),
];
```

Os `defaults` são usados apenas quando **nenhum valor está salvo no DB** ainda. Uma vez que o admin salva pelo painel, o DB tem precedência.

---

### 4.2 AppSettings — kill switch

Classe estática reutilizável. Grupo `email` é usado pelo módulo de email mas pode ser usado por qualquer outro módulo com outro grupo.

```php
use App\Support\AppSettings;

// Leitura (cache 60s TTL)
AppSettings::get('email', 'enabled', true);
AppSettings::get('email', 'export_enabled', true);

// Escrita (upsert no DB + invalida cache)
AppSettings::set('email', 'enabled', false);        // bloqueia TUDO
AppSettings::set('email', 'export_enabled', false); // bloqueia só Export

// Todos os valores de um grupo (sem cache, direto do DB)
AppSettings::all('email');
// → ['enabled' => true, 'export_enabled' => false, ...]

// Remover
AppSettings::forget('email', 'export_enabled');
```

**Tabela `app_settings`:**

| Coluna | Tipo | Detalhe |
|--------|------|---------|
| `id` | bigint | PK |
| `group` | string | Ex: `'email'` |
| `key` | string | Ex: `'export_enabled'` |
| `value` | json | Qualquer tipo JSON-serializável |
| UNIQUE | `(group, key)` | Garante upsert seguro |

**Cache key format:** `app_settings.{group}.{key}`

---

### 4.3 Enums

**`EmailStatus`** — ciclo de vida de um email:

| Valor | Label | Cor |
|-------|-------|-----|
| `queued` | Na fila | amber |
| `processing` | Processando | blue |
| `sent` | Enviado | green |
| `failed` | Falhou | red |
| `bounced` | Rejeitado | orange |
| `delivered` | Entregue | emerald |

**`EmailCategory`** — controla qual kill switch é verificado:

| Valor | Kill switch key |
|-------|-----------------|
| `auth` | `auth_enabled` |
| `export` | `export_enabled` |
| `notification` | `notification_enabled` |
| `system` | `system_enabled` |

---

### 4.4 SendEmailDTO

```php
readonly class SendEmailDTO
{
    public function __construct(
        public string        $to,
        public string        $subject,
        public string        $template,      // 'emails.exports.ready'
        public EmailCategory $category,
        public array         $variables = [],
        public ?string       $toName = null,
        public ?string       $replyTo = null,
        public ?string       $notifiableType = null,
        public ?int          $notifiableId = null,
        public ?string       $idempotencyKey = null, // auto-gerado se null
        public ?Carbon       $scheduledAt = null,
    ) {}

    // Retorna novo DTO com o `to` substituído (usado para EMAIL_TO_OVERRIDE)
    public function withTo(string $email, ?string $name = null): self
}
```

**Geração automática da chave de idempotência** (quando `idempotencyKey` é null):

```
sha256(to | template | notifiableId | date('Y-m-d'))
```

Essa chave agrupa o mesmo destinatário + template + entidade + dia para o bloqueio local de duplicatas. O claim e a geração impedem reentregas locais evitáveis; resultado externo incerto exige reconciliação e não há garantia de entrega exatamente uma vez.

---

### 4.5 EmailLog Model

Tabela `email_logs`. Representa um registro de tentativa de envio.

**Colunas principais:**

| Coluna | Tipo | Detalhe |
|--------|------|---------|
| `uuid` | string | Identificador público (rota de resend) |
| `to_email` | string | Destinatário real (pode ser o override) |
| `subject` | string | Assunto |
| `template` | string | Caminho da view Blade |
| `variables` | json | Variáveis injetadas na view |
| `category` | enum | `EmailCategory` |
| `status` | enum | `EmailStatus` |
| `attempts` | tinyint | Incrementado a cada tentativa |
| `error_message` | text | Última exceção capturada |
| `idempotency_key` | string unique | Previne duplicatas |
| `message_id` | string | ID retornado pelo provider, usado para correlacionar webhooks |
| `mailer` | string | Driver usado (`resend`, `smtp`, etc.) |
| `reply_to` | string nullable | Endereço de resposta persistido; reenvio/recuperação não dependem do DTO original |
| `scheduled_at` | datetime(6) nullable | Instante UTC mínimo para iniciar a entrega |
| `sent_at` / `failed_at` | datetime | Timestamps de estado |
| `notifiable_type/id` | morph | Entidade relacionada (ex: Export) |
| `metadata` | json | Dados extras do webhook |

**Métodos de ciclo de vida:**

```php
$log->markAsSent(?string $messageId);   // somente processing da mesma geração → sent
$log->markAsFailed(string $message);    // somente queued da mesma geração → failed seguro
$log->markAsDelivered(?array $metadata); // status → delivered (webhook)
$log->markAsBounced(?array $metadata);   // status → bounced (webhook)
```

**Scopes:**

```php
EmailLog::ofStatus(EmailStatus::Failed)->get();
EmailLog::ofCategory(EmailCategory::Export)->get();
EmailLog::forEmail('user@example.com')->get();
```

**Índices compostos:**

- `[status, created_at]` — filtro por status com ordenação
- `[category, status]` — filtro combinado
- `[to_email, created_at]` — busca por destinatário
- `message_id` — lookup de webhook

---

### 4.6 EmailService

Implementação de `EmailServiceInterface`. Singleton (registrado no `EmailServiceProvider`).

**`queue(SendEmailDTO): ?EmailLog`** — caminho principal:

1. Verifica kill switch global (`email.enabled`)
2. Verifica kill switch do módulo (`email.{category}_enabled`)
3. Aplica `EMAIL_TO_OVERRIDE` se configurado
4. Verifica/cria `EmailLog` com idempotência
5. Publica `ProcessEmailJob` após o commit externo
6. Retorna `EmailLog` ou `null` se bloqueado/duplicata

**`sendNow(SendEmailDTO): ?EmailLog`** — envio síncrono (use apenas quando necessário, ex: emails críticos de senha):

- Mesmo fluxo de kill switch e override
- Usa a mesma chave única, geração e claim de `queue`; duplicata retorna null
- Executa após commit; em transação externa retorna inicialmente Queued e só envia quando ela confirmar

**`findOrCreateLog` — lógica de idempotência:**

```
Existe log com mesma chave?
├── Não → cria novo (INSERT protegido por chave única)
├── Sim, failed + retry_safe e sem sent_at/message_id → CAS queued + token novo
└── Demais estados, inclusive failed incerto e estados de webhook → null
```

`retry_safe` começa false, inclusive nos registros legados. Só falha anterior ao transporte ou reconciliação explícita de não envio concede retry. Corrida de criação usa leitura atual com lock após conflito único para enxergar o vencedor mesmo em MySQL REPEATABLE READ; nenhuma chamada externa ocorre sob esse lock.

---

### 4.7 ProcessEmailJob

#### Opções persistidas e agenda — MAIL-003

`queue` e `sendNow` persistem `replyTo`/`scheduledAt` como `reply_to`/`scheduled_at` no primeiro registro. A agenda é normalizada para UTC com microssegundos. Retry da mesma chave, reenvio e recuperação mantêm o payload original, inclusive esses campos e o nome do mailer. Uma nova configuração `mail.default` só afeta novos registros. O nome persistido não congela credenciais/host/transportes configurados sob esse alias.

Agenda futura exige fila `database`, `redis` ou `beanstalkd`, com delay durável. Mesmo `sendNow` futuro publica na fila após commit. Drivers incompatíveis (sync/null/deferred/background/failover/desconhecido e SQS, devido a limites/FIFO) são recusados explicitamente, com rollback da submissão/reenvio; não há entrega imediata como fallback. Data passada/nula mantém o comportamento imediato. O delay é centralizado na construção de `ProcessEmailJob`, cobrindo publicação inicial, ResendEmailAction e email:recover. O job recarrega a agenda persistida, libera execução prematura sem claim/contador da entrega e exige vencimento UTC no CAS de aquisição. Repetição após vencimento continua protegida por token/estado.

O job lê reply-to somente do log; o antigo parâmetro `replyTo` do construtor foi removido. Um escalar antigo no payload serializado não sobrepõe o contrato persistido. Mailer nulo/vazio ou alias ausente é falha anterior ao transporte, sem fallback para default atual. Reconciliar a decisão operacional antes de repetir; alterar o default não resolve automaticamente o backlog.

Aplicar também `2026_10_18_104742_add_delivery_options_to_email_logs_table.php` com todos os processos de envio parados, depois reiniciar com as fontes novas. A migration aditiva nullable não infere agenda/reply-to perdidos em legados nem envia mensagens. Revisar backlog/payloads antigos antes de republicá-los. Rollback remove os dois campos e suas opções; reconciliar/cancelar agendas antes de voltar ao código antigo, que as ignorava. Nesta estabilização, migrações foram executadas somente em bancos descartáveis; implantação no banco existente não foi realizada.

```php
class ProcessEmailJob implements ShouldQueue
{
    public int   $tries  = 3;
    public array $backoff = [30, 120, 300]; // 30s → 2min → 5min

    public function handle(): void
    {
        // Captura dispatchToken escalar; queued + token correto → processing por CAS
        // Pré-renderização valida template antes de entrar no mailer persistido
        // Mail::mailer($log->mailer)->to(...)->send(mailable)
        // Sucesso → markAsSent()
        // Falha antes do mailer → queued + throw; após entrar → processing + throw
    }

    public function failed(Throwable $e): void
    {
        // Somente queued + token exato → failed/retry_safe
        // Gate::forUser(...)->allows('view-email-logs', EmailLog::class)
        // Alerta genérico com UUID operacional; sem fallback, destinatário ou exceção
    }
}
```

O retry de 3 tentativas/backoff só repete trabalho comprovadamente anterior ao transporte. Depois de entrar no mailer, erros de envio, listeners, resolução de ID, persistência, timeout e crash são resultados incertos: Processing bloqueia reentrega/reenvio e exige reconciliação. Pré-renderizar não garante que o mailer não renderize novamente; toda exceção após entrada é tratada conservadoramente. Jobs/callbacks de geração antiga ou sem token são inertes. Estados de webhook mais fortes não são substituídos por conclusão local tardia.

#### Reconciliação e implantação

A [tabela compartilhada MAIL-001](../../stabilization/tasks/MAIL-001.md#contrato-de-execução-e-transições--2026-10-17) define progressão, terminais, eventos tardios e limites consumidos pelo inbox Resend da MAIL-002. Falha insegura/Bounced aceita confirmação forte de Delivered/Opened/Clicked, sem reenviar. Sent tardio só resolve Failed local ainda seguro; não apaga Failed de provedor. Eventos repetidos não alteram conteúdo/timestamps; evidência de provedor revoga retry_safe. O contrato temporal e a recuperação do inbox são descritos abaixo; isso não estende o inbox aos demais providers.

Aplicar `2026_10_17_232046_add_delivery_claim_to_email_logs_table.php` com todos os processos de envio parados e reiniciar workers com o código novo. A migration adiciona token nullable/retry_safe=false sem presumir entrega ou segurança dos registros existentes, amplia attempts para unsignedInteger e não envia nada. No rollback, remove os campos novos e conserva a largura do contador para evitar truncamento; não reativar código antigo com backlog de resultado incerto. Neste lote, migrations rodam somente em bancos descartáveis de teste.

O comando `php artisan email:recover <uuid> <token-ou-legacy> --outcome=... --evidence=INC-123 --workers-stopped` registra uma decisão operacional sem chamar provedor. A referência aceita somente letras, números, ponto, hífen, sublinhado e dois-pontos (3–80 caracteres); não fornecer conteúdo, destinatários, tokens ou segredos. O operador deve parar efetivamente **todos** os workers/processos de envio, inclusive sendNow/sync, e verificar no provedor/ticket o resultado antes de confirmar `--workers-stopped`. O comando não detecta processos nem consulta o provedor.

- `sent`: somente Processing/Failed inseguro ou Queued legado; confirmação positiva independente, `--message-id` opcional sem substituir ID incompatível.
- `not-sent`: mesmos estados, sem sent_at/message_id; confirmação de ausência de envio torna Failed seguro para reenvio explícito.
- `queued`: republica somente Queued com token não nulo e sem marcadores de aceitação, em driver durável explicitamente permitido: database, redis, sqs ou beanstalkd. SQS aceita somente agenda nula/vencida; agenda futura exige database/redis/beanstalkd. Recusa sync/null/deferred/background/failover e drivers desconhecidos; não troca geração. Configure e valide a fila antes dessa recuperação. Não consome a fila nem envia no próprio comando.

Queued legado não pode ser republicado diretamente: confirmar primeiro sent/not-sent. O token literal `legacy` exige que a coluna esteja nula e recebe identidade nova durante a reconciliação. IDs de geração podem ser consultados por operador com acesso ao banco, sem ampliar a projeção web. Jobs legados são inertes até reconciliação; planejar o backlog antes do rollout. Falha entre commit e publicação ainda pode deixar Queued sem job; republicação usa a mesma geração e o claim evita envio concorrente. Após renderização o job revalida status/token e ausência de sent_at/message_id, abortando se um evento já interveio. Existe uma janela inevitável entre essa checagem e o efeito externo; não há transação distribuída ou garantia de entrega exatamente uma vez. Não usar nova chave para contornar resultado incerto.

---

### 4.8 DynamicMailable

> **⚠️ Gotcha crítico:** as propriedades são `$emailTemplate` e `$replyToAddress` — **não** `$template` e `$replyTo`. O motivo: `Illuminate\Mail\Mailable` já declara `$replyTo` como propriedade não-readonly. Usar o mesmo nome causaria fatal error de PHP.

```php
class DynamicMailable extends Mailable
{
    public function __construct(
        private readonly string  $emailTemplate,  // ← NÃO $template
        private readonly array   $variables = [],
        private readonly ?string $replyToAddress = null, // ← NÃO $replyTo
    ) {}

    public function content(): Content
    {
        return new Content(
            view: $this->emailTemplate,
            with: $this->variables,
        );
    }
}
```

---

### 4.9 Webhooks de Entrega

**Endpoint público:** `POST /api/email/webhook/{provider}`

O `EmailWebhookService` mantém um registry de providers → handlers:

```php
// Registry padrão (registrado no EmailServiceProvider)
'resend'   => ResendWebhookHandler::class
'ses'      => SesWebhookHandler::class
'postmark' => PostmarkWebhookHandler::class

// Registrar handler customizado
EmailWebhookService::register('myProvider', MyWebhookHandler::class);
```

**Cada handler implementa `WebhookHandlerInterface`:**

```php
interface WebhookHandlerInterface
{
    public function verifySignature(Request $request): bool;
    public function handle(Request $request): void;
}
```

**Correlação com EmailLog:** feita por `message_id`. O provider retorna um ID no momento do envio (ex: Resend retorna `X-Resend-Email-ID`) — este ID é salvo em `email_logs.message_id`. O webhook usa esse mesmo ID para localizar o log e atualizar o status.

**Resend no Laravel 13:** o projeto usa o driver nativo `transport => resend` do Laravel. Para ativar em produção:

```bash
composer require resend/resend-php
```

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxx
RESEND_WEBHOOK_SECRET=whsec_xxxxxxxxx
```

O envio continua passando por `EmailService` e `ProcessEmailJob`; não use a facade direta da Resend para emails transacionais do módulo, porque isso pula o `email_logs`, kill switch, idempotência e correlação de webhooks.

**Captura do ID do provider:** `EmailProviderMessageIdResolver` lê `X-Resend-Email-ID` do `SentMessage` retornado pelo mailer. Para `log` e `array`, o resolver retorna `null` de propósito, porque não existe ID externo para webhook.

**Detalhes por provider:**

| Provider | Verificação de assinatura | Env var |
|----------|--------------------------|---------|
| Resend | SVIX (`svix-id`, `svix-timestamp`, `svix-signature`) | `RESEND_WEBHOOK_SECRET` |
| SES | RSA SNS v1/SHA1 ou v2/SHA256; tópico permitido; confirmação somente após autenticação | `SES_ALLOWED_TOPIC_ARNS` |
| Postmark | Header `X-Postmark-Webhook-Token` | `POSTMARK_WEBHOOK_TOKEN` |

> Todos os providers recusam configuração de autenticação ausente, inclusive em desenvolvimento. Não há bypass local; use fixtures assinadas e segredos sintéticos nos testes.

---

### Contrato dos webhooks Resend/Postmark

Resend exige `RESEND_WEBHOOK_SECRET` base64 canônico não-vazio, com `whsec_` somente no início ou formato legado sem prefixo. HMAC SHA256 autentica bytes exatos `svix-id.svix-timestamp.rawbody`. Timestamp deve ser inteiro positivo canônico sem overflow; aceita ±300s inclusivos de `now()->timestamp`, nega ±301s. Aceita qualquer v1 correspondente entre assinaturas da lista para rotação. `created_at` pode ser antigo: janela verifica tentativa, não evento. Configuração/headers/assinatura inválidos ou idade fora da janela respondem401 sem consultas/escritas de EmailLog/recorder. Sucesso continua204. A deduplicação existente trata repetições na janela; regras finais de recuperação/ordenação pertencem a MAIL-001/002.

Postmark exige `POSTMARK_WEBHOOK_TOKEN`, ligado em `config/services.php`. Configure o mesmo valor como `HttpHeaders: [{Name: X-Postmark-Webhook-Token, Value: ...}]` no provedor, sobre HTTPS; não usar API key. Strings não-blank e comparação exata `hash_equals`, sem conversão/trim silencioso. Header/config ausente ou incorreto responde401; sucesso200 vazio é necessário para verificação oficial Postmark. Não existe HMAC/timestamp nativo nesse contrato; token compartilhado não prova idade do evento. Não persistir esse segredo: TelescopeServiceProvider mantém o header na lista de ocultação.

Os dois handlers decodificam objeto JSON diretamente de `getContent()`, nunca da bag transformada por middleware: espaços/UTF-8/IDs originais são preservados. JSON malformado/não-objeto retorna401 antes de DB. `EmailWebhookService` é o consumidor que autentica e imediatamente processa, sem reescrita do corpo entre etapas. Mesma rota pública, sem SDK/dependência nova. [Contrato e evidências SEC-009](../../stabilization/tasks/SEC-009.md), [Postmark oficial](https://postmarkapp.com/developer/api/webhooks-api), [Resend oficial](https://resend.com/docs/webhooks/verify-webhooks-requests).

### Contrato do webhook SES/SNS

Configure `SES_ALLOWED_TOPIC_ARNS` com ARNs separados por vírgula; `config/services.php` remove espaços e entradas vazias e preenche `services.ses.allowed_topic_arns`. Configure explicitamente os ARNs permitidos; o padrão é uma lista vazia e nenhum tópico real é concedido pelo kit. O endpoint público aceita JSON raw com `Content-Type: text/plain` (padrão SNS) ou `application/json`, exige envelope/tópico/assinatura válidos e preserva bytes UTF-8, espaços e quebras de linha assinados. Não use a bag de input já transformada pelo middleware.

O certificado só pode vir do path SNS de certificado no host HTTPS regional derivado do tópico permitido. URLs com credenciais, porta explícita, query, fragmento ou destino arbitrário são recusadas antes da rede. Transporte exige OpenSSL/cURL, DNS IPv4 público unicast fixado, TLS verificado, proxy desligado, sem redirects e com timeouts/limites. O GET restrito do certificado precede a verificação criptográfica por necessidade do protocolo; nenhuma confirmação ou consulta de EmailLog ocorre antes da assinatura válida.

`verifySignature()` autentica e vincula envelope/corpo à Request. `handle()` sem essa prova recusa. A confirmação usa endpoint regional e parâmetros reconstruídos de `TopicArn`/`Token` autenticados, nunca navega a SubscribeURL bruta. Erros de estrutura/assinatura respondem401; indisponibilidade do certificado ou confirmação responde503 genérico para permitir retry, sem expor Token/URL. O mapeamento Delivery/Bounce/Complaint existente é preservado.

Suporte inicial: partição comercial `arn:aws:sns`, endpoints regionais IPv4, Message até256KiB/envelope2MiB. Sem confirmação automática de UnsubscribeConfirmation, sem bypass local e sem janela curta de idade que descarte retry legítimo. Assinatura válida não resolve replay, ordenação ou transições: [MAIL-001](../../stabilization/tasks/MAIL-001.md) e [MAIL-002](../../stabilization/tasks/MAIL-002.md) mantêm esses contratos. Não se alega integração AWS real pelos testes simulados.

Respostas admitidas para processamento têm no máximo64KiB; Content-Length excessivo é recusado, o callback nativo cURL retorna1 ao observar overflow e o corpo final é conferido. A transferência é observada por chunks, portanto não se promete receber exatamente até64KiB antes de cancelar. O [SesTransportIntegrationTest](../../../tests/Feature/Email/SesTransportIntegrationTest.php), com perfil `STABILIZATION_SNS_NATIVE_TRANSPORT=1`, usa somente servidor HTTP descartável em127.0.0.1 e exige abort42/download parcial antes do corpo completo256KiB. Essa prova local não alega TLS, assinatura ou integração AWS real; esses critérios usam as validações/fakes anteriores.

Implementação canônica: [SesWebhookHandler.php](../../../app/Modules/Email/Webhooks/SesWebhookHandler.php). Testes: [SesWebhookSecurityTest.php](../../../tests/Feature/Email/SesWebhookSecurityTest.php), com RSA/certificados sintéticos e HTTP/DNS substituídos, sem AWS real ou envio de mensagens.

---

#### Inbox durável Resend — MAIL-002

O Resend é o único consumidor de `EmailWebhookEventRecorder`. Depois da assinatura, `EmailWebhookService` vincula a autorização ao provider e hash do corpo recebido. `record` verifica esse contexto, guarda o corpo original/hash, somente headers `svix-id`/`svix-timestamp` e a prova `authenticated_at`. Mesmo event_id com bytes diferentes ou colisão de identidade por collation resulta409 genérico; nenhum conteúdo é substituído. Mantém-se o índice único existente `(provider,event_id)`: em MySQL, dois IDs distintos que colidam por case/espaço não são silenciosamente deduplicados, mas exigem correção/reconciliação operacional. IDs de mensagem são comparados exatamente, inclusive no MySQL (consulta binária e conferência em PHP).

Registro ocorre antes da transação de aplicação. Em HTTP normal sem transação externa, o registro fica commitado e uma falha posterior deixa um pendente recuperável. Se um chamador envolver o fluxo inteiro em sua própria transação e a desfizer, o registro também participa desse rollback; não há conexão paralela/outbox. `process` recarrega o evento persistido, faz lock de evento e log em ordem fixa, aplica o contrato MAIL001, atualiza cursor e processed_at juntos. Falha entre aplicação e conclusão desfaz a projeção/cursor/conclusão, sem descartar o registro anterior. Eventos concluídos são inertes na repetição. Nenhum envio, HTTP externo ou job novo ocorre no processador/CLI.

| Resultado | processed_at / recuperação |
|---|---|
| applied / state_preserved | Concluído, estado aplicado ou preservado pela tabela única |
| stale / unordered / invalid_timestamp / unsupported | Concluído sem substituir estado por evidência fraca/sem ordem/inválida ou tipo desconhecido |
| unmatched / ambiguous / provider_mismatch | Pendente até correlação única/correta; repetir o request ou usar CLI |
| future_timestamp | Pendente, reavaliável quando o relógio alcançar o evento |
| obsolete_generation | Concluído sem efeitos; vínculo/token/ID capturados já não correspondem à geração |
| untrusted / quarantined | Sem aplicação; exige evidência autenticada ou reconciliação, nunca promover via CLI |

O vínculo captura `email_log_id`, `dispatch_token` e `linked_at`, inclusive quando o token legado é nulo. Um log excluído ou reenvio que limpe o message_id não permite capturar uma nova geração pelo mesmo ID. Correlação ausente permanece pendente e é refeita quando o log aparecer. SMTP/log/array e aliases configurados desses transportes são aceitos além de Resend; isso não atesta uma conta externa. Outro provider explícito ou transporte desconhecido permanece pendente.

Relógio: ISO/RFC3339 estrito com timezone e no máximo6decimais, normalizado para UTC. `occurred_at` e `webhook_occurred_at` usam DATETIME(6), com leitura UTC mesmo em app local, evitando o limite MySQL2038. Anos UTC fora de1000–9999 são inválidos. Mais de5min no futuro fica pendente; expressões relativas, datas impossíveis e formato inválido não alteram o log. Sem timestamp, não se usa received_at como prova temporal: só aplicação inicial ou progressão forte da tabela MAIL001.

O cursor é o **máximo observado** de eventos reconhecidos, válidos, corretamente correlacionados e da geração certa, mesmo se o estado for preservado; não é o timestamp do estado escolhido. Evidência antiga fraca (Sent/Failed/Bounced/DeliveryDelayed/Scheduled) não substitui evidência nova. Progressões fortes Delivered/Opened/Clicked/Suppressed/Complained ainda podem prevalecer se permitidas por MAIL001, sem regredir cursor; reclamação/abertura não se perdem por chegada tardia. Empates usam somente a tabela, sem ordenar IDs lexicograficamente. Mesma evidência mais nova pode avançar cursor preservando metadata e timestamps do estado. Reenvio seguro limpa cursor e cria geração nova.

Recuperação: `php artisan email:webhooks:reprocess [uuid] --limit=100` processa no máximo1–1000 eventos Resend pendentes com authenticated_at. UUID opcional permite selecionar um pendente além do primeiro lote; limites/UUID inválidos falham, e UUID sem pendente confiável é recusado. A saída contém apenas contagens. Pendentes sem correlação ou no futuro não desaparecem: repetir a seleção limitada pode reencontrá-los; usar UUID/inspeção operacional para outros eventos. Não há varredura automática ou promessa de justiça do lote. O comando nunca reconfirma inscrições SNS nem chama provedores.

Legados: a migration `2026_10_18_003725_add_processing_contract_to_email_webhook_events.php` não atesta registros existentes. No MySQL, a conversão TIMESTAMP→DATETIME ocorre com sessão temporariamente UTC para preservar os instantes legados; o timezone anterior é restaurado mesmo em falha. CLI não aplica esses registros. Retry assinado atual pode atestar somente um raw idêntico e reextrai os fatos do corpo verificado; um legado já processado não é aplicado novamente. Corpo ausente/divergente permanece sem atestado (409 no conflito), sem copiar autoridade de um registro antigo. No rollback, os campos novos são removidos, conservando a representação ampliada de occurred_at para não truncar precisão/intervalo. Planejar aplicação do schema e reinício dos consumidores antes de operar o inbox; migrations e comandos desta entrega são validados apenas em bancos descartáveis.

SES/Postmark preservam a autenticação SEC008/009 e monotonicidade MAIL001. Não usam esse inbox: esta entrega não promete deduplicação durável ou ordenação cronológica para eles. Não confundir a prova local de requests assinados/simulados com integração externa real.

### 4.10 Actions

**`ResendEmailAction`** — reenfileira um email com status `failed`:

```php
$action->handle($emailLog);
// Exige failed + retry_safe e ausência de sent_at/message_id, por CAS
// Geração nova; limpa erro/failed_at/metadata da tentativa anterior
// Publica ProcessEmailJob após commit; recusa snapshot obsoleto
```

**`SendEmailAction`** — wrapper público (injeta `EmailServiceInterface`):

```php
// Use quando preferir uma Action a injetar o service diretamente
app(SendEmailAction::class)->handle($dto);
```

---

### 4.11 Controller — Logs UI

A consulta da listagem seleciona somente as 14 colunas usadas pela projeção
operacional. JSONs de `variables` e `metadata`, chaves de idempotência e dados
internos de entrega não são carregados nos modelos dessa lista. O erro é retornado
como mensagem genérica; o conteúdo persistido permanece intacto.

**`EmailLogController`:**

| Método | Rota | Detalhe |
|--------|------|---------|
| `index` | `GET /email-logs` | `view-email-logs`; paginado (25), filtros: status, category, search |
| `resend` | `POST /email-logs/{log:uuid}/resend` | `resend-email-logs`; só aceita `failed` e reenfileira |
| `updateSettings` | `PATCH /email-logs/settings` | `manage-email-settings`; valida/persiste cinco booleanos |

O `index` retorna para o Inertia:
- `logs` — `LengthAwarePaginator` flat com projeção explícita de 14 campos; o modelo inteiro não é serializado
- `filters` — valores atuais dos filtros
- `statuses` — `EmailStatus::options()`
- `categories` — `EmailCategory::options()`
- `settings` — somente enabled, auth_enabled, export_enabled, notification_enabled e system_enabled, todos booleanos; valores legados 0, 1, "0" e "1" são normalizados preservando o estado aceito pela validação. Outros valores inválidos usam default booleano válido ou true.

---

Administração exige sessão autenticada e e-mail verificado, developer literal (AuthorityService nível 3), `permissions.super_role=developer`, ausência de bearer e token null/TransientToken. Os três Gates são distintos e reutilizam o contrato de `manageIntegrationSettings` no [AppServiceProvider](../../../app/Providers/AppServiceProvider.php). `user`, `admin`, `super-admin`, aliases e PATs são recusados. Slug de permissão homônimo não concede administração: todos os `can` e `Gate::authorize` recebem `EmailLog::class`, evitando o bypass funcional do Gate::before. Não chamar esses Gates sem esse argumento. UUID inexistente pode retornar 404 no binding antes do Gate; isso não concede leitura nem execução.

A lista também alimenta a Sheet, sem endpoint show. Retorna apenas uuid, destinatário/nome, assunto/template, categoria/status, tentativas, mailer/message_id, sent_at/failed_at/created_at e error_message genérico. Variables, metadata, idempotency_key, relações, remetente, ID interno, updated_at e exceções cruas ficam fora da resposta; permanecem armazenados para o pipeline. A mensagem genérica é “Falha no processamento do e-mail.” quando existe erro persistido.

`updateSettings` preserva JSON para chamadas comuns e responde com redirect/flash para `X-Inertia`, permitindo o fluxo real de `useForm.patch` sem modal de resposta inválida. Recusas antecedem validação/action/config e não despacham jobs ou mensagens.

### 4.12 Rotas

```php
use App\Modules\Email\Models\EmailLog;

Route::middleware(['auth', 'verified'])->prefix('email-logs')->name('email-logs.')->group(function () {
    Route::get('/', [EmailLogController::class, 'index'])
        ->can('view-email-logs', EmailLog::class)->name('index');
    Route::post('/{emailLog}/resend', [EmailLogController::class, 'resend'])
        ->can('resend-email-logs', EmailLog::class)->name('resend');
    Route::patch('/settings', [EmailLogController::class, 'updateSettings'])
        ->can('manage-email-settings', EmailLog::class)->name('settings.update');
});

// api.php — público, assinatura do provedor validada pelo handler
Route::post('/email/webhook/{provider}', [EmailWebhookController::class, 'handle']);
```

---

## 5. Frontend

### 5.1 Tipos TypeScript

**`resources/js/types/app/email-log.ts`:**

```typescript
export type EmailStatus =
    | 'queued'
    | 'processing'
    | 'sent'
    | 'failed'
    | 'bounced'
    | 'delivered'
    | 'delivery_delayed'
    | 'complained'
    | 'opened'
    | 'clicked'
    | 'received'
    | 'scheduled'
    | 'suppressed';
export type EmailCategory = 'auth' | 'export' | 'notification' | 'system';

export interface EmailLog {
    uuid: string;
    to_email: string;
    to_name: string | null;
    subject: string;
    template: string;
    category: EmailCategory;
    status: EmailStatus;
    error_message: string | null;
    attempts: number;
    mailer: string | null;
    message_id: string | null;
    sent_at: string | null;
    failed_at: string | null;
    created_at: string;
}

export interface EmailSettings {
    enabled?: boolean;
    auth_enabled?: boolean;
    export_enabled?: boolean;
    notification_enabled?: boolean;
    system_enabled?: boolean;
}
```

---

### 5.2 Página email-logs

**`resources/js/pages/app/email-logs/index.tsx`**

Componentes principais:

- **`KillSwitchPanel`** — toggles por módulo, salva via `PATCH /email-logs/settings` usando `useForm` do Inertia. Toggle implementado como `<button role="switch">` (sem componente Switch dedicado no projeto).
- **`FiltersBar`** — status, category, search. Navega com `router.get()` preservando query string.
- **`EmailLogTable`** — lista os logs. Clique em uma linha abre `EmailLogDetailSheet`.
- **`EmailLogDetailSheet`** — Sheet lateral com a projeção operacional da lista e botão de resend para emails `failed`; não recebe dados crus do modelo.
- **`PaginationBar`** — usa estrutura flat do Laravel (`prev_page_url`, `next_page_url`, `current_page`, `last_page`).

> **⚠️ Atenção ao tipo de paginação:** o `->paginate()` do Laravel passado diretamente ao Inertia retorna estrutura **flat** (`current_page`, `last_page`, `prev_page_url`, `next_page_url` na raiz). Não é a estrutura `{ data, meta, links }` dos API Resources. Use a interface `LaravelPaginator<T>` definida no próprio arquivo.

---

### 5.3 Kill switch panel

Os toggles seguem este padrão (sem componente `Switch` — não existe no projeto):

```tsx
<button
    role="switch"
    aria-checked={isOn}
    onClick={() => setData(key, !isOn)}
    className={`relative h-6 w-11 rounded-full transition-colors ${
        isOn ? 'bg-primary' : 'bg-input'
    }`}
>
    <span className={`absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform ${
        isOn ? 'translate-x-5' : 'translate-x-0'
    }`} />
</button>
```

---

## 6. Templates Blade

**Layout base:** `resources/views/emails/layouts/base.blade.php`

- HTML responsivo com CSS inline
- Max-width: 600px, centralizado
- Compatível com Gmail e Outlook
- Seção `@yield('content')` para o corpo
- Aceita `$preheaderText` como variável opcional

**Criar um novo template:**

```blade
{{-- resources/views/emails/minha-feature/meu-email.blade.php --}}
@extends('emails.layouts.base', [
    'preheaderText' => 'Texto de preview no cliente de email.',
])

@section('content')
    <p>Olá, {{ $userName }}!</p>
    {{-- Variáveis vêm do array $variables do SendEmailDTO --}}
@endsection
```

**Templates existentes:**

| Template | Variáveis |
|----------|-----------|
| `emails.exports.ready` | `$userName`, `$downloadUrl`, `$expiresAt` |

---

## 7. Como Integrar em uma Nova Feature

**Exemplo: notificação de pagamento aprovado**

```php
// Em qualquer Controller, Job ou Notification
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;

app(EmailServiceInterface::class)->queue(new SendEmailDTO(
    to:             $user->email,
    toName:         $user->name,
    subject:        'Pagamento aprovado',
    template:       'emails.payments.approved',  // criar o template
    category:       EmailCategory::Notification,
    variables:      [
        'userName'   => $user->name,
        'amount'     => 'R$ ' . number_format($payment->amount, 2, ',', '.'),
        'paymentId'  => $payment->uuid,
    ],
    notifiableType: Payment::class,
    notifiableId:   $payment->id,
    idempotencyKey: "payment-{$payment->id}-approved",
));
```

**Pontos de atenção:**
1. Escolha a `EmailCategory` correta — determina qual kill switch é verificado
2. Use `idempotencyKey` explícito quando a feature pode disparar o mesmo email mais de uma vez (ex: via retry de job)
3. `notifiableType/Id` é opcional mas ajuda para rastreamento

---

## 8. Como Criar um Novo Template

1. Crie `resources/views/emails/{feature}/{nome}.blade.php` estendendo o layout base
2. Documente as variáveis esperadas em comentário no topo do arquivo
3. Teste no playground: `/admin/dev/components/email` → preencha template + variáveis JSON → clique **Preview**
4. Adicione o template ao dropdown do playground (`email.tsx`) para facilitar reuso futuro

---

## 9. Como Adicionar um Novo Provider de Webhook

1. Crie `app/Modules/Email/Webhooks/MeuProviderWebhookHandler.php` implementando `WebhookHandlerInterface`
2. Implemente `verifySignature()` com a lógica de assinatura do provider
3. Implemente `handle()` para mapear eventos → `markAsDelivered()` / `markAsBounced()`
4. Registre no `EmailServiceProvider`:

```php
EmailWebhookService::register('meuprovider', MeuProviderWebhookHandler::class);
```

5. Configure o webhook no painel do provider apontando para:
   `POST https://seu-dominio.com/api/email/webhook/meuprovider`

---

## 10. Variáveis de Ambiente

| Variável                     | Padrão    | Descrição                                                   |
| ---------------------------- | --------- | ----------------------------------------------------------- |
| `MAIL_MAILER`                | `log`     | Driver de envio: `log`, `smtp`, `resend`, `ses`, `postmark` |
| `RESEND_API_KEY`             | *(vazio)* | API key usada pelo driver `resend` do Laravel                |
| `MAIL_FROM_ADDRESS`          | —         | Remetente padrão                                            |
| `MAIL_FROM_NAME`             | —         | Nome do remetente                                           |
| `EMAIL_ENABLED`              | `true`    | Kill switch global (default quando DB não tem valor)        |
| `EMAIL_AUTH_ENABLED`         | `true`    | Kill switch para categoria Auth                             |
| `EMAIL_EXPORT_ENABLED`       | `true`    | Kill switch para categoria Export                           |
| `EMAIL_NOTIFICATION_ENABLED` | `true`    | Kill switch para categoria Notification                     |
| `EMAIL_SYSTEM_ENABLED`       | `true`    | Kill switch para categoria System                           |
| `EMAIL_TO_OVERRIDE`          | *(vazio)* | Redireciona TODOS os emails para este endereço (staging)    |
| `RESEND_WEBHOOK_SECRET`      | *(vazio)* | Secret SVIX para verificação de webhooks do Resend          |
| `POSTMARK_WEBHOOK_TOKEN`     | *(vazio)* | Token para verificação de webhooks do Postmark              |

> `MAIL_MAILER=log` — emails são escritos em `storage/logs/laravel.log`. Ideal para desenvolvimento.

---

## 11. Referência da API

### EmailServiceInterface

```php
interface EmailServiceInterface
{
    public function queue(SendEmailDTO $dto): ?EmailLog;
    // Retorna null se: kill switch ativo, duplicata detectada

    public function sendNow(SendEmailDTO $dto): ?EmailLog;
    // Envio síncrono. Mesmo comportamento de kill switch e override.
    // Usa a mesma idempotência e aguarda commit da transação externa.
}
```

### AppSettings

```php
AppSettings::get(string $group, string $key, mixed $default = null): mixed
AppSettings::set(string $group, string $key, mixed $value): void
AppSettings::all(string $group): array<string, mixed>
AppSettings::forget(string $group, string $key): void
```

### EmailLog — métodos públicos

```php
$log->markAsSent(?string $messageId = null): bool
$log->markAsFailed(string $errorMessage): bool
$log->markAsDelivered(?array $metadata = null): bool
$log->markAsBounced(?array $metadata = null): bool
$log->isSent(): bool
$log->isFailed(): bool
```

---

## 12. Testes

**Testes do módulo:**

| Arquivo | Cenários |
|---------|----------|
| `EmailServiceTest` | queue cria log + despacha job; kill switch global/categoria; duplicatas bloqueadas; somente falha comprovadamente segura reutiliza chave; override |
| `EmailAtomicDeliveryTest` | estados terminais, gerações serializadas, reentrega, pré-renderização, resultado incerto e transições de webhook |
| `EmailDispatchCommitTest` | commit/rollback reais SQLite isolado; recuperação e recusas; transporte array real com falha pós-aceitação; interposição de evento |
| `EmailFailureAlertTest` | Gate real, minimização, ausência de fallback e callbacks obsoletos sem alerta |
| `EmailDeliveryConcurrencyTest` | perfil opt-in MySQL descartável: criação/retry/claim em processos distintos com disputa InnoDB comprovada |
| `EmailLogControllerTest` | developer legítimo e filtros; recusas de papéis/aliases/PAT/bearer/homônimos; ausência de efeitos; allowlists de logs/settings; erro genérico; validação e recuperação; JSON e redirect Inertia |
| `EmailWebhookTest` | Resend delivered; Resend bounced; assinatura SVIX válida/inválida; message_id desconhecido (silencia); provider desconhecido (404); SES delivery |
| `EmailProviderMessageIdResolverTest` | captura `X-Resend-Email-ID` e ignora mailers sem provider externo (`log`, `array`) |

**Executar:**

```bash
php artisan test --compact tests/Feature/Email/
```

---

## 13. Armadilhas Conhecidas

### DynamicMailable — nomes de propriedade

`$template` e `$replyTo` são propriedades de `Illuminate\Mail\Mailable` declaradas como não-readonly. Redeclarar como `readonly` no filho causa **fatal error** do PHP. Use sempre `$emailTemplate` e `$replyToAddress`.

### Idempotência e emails falhos

`failed` sozinho não autoriza reenvio: exige `retry_safe=true` e ausência de aceitação conhecida. Processing e estados terminais bloqueiam a mesma chave. `retry_safe` não é exposto na projeção web; a ação valida no servidor e informa quando é necessária reconciliação. Não trocar a chave para contornar resultado incerto. Consulte a recuperação acima antes de atuar sobre backlog legado.

### Factory — override de `newFactory()`

O `EmailLog` usa `HasFactory` mas a factory fica em `database/factories/` (fora do namespace padrão do módulo). É necessário sobrescrever `newFactory()` no model:

```php
protected static function newFactory(): EmailLogFactory
{
    return EmailLogFactory::new();
}
```

### Paginador flat no frontend

O `->paginate()` passado diretamente ao Inertia retorna estrutura flat, não `{ data, meta, links }`. Use a interface `LaravelPaginator<T>` definida em `resources/js/pages/app/email-logs/index.tsx`:

```typescript
interface LaravelPaginator<T> {
    data: T[]
    current_page: number
    last_page: number
    prev_page_url: string | null
    next_page_url: string | null
    total: number
    per_page: number
}
```

### Webhook endpoint sem auth

`POST /api/email/webhook/{provider}` deve ficar **fora** de qualquer middleware de autenticação. Providers externos como Resend e SES não enviam tokens de sessão Laravel. A segurança é feita por `verifySignature()` em cada handler.

### Queue em testes

Ao testar `resend`, use `Queue::fake()` antes do request. Sem isso, o job roda imediatamente na fila `sync` e tenta renderizar o template — que pode falhar por variáveis ausentes.

---

## 14. Dev Tools

Acesse `/admin/dev/components/email` para:

- **Kill switch status** — estado atual de cada módulo em tempo real
- **Playground** — formulário completo para enfileirar um email de teste real
- **Preview** — renderiza o template Blade com as variáveis atuais, exibe em iframe isolado
- **Documentação inline** — exemplos de código para todos os padrões do módulo
- **Logs recentes** — últimos 5 registros da tabela `email_logs`

O preview usa `POST /admin/dev/components/email/preview` que renderiza a view server-side e retorna HTML puro. O iframe usa `srcDoc` para injetar o HTML sem `document.write()`.
