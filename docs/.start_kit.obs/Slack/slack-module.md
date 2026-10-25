# Módulo Slack

Documentação completa da integração com Slack via Incoming Webhooks. Cobre arquitetura, kill switch runtime, reportagem automática de exceções, canal de log Monolog, painel admin e guia de uso para novas features.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Fluxo Completo](#2-fluxo-completo)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Backend](#4-backend)
   - [Configuração](#41-configuração)
   - [AppSettings — kill switch e tabela](#42-appsettings--kill-switch-e-tabela)
   - [SlackAlertLevel Enum](#43-slackalertlevel-enum)
   - [SlackMessageDTO](#44-slackmessagedto)
   - [SlackServiceInterface](#45-slackserviceinterface)
   - [SlackService](#46-slackservice)
   - [Exception Reporter](#47-exception-reporter)
   - [Canal de Log Monolog](#48-canal-de-log-monolog)
   - [Controller — Admin UI](#49-controller--admin-ui)
   - [Rotas](#410-rotas)
5. [Frontend](#5-frontend)
   - [Página Admin](#51-página-admin)
   - [Wayfinder Actions](#52-wayfinder-actions)
6. [Como Configurar o Webhook no Slack](#6-como-configurar-o-webhook-no-slack)
7. [Como Usar em uma Nova Feature](#7-como-usar-em-uma-nova-feature)
8. [Variáveis de Ambiente](#8-variáveis-de-ambiente)
9. [Referência da API do Módulo](#9-referência-da-api-do-módulo)
10. [Comportamento de Exceções](#10-comportamento-de-exceções)
11. [Formato das Mensagens no Slack](#11-formato-das-mensagens-no-slack)
12. [Armadilhas Conhecidas](#12-armadilhas-conhecidas)
13. [Testes](#13-testes)

---

## 1. Visão Geral

### Problema resolvido

Erros de produção e eventos críticos precisam ser visíveis imediatamente, sem que o desenvolvedor precise verificar logs manualmente. O módulo resolve isso com:

- Reporte automático de exceções não tratadas ao Slack (sem configuração extra por controller)
- Kill switch em runtime via banco + cache (sem deploy para desativar)
- API fluente para alertas customizados em qualquer camada da aplicação
- Canal de log Monolog ativado automaticamente quando `SLACK_ENABLED=true`
- Painel exclusivo de developer para configurar webhook, nível de alerta e testar a conexão

### Capacidades

| Recurso | Detalhe |
|---------|---------|
| Exceções automáticas | `bootstrap/app.php` captura e envia erros 500+ ao Slack |
| Filtro inteligente | Ignora 404, ValidationException, AuthenticationException |
| Alertas manuais | `SlackService::alert()` com nível, contexto e formatação automática |
| Mensagens customizadas | `SlackMessageDTO` com campos, título, cores e footer |
| Canal Monolog automático | `SLACK_ENABLED=true` inclui o canal no stack automaticamente |
| Kill switch | `AppSettings('slack','enabled')` — desativa sem deploy, TTL cache 60s |
| Painel admin | `/admin/slack` — UI completa para configurar e testar |
| Prioridade de config | SlackService: DB > `.env`; canal Monolog: configuração própria no boot |

---

## 2. Fluxo Completo

```
Exceção não tratada / Código chamador
        │
        ▼
SlackServiceInterface::send(SlackMessageDTO)
  ou  ::alert(message, level, context)
  ou  ::exception(Throwable, context?)
        │
        ├── isEnabled()?
        │     ├── AppSettings('slack','enabled') → DB tem prioridade
        │     ├── fallback: config('slack.enabled') → .env SLACK_ENABLED
        │     └── false → retorna false silenciosamente
        │
        ├── webhookUrl()?
        │     ├── AppSettings('slack','webhook_url') → DB tem prioridade
        │     └── fallback: config('slack.webhook_url') → .env SLACK_WEBHOOK_URL
        │
        ▼
SensitiveDataRedactor → payload completo redigido
Http::timeout(5)->post($webhookUrl, $payloadRedigido)
        │
        ├── Sucesso → retorna true
        └── ConnectionException / qualquer erro → Log::warning() local + retorna false
                                                   (não propaga exceção)
```

**Canal Monolog (ativado automaticamente via SLACK_ENABLED, paralelo e independente):**
```
Log::error('texto') / Log::critical('texto')
        │
        └── config/logging.php stack → inclui 'slack' quando SLACK_ENABLED=true
                └── Monolog SlackWebhookHandler → POST direto (usa SLACK_WEBHOOK_URL)
```

**Prioridade de leitura do SlackService:**
```
AppSettings (tabela app_settings, grupo 'slack') → config/slack.php (.env) → default hardcoded
```

---

## 3. Estrutura de Arquivos

```
app/Modules/Slack/
├── Contracts/
│   └── SlackServiceInterface.php       # Interface pública do módulo
├── DTOs/
│   └── SlackMessageDTO.php             # Payload tipado para o webhook
├── Enums/
│   └── SlackAlertLevel.php             # Info / Warning / Error / Critical
└── Services/
    └── SlackService.php                # Implementação concreta

config/
└── slack.php                           # Configuração com fallback .env

app/Http/Controllers/Admin/
└── SlackSettingsController.php         # API do painel admin (show/update/test)

resources/js/pages/admin/slack/
└── index.tsx                           # UI do painel admin

resources/js/actions/App/Http/Controllers/Admin/
└── SlackSettingsController.ts          # Wayfinder — gerado automaticamente

bootstrap/app.php                       # Exception reporter registrado aqui
app/Providers/AppServiceProvider.php    # Bind singleton
config/logging.php                      # Canal 'slack' do Monolog + stack dinâmico
docs/.start_kit.obs/Slack/
├── slack-module.md                     # Este arquivo
└── portar-para-novo-projeto.md         # Guia de portabilidade
```

---

## 4. Backend

### 4.1 Configuração

**`config/slack.php`**

```php
return [
    'webhook_url' => env('SLACK_WEBHOOK_URL'),
    'enabled'     => (bool) env('SLACK_ENABLED', true),
    'log_level'   => env('SLACK_LOG_LEVEL', 'warning'),
    'app_name'    => env('APP_NAME', 'Laravel'),
    'app_url'     => env('APP_URL'),
    'environment' => env('APP_ENV', 'production'),
];
```

> **Regra de prioridade:** `AppSettings` (banco de dados, tabela `app_settings`) tem precedência sobre `.env`. O `.env` é apenas fallback. O default atual de config/slack.php é true; projetos novos podem definir explicitamente SLACK_ENABLED=false. Sem URL efetiva o Service continua inativo.

---

### 4.2 AppSettings — kill switch e tabela

As configurações runtime são persistidas na **tabela `app_settings`** do banco MySQL do projeto:

```sql
SELECT * FROM app_settings WHERE `group` = 'slack';
-- id | group | key         | value                              | created_at          | updated_at
-- 1  | slack | webhook_url | "https://hooks.slack.com/..."      | 2026-05-19 13:07:29 | ...
-- 2  | slack | enabled     | true                               | ...                 | ...
-- 3  | slack | log_level   | "warning"                          | ...                 | ...
```

**Schema da tabela:**
```
app_settings
├── id (PK)
├── group   (string, indexed)   ← namespace, ex: 'slack', 'email'
├── key     (string, indexed)   ← chave, ex: 'webhook_url'
├── value   (JSON text)         ← valor JSON-encoded, ex: "null", "true", "\"warning\""
├── created_at
└── updated_at
UNIQUE(group, key)
```

**Chaves do grupo `slack`:**

| key | tipo | descrição |
|---|---|---|
| `webhook_url` | string\|null | URL do Incoming Webhook — `null` = use .env |
| `enabled` | bool | master kill switch |
| `log_level` | string | nível mínimo (info/warning/error/critical) |

**Leitura no código (SlackService):**
```php
// webhook URL: DB primeiro, .env como fallback
$url = AppSettings::get('slack', 'webhook_url') ?: config('slack.webhook_url');

// enabled: DB primeiro, .env como fallback
$enabled = AppSettings::get('slack', 'enabled', config('slack.enabled', false));
```

**Escrita (via painel admin ou Tinker):**
```php
AppSettings::set('slack', 'webhook_url', 'https://hooks.slack.com/...');
AppSettings::set('slack', 'enabled', true);
AppSettings::set('slack', 'log_level', 'error');
```

**Cache TTL: 60 segundos.** AppSettings::set/forget invalidam a chave imediatamente; salvar pelo painel não precisa aguardar o TTL.

**Atualização do webhook:** ausente, `null` ou vazio no request mantém a URL existente. Uma URL válida não vazia substitui o override; `reset_webhook=true` remove a chave via `AppSettings::forget()` e restaura o fallback do ambiente. Reset junto com URL não vazia é recusado com 422 antes de qualquer escrita. O painel nunca recebe a URL salva.

---

### 4.3 SlackAlertLevel Enum

`app/Modules/Slack/Enums/SlackAlertLevel.php`

```php
enum SlackAlertLevel: string
{
    case Info     = 'info';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';
}
```

| Case | Cor attachment | Emoji |
|---|---|---|
| Info | `#36a64f` (verde) | `:information_source:` |
| Warning | `#f0a500` (amarelo) | `:warning:` |
| Error | `#e01e5a` (vermelho) | `:x:` |
| Critical | `#8e0000` (vermelho escuro) | `:rotating_light:` |

**Métodos:**
- `label(): string` — label em português
- `color(): string` — hex para attachment
- `emoji(): string` — emoji para título
- `options(): array<string, string>` — para selects no frontend

---

### 4.4 SlackMessageDTO

`app/Modules/Slack/DTOs/SlackMessageDTO.php`

```php
readonly class SlackMessageDTO
{
    public function __construct(
        public string $text,                              // Corpo (suporta markdown Slack)
        public ?string $title = null,                     // Prefixado com emoji do nível
        public SlackAlertLevel $level = SlackAlertLevel::Info,
        public array $fields = [],                        // [['title'=>'', 'value'=>'', 'short'=>true], ...]
        public ?string $footer = null,
        public ?string $fallback = null,                  // Texto de notificação push
    ) {}

    public function toPayload(): array { ... }            // Formato Slack Attachments API
}
```

**Formato gerado pelo `toPayload()`:**
```json
{
  "attachments": [{
    "fallback": "texto de fallback",
    "color": "#e01e5a",
    "title": ":x: Título",
    "text": "corpo da mensagem",
    "fields": [
      { "title": "Campo", "value": "Valor", "short": true }
    ],
    "footer": "App | 2026-05-19 15:00:00",
    "ts": 1716134400
  }]
}
```

---

### 4.5 SlackServiceInterface

`app/Modules/Slack/Contracts/SlackServiceInterface.php`

```php
interface SlackServiceInterface
{
    /** Envia um DTO diretamente. */
    public function send(SlackMessageDTO $message): bool;

    /** Alerta rápido com contexto chave→valor. */
    public function alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool;

    /** Reporta metadados operacionais da exceção, sem mensagem ou trace crus. */
    public function exception(\Throwable $e, ?string $context = null): bool;

    /** Retorna true somente se enabled=true E webhook URL presente. */
    public function isEnabled(): bool;
}
```

**Bind (AppServiceProvider):**
```php
$this->app->singleton(SlackServiceInterface::class, SlackService::class);
```

---

### 4.6 SlackService

`app/Modules/Slack/Services/SlackService.php` recebe `App\Supports\SensitiveDataRedactor` por injeção.

`send()` aplica o kill switch e resolve o destino internamente; redige todo o payload DTO, incluindo texto, títulos, campos, footer e fallback, antes do POST com timeout de cinco segundos. Falha de transporte retorna false e registra somente a classe da exceção.

`alert()` redige contexto antes da conversão para campos/JSON. `exception()` envia descrição genérica, classe, arquivo/linha, ambiente, método e URL sem credenciais, query ou fragmento; mensagem e stack trace originais são omitidos. Objetos, resources e Throwable não são serializados livremente; a recursão é limitada.

`isEnabled()` exige enabled e URL efetiva. `webhookUrl()` usa AppSettings e depois config/slack.php. O destino não entra nas props nem no payload de alerta.

---

### 4.7 Exception Reporter

Registrado em `bootstrap/app.php`:

```php
$exceptions->report(function (\Throwable $e): void {
    if ($e instanceof AuthenticationException)                          return;
    if ($e instanceof ValidationException)                              return;
    if ($e instanceof NotFoundHttpException)                            return;
    if ($e instanceof HttpException && $e->getStatusCode() < 500)      return;

    rescue(fn () => app(SlackServiceInterface::class)->exception($e));
});
```

**Comportamento:**
- Executa em **paralelo** ao log normal — não substitui, não bloqueia
- `rescue()` captura qualquer falha no envio → nunca causa exceção secundária
- Não retorna `false` → o log normal do Laravel continua funcionando

**O que vai ao Slack:**

| Tipo | Slack? | Motivo |
|---|---|---|
| `Exception` genérica | ✅ | Inesperada |
| `RuntimeException` | ✅ | Inesperada |
| Falha de Job (queue) | ✅ | Inesperada |
| `AuthenticationException` | ❌ | Esperada (sessão expirada) |
| `ValidationException` | ❌ | Esperada (input do usuário) |
| `NotFoundHttpException` | ❌ | Esperada (404) |
| `HttpException` < 500 | ❌ | Esperada (4xx) |

---

### 4.8 Canal de Log Monolog

`config/logging.php` mantém o canal `slack` e a lista dinâmica do stack. O canal usa `App\Supports\RedactSlackLogs`; o stack recebe o mesmo tap somente quando seus canais efetivos incluem Slack. O processamento antes da interpolação e nos handlers evita serializar objetos/Throwable ou enviar dados sensíveis. Registros compartilhados desse stack também recebem redação; canais diretos alheios permanecem independentes.

O destino é `LOG_SLACK_WEBHOOK_URL`, com fallback em `SLACK_WEBHOOK_URL`, lido no boot. AppSettings, reset e kill switch do painel controlam o SlackService; não alteram o destino ou a ativação do canal Monolog. Ao portar, copie a configuração com os dois taps; adicionar apenas o canal perde essa proteção.

Exceções não tratadas passam pelo exception reporter. Chamadas explícitas `Log::error()`/`Log::critical()` usam o canal Monolog conforme seu nível.

---

### 4.9 Controller — Admin UI

`SlackSettingsController` usa `UpdateSlackSettingsRequest`. O Gate `manageIntegrationSettings` exige developer literal, nível 3 e sessão first-party, sem PAT/bearer, em qualquer ambiente. O middleware protege GET/PUT/test; o Request também autoriza antes da validação. Não há capacidade funcional que conceda este acesso global.

`show()` entrega somente `settings={enabled, log_level}`, `webhook_configured` boolean na raiz, `isEnabled` e `levelOptions`. Não retorna webhook, corpos ou configurações arbitrárias.

`update()` exige enabled boolean e log_level do enum; URL opcional válida de até 512 caracteres. URL omitida/null/vazia mantém, URL não vazia substitui, reset explícito remove override. Reset com URL não vazia é erro 422. A transação mantém as três configurações coerentes. `test()` usa somente o destino salvo; falha retorna erro controlado.

---

### 4.10 Rotas

```php
Route::name('admin.slack.')
    ->prefix('admin/slack')
    ->middleware(['auth', 'verified', 'can:manageIntegrationSettings'])
    ->group(function () {
        Route::get('/',      [SlackSettingsController::class, 'show'])   ->name('index');
        Route::put('/',      [SlackSettingsController::class, 'update']) ->name('update');
        Route::post('/test', [SlackSettingsController::class, 'test'])   ->name('test');
    });
```

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `/admin/slack` | `admin.slack.index` | Painel de configuração |
| PUT | `/admin/slack` | `admin.slack.update` | Salva no AppSettings |
| POST | `/admin/slack/test` | `admin.slack.test` | Envia msg de teste |

---

## 5. Frontend

### 5.1 Página Admin

`resources/js/pages/admin/slack/index.tsx` inicia o campo webhook vazio; o valor salvo nunca é enviado pelo servidor. O boolean `webhook_configured` informa existência sem revelar a URL. Salvar vazio mantém; “Restaurar webhook do ambiente” solicita reset explícito e limpa/desabilita o campo de substituição.

`useHttp` v3 faz PUT e POST com await/catch. Falha preserva o formulário e exibe toast genérico; sucesso limpa o novo segredo/reset e recarrega somente settings/isEnabled/webhook_configured. Testar usa a configuração persistida e fica desabilitado durante alterações não salvas, requests em curso ou integração inativa. Badge distingue Conectado, Desativado e Não configurado.

Os helpers Wayfinder e componentes existentes são reutilizados.

---

### 5.2 Wayfinder Actions

`resources/js/actions/App/Http/Controllers/Admin/SlackSettingsController.ts`

Gerado por `php artisan wayfinder:generate`.

```typescript
import { show, update, test } from '@/actions/App/Http/Controllers/Admin/SlackSettingsController';

show.url()   // '/admin/slack'
update.url() // '/admin/slack'
test.url()   // '/admin/slack/test'
```

---

## 6. Como Configurar o Webhook no Slack

1. Acesse **[api.slack.com/apps](https://api.slack.com/apps)**
2. **Create New App → From Scratch** — nome ex. `Start Kit Alerts`
3. Menu lateral: **Incoming Webhooks** → ative o toggle
4. **Add New Webhook to Workspace** → selecione o canal (ex. `#erros-producao`)
5. Clique em **Allow** → copie a URL gerada
6. Cole no painel `/admin/slack` ou no `.env` como `SLACK_WEBHOOK_URL`

**Canais sugeridos:**

| Canal | Para quê |
|---|---|
| `#erros-producao` | Exceções 500+, falhas de job |
| `#alertas` | Warnings, erros de integração |
| `#deploy` | Notificações de deploy |

---

## 7. Como Usar em uma Nova Feature

### Caso 1 — Alerta simples (via injeção)

```php
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\Enums\SlackAlertLevel;

class ProcessarPagamentoJob implements ShouldQueue
{
    public function __construct(private readonly SlackServiceInterface $slack) {}

    public function failed(\Throwable $e): void
    {
        $this->slack->alert(
            message: 'Falha ao processar pagamento.',
            level: SlackAlertLevel::Error,
            context: ['pedido_id' => $this->pedido->id, 'valor' => 'R$ '.$this->pedido->valor],
        );
    }
}
```

### Caso 2 — Exceção com contexto

```php
try {
    $this->apiExterna->sincronizar($payload);
} catch (\Throwable $e) {
    app(SlackServiceInterface::class)->exception($e, "Sync pedido #{$this->pedido->id}");
    throw $e;
}
```

### Caso 3 — Mensagem totalmente customizada

```php
$dto = new SlackMessageDTO(
    text: "Exportação de *{$total}* registros concluída em {$duracao}s.",
    title: 'Exportação finalizada',
    level: SlackAlertLevel::Info,
    fields: [
        ['title' => 'Usuário',   'value' => $user->name,          'short' => true],
        ['title' => 'Registros', 'value' => number_format($total), 'short' => true],
        ['title' => 'Arquivo',   'value' => $export->file_name,   'short' => false],
    ],
    footer: config('slack.app_name').' | '.now()->toDateTimeString(),
);

app(SlackServiceInterface::class)->send($dto);
```

### Checklist para nova feature com Slack

- [ ] Injetar `SlackServiceInterface` no construtor (não `app()` inline) para testabilidade
- [ ] Nível correto: `Info` = normal, `Warning` = anomalia, `Error` = falha recuperável, `Critical` = catástrofe
- [ ] Contexto suficiente para reproduzir: IDs, valores, endpoints
- [ ] **Não** incluir dados sensíveis (senhas, tokens, CPF)
- [ ] Verificar `isEnabled()` antes de loop — evitar flood

---

## 8. Variáveis de Ambiente

### Mínimo necessário (controla tudo)

```env
SLACK_ENABLED=true
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../...
```

Isso ativa:
- ✅ Exception reporter (`SlackService`)
- ✅ Canal Monolog no stack (level `error` por padrão)

### Tabela completa

| Variável | Padrão | Quem usa | Descrição |
|---|---|---|---|
| `SLACK_WEBHOOK_URL` | — | SlackService + Monolog (fallback) | URL do Incoming Webhook |
| `SLACK_ENABLED` | Service: true; inclusão automática no stack: false | SlackService + logging.php stack | Override DB controla só Service; LOG_STACK pode incluir Slack explicitamente |
| `SLACK_LOG_LEVEL` | `warning` | `config/slack.php` (não usado diretamente) | Nível da config |
| `LOG_SLACK_WEBHOOK_URL` | ← `SLACK_WEBHOOK_URL` | Monolog channel | Sobrescreve a URL só para Monolog |
| `LOG_SLACK_LEVEL` | `error` | Monolog channel | Nível mínimo do canal Monolog |
| `LOG_SLACK_USERNAME` | `APP_NAME` | Monolog channel | Username exibido no Slack |

> `LOG_SLACK_WEBHOOK_URL` só precisa ser definido se quiser usar uma URL **diferente** da `SLACK_WEBHOOK_URL` para os logs do Monolog.

---

## 9. Referência da API do Módulo

```php
// Envia DTO diretamente
send(SlackMessageDTO $message): bool

// Alerta rápido com contexto
alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool

// Reporta metadados operacionais da exceção
exception(\Throwable $e, ?string $context = null): bool

// Verifica se está ativo e configurado
isEnabled(): bool
```

```php
new SlackMessageDTO(
    text: string,         // suporta *bold*, _italic_, `code`, ```bloco```
    title: ?string,
    level: SlackAlertLevel,
    fields: array,        // [['title'=>'', 'value'=>'', 'short'=>true|false]]
    footer: ?string,
    fallback: ?string,
)
->toPayload(): array
```

---

## 10. Comportamento de Exceções

```
Exceção lançada
        │
        ▼
bootstrap/app.php → report()
        │
        ├── AuthenticationException? → ignorado
        ├── ValidationException?      → ignorado
        ├── NotFoundHttpException?    → ignorado
        ├── HttpException < 500?      → ignorado
        │
        ▼
rescue(fn() => SlackService::exception($e))
        │
        ├── isEnabled()? → false → silencioso
        │
        ▼
Http::post(webhook) timeout 5s
        └── erro no POST → Log::warning() local + false (nunca propaga)
```

**Para ignorar exceção adicional:**
```php
// bootstrap/app.php — dentro do report()
if ($e instanceof MinhaExcecaoEsperada) return;
```

---

## 11. Formato das Mensagens no Slack

### Exceção automática

```
🚨 Exceção não tratada
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Exceção   | App\Exceptions\PagamentoException
Mensagem  | Detalhes da exceção omitidos por segurança.
Arquivo   | app/Services/PagamentoService.php:87
Ambiente  | production
URL       | https://meuapp.com/api/pagamentos
Método    | POST
Ocorreu uma exceção não tratada.
────────────────────────────────
Start Kit | 2026-05-19 15:32:00
```

### Alerta manual

```
❌ Erro
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[texto da mensagem]

Pedido_id | 4521
Valor     | R$ 150,00
Ambiente  | production
────────────────────────────────
Start Kit | https://meuapp.com
```

---

## 12. Armadilhas Conhecidas

### 1. Campo webhook vazio mantém a configuração

O campo começa vazio por segurança, mesmo com webhook configurado. Salvar sem uma nova URL mantém o override. Para voltar ao ambiente, use o checkbox de reset; não é necessário consultar ou apagar dados manualmente. Valores null legados continuam usando fallback no SlackService.

---

### 2. Monolog não lê AppSettings

**Problema:** Mudou a URL pelo painel admin mas o `Log::error()` usa a URL antiga.

**Motivo:** O Monolog é inicializado em boot, lendo apenas o `.env`. Não consulta AppSettings.

**Regra:** Monolog usa `LOG_SLACK_WEBHOOK_URL`, com fallback em `SLACK_WEBHOOK_URL`, no boot. O `SlackService` (exception reporter + `alert()`) usa AppSettings.

---

### 3. `rescue()` silencia erros de configuração

**Problema:** Webhook inválida, nada chega no Slack, mas também nenhum erro visível.

**Solução:** Sempre testar via botão no painel admin ou Tinker:
```bash
php artisan tinker --execute 'echo app(\App\Modules\Slack\Contracts\SlackServiceInterface::class)->isEnabled() ? "ok" : "desativado/sem URL";'
```

---

### 4. Rate limit do Slack (cascade de erros)

**Problema:** DB offline → dezenas de exceções → Slack bloqueia com 429.

**Mitigação:** Adicionar throttle em `bootstrap/app.php`:
```php
$exceptions->throttle(function (\Throwable $e) {
    return Limit::perMinute(5)->by(get_class($e));
});
```

---

### 5. `SLACK_ENABLED=true` no `.env` vs. `enabled=false` no banco

**Comportamento:** O banco tem prioridade. Se o painel desativou (`enabled=false` no banco), o `.env` `SLACK_ENABLED=true` é **ignorado** pelo `SlackService`.

**Atenção:** O canal Monolog lê apenas o `.env`. Então `Log::error()` ainda vai ao Slack mesmo com o painel desativado — porque o Monolog não lê o banco.

**Para desativar completamente:** defina SLACK_ENABLED=false, retire slack de LOG_STACK caso esteja explícito e desative enabled no painel ou remova esse override para o Service respeitar o ambiente. Recarregue a configuração/processos. Desativar só o Service não impede inclusão automática do canal; SLACK_ENABLED=false sozinho não retira um canal incluído explicitamente no stack.

---

## 13. Testes

### Via Tinker

```bash
# Status
php artisan tinker --execute 'echo app(\App\Modules\Slack\Contracts\SlackServiceInterface::class)->isEnabled() ? "ativo" : "inativo";'

# Alert de teste
php artisan tinker --execute '
app(\App\Modules\Slack\Contracts\SlackServiceInterface::class)->alert(
    "Teste manual",
    \App\Modules\Slack\Enums\SlackAlertLevel::Warning,
    ["origem" => "tinker", "data" => now()->toDateTimeString()]
);'

# Simular exceção
php artisan tinker --execute '
try { throw new \RuntimeException("Teste"); }
catch (\Throwable $e) {
    app(\App\Modules\Slack\Contracts\SlackServiceInterface::class)->exception($e, "Teste manual");
}'
```

### Via painel admin

1. Acesse `/admin/slack`
2. Configure webhook URL + ative
3. Clique em **Enviar mensagem de teste**
4. Verifique o canal no Slack

### Testes unitários sugeridos

```php
class SlackServiceTest extends TestCase
{
    public function test_does_not_send_when_disabled(): void
    {
        AppSettings::set('slack', 'enabled', false);
        $this->assertFalse(app(SlackServiceInterface::class)->isEnabled());
    }

    public function test_does_not_send_without_webhook_url(): void
    {
        config(['slack.webhook_url' => null]);
        AppSettings::forget('slack', 'webhook_url');
        $this->assertFalse(app(SlackServiceInterface::class)->isEnabled());
    }

    public function test_send_posts_to_webhook(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('ok', 200)]);
        AppSettings::set('slack', 'webhook_url', 'https://hooks.slack.com/test');
        AppSettings::set('slack', 'enabled', true);

        $result = app(SlackServiceInterface::class)->alert('Teste', SlackAlertLevel::Info);

        $this->assertTrue($result);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com'));
    }

    public function test_null_in_db_falls_back_to_env(): void
    {
        AppSettings::set('slack', 'webhook_url', null);
        config(['slack.webhook_url' => 'https://hooks.slack.com/from-env']);

        Http::fake(['https://hooks.slack.com/*' => Http::response('ok', 200)]);
        AppSettings::set('slack', 'enabled', true);

        $result = app(SlackServiceInterface::class)->alert('Teste', SlackAlertLevel::Info);
        $this->assertTrue($result);
    }
}
```

## Regressões de estabilização (SEC-004)

`SlackSettingsSecurityTest`, `ExternalApiHardeningTest`, `IntegrationRedactionTest`, `TelescopeSensitiveDataTest` e o perfil integration_settings em `AuthorityHttpIntegrationTest` cobrem autorização, keep/replace/reset, props sem segredo, cache antigo, payloads e falhas/recuperação. Use o isolamento PREP-001 e Http::preventStrayRequests/fakes; não execute exemplos Tinker de envio durante testes automatizados. Evidências e limites reais estão em [SEC-004](../../stabilization/tasks/SEC-004.md).
