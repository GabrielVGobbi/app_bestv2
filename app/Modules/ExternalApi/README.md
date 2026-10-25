# ExternalApi Module

Modulo Laravel para integrar APIs externas de forma portavel entre projetos. A estrutura centraliza configuracao, HTTP clients, mocks, DTOs, providers, rotas de teste e persistencia OAuth dentro de `app/Modules/ExternalApi`.

## Estrutura

```text
app/Modules/ExternalApi
├── Console/Commands
│   └── ExternalApiInstallCommand.php
├── Core
│   ├── Abstracts
│   ├── Contracts
│   ├── DTOs
│   ├── Exceptions
│   └── Traits
├── Database/Migrations
├── Facades
├── Models
├── Modules
│   ├── Asaas
│   ├── Iugu
│   └── Spotify
├── Providers
│   └── ExternalApiServiceProvider.php
├── config/external-apis.php
└── routes.php
```

### Core

- `AbstractConfiguration`: padroniza leitura de `external-apis.modules.{module}`.
- `AbstractHttpClient`: HTTP client base com `timeout`, `connectTimeout`, retry somente em GET, rate limit, cache opt-in por endpoint, logs estruturados e tratamento de erro.
- `ExternalApiRegistry`: resolve modulos pela config, incluindo provider, manager, configuration, HTTP client, mock client e modo fake/real.
- `RetryPolicy`: centraliza backoff exponencial com jitter, `Retry-After` e status retryable.
- `ExternalApiRateLimiter`: controla limite por modulo/endpoint com lock curto de cache.
- `ExternalApiResponseCache`: cacheia somente `GET` bem-sucedido quando o endpoint opta por cache.
- `ExternalApiRequestLogger`: grava logs estruturados e mantem snapshots recentes em cache para diagnostico.
- `OAuthStateManager`: gera e valida OAuth state por provider/usuario.
- `MockHttpClientInterface`: marca clients fake para garantir uso sem rede real.
- `BaseResponse`: envelope comum para respostas HTTP/mock.
- Exceptions: `ExternalApiException`, `ConfigurationException`, `HttpException`, `RateLimitException`.

### Persistencia OAuth

O model fica dentro do modulo:

```php
App\Modules\ExternalApi\Models\ExternalApiConnection
```

A migration tambem fica no modulo:

```text
app/Modules/ExternalApi/Database/Migrations/2026_05_11_174025_create_external_api_connections_table.php
```

O provider carrega essa migration com `loadMigrationsFrom`, entao ela roda normalmente com:

```bash
php artisan migrate
```

Os tokens sao salvos com casts criptografados:

- `access_token`: `encrypted`
- `refresh_token`: `encrypted`
- `scopes`: `encrypted:array`
- `metadata`: `encrypted:array`

## Instalacao em outro projeto

1. Copie `app/Modules/ExternalApi` para o projeto.
2. Registre o provider em `bootstrap/providers.php`:

```php
use App\Modules\ExternalApi\Providers\ExternalApiServiceProvider;

return [
    // ...
    ExternalApiServiceProvider::class,
];
```

3. Gere a config publicada:

```bash
php artisan external-api:install
```

Para sobrescrever uma config existente:

```bash
php artisan external-api:install --force
```

Tambem e possivel publicar via tag:

```bash
php artisan vendor:publish --tag=external-api-config
```

4. Rode as migrations:

```bash
php artisan migrate
```

5. Regere autoload e, se usar Wayfinder:

```bash
composer dump-autoload
php artisan wayfinder:generate --with-form --no-interaction
```

## Configuracao

A fonte do modulo fica em:

```text
app/Modules/ExternalApi/config/external-apis.php
```

A copia customizavel da aplicacao fica em:

```text
config/external-apis.php
```

Formato base:

```php
return [
    'fake' => [
        'enabled' => env('EXTERNAL_API_FAKE_ENABLED', true),
    ],

    'modules' => [
        'spotify' => [
            'enabled' => env('SPOTIFY_ENABLED', true),
            'fake' => env('SPOTIFY_FAKE_ENABLED', env('EXTERNAL_API_FAKE_ENABLED', true)),
            'base_url' => env('SPOTIFY_BASE_URL', 'https://api.spotify.com/v1'),
            'auth_url' => env('SPOTIFY_AUTH_URL', 'https://accounts.spotify.com'),
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
            'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
            'scopes' => env('SPOTIFY_SCOPES', 'user-read-email playlist-read-private playlist-read-collaborative'),
            'timeout' => env('SPOTIFY_TIMEOUT', 30),
            'connect_timeout' => env('SPOTIFY_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('SPOTIFY_RETRY_ATTEMPTS', 3),
            'rate_limit' => env('SPOTIFY_RATE_LIMIT', 100),
        ],
    ],
];
```

Config v2 tambem suporta:

- `external-apis.retry`: base delay, max delay, jitter e status retryable.
- `external-apis.rate_limit`: decay e lock para contadores.
- `external-apis.logging`: log estruturado por request, com redacao de tokens e secrets.
- `external-apis.diagnostics`: liga/desliga a tela de diagnostico.
- `external-apis.modules.{module}.provider|manager|configuration|http_client|mock_client`: registry metadata.
- `external-apis.modules.{module}.endpoints.{endpoint}.cache`: cache opt-in por endpoint.
- `external-apis.modules.{module}.endpoints.{endpoint}.rate_limit`: override de limite por endpoint.

### Fake mode

Mocks sao globais por padrao e podem ser sobrescritos por modulo.

```env
EXTERNAL_API_FAKE_ENABLED=true
SPOTIFY_FAKE_ENABLED=true
```

O Spotify possui o simulador `SpotifyMockHttpClient`: com `fake=true`, suas operacoes retornam os dados mockados sem acessar a rede.

Iugu e Asaas ainda nao possuem simulador. Nesses modulos, `fake=true` permite resolver configuracao, client e manager para inspecao, mas as operacoes lancam `ConfigurationException` (`Fake mode is not supported by this HTTP client.`). O transporte base recusa antes de ler headers, consultar cache, consumir rate limit, registrar request ou enviar HTTP. A mesma protecao vale para construcao direta do client e transporte raw herdado; implementar apenas o marcador de mock nao libera rede real em modo fake.

A flag e conferida em cada operacao, inclusive em clients ja resolvidos e quando existe resposta em cache. A configuracao do modulo prevalece sobre o padrao global; modulo `fake=null` herda `external-apis.fake.enabled`. O transporte real exige a opcao `fake=false` e as credenciais exigidas pelo manager. Nao ha troca automatica para modo real quando um mock esta ausente. Os testes desse caminho usam `Http::fake()` e `Http::preventStrayRequests()`, sem chamar os provedores.

### Retry e resultado incerto

Retries automaticos sao exclusivos de `GET`: `retry_attempts` define o total de tentativas (minimo uma), com os status configurados, falhas de conexao, backoff e `Retry-After` existentes. A politica recebe o metodo explicitamente nos transportes normal e raw.

`POST`, `PUT`, `DELETE` e os demais metodos fazem uma tentativa automatica. Isso tambem vale para os tres grants POST do Spotify (`client_credentials`, `authorization_code` e `refresh_token`), que usam transporte proprio. Respostas 429/5xx ou falhas de conexao continuam propagando o erro; nao provocam reenvio automatico da operacao.

Um timeout pode ocorrer depois de o provedor aceitar a criacao. O consumidor deve consultar o resultado usando a referencia externa ou um identificador ja conhecido e reconciliar o efeito antes de decidir uma nova operacao. Em uma resposta perdida pode nao haver identificador retornado; use find somente quando o ID for conhecido. No Asaas, `externalReference` e correlacao, sem garantia de unicidade adotada pelo kit; uma consulta vazia imediata nao prova que a criacao falhou. Os servicos existentes de clientes e cobrancas oferecem `list`/`find` para consulta explicita, sem um fluxo automatico de recriacao.

Nao ha opt-in de retry de escrita nem chave de idempotencia adicionada pelo kit. O suporte documentado de Iugu e limitado a operacoes especificas; os managers Iugu atuais oferecem somente leituras. Qualquer futura repeticao de escrita exige contrato do endpoint, chave estavel quando suportada e testes de resposta perdida antes de habilitacao.

Referencias oficiais consultadas em 2026-10-17: [Asaas: retries e idempotencia](https://docs.asaas.com/docs/retries-e-idempot%C3%AAncia), [Asaas: criar cobranca](https://docs.asaas.com/reference/criar-nova-cobranca), [Iugu: chave de idempotencia](https://dev.iugu.com/docs/chave-de-idempot%C3%AAncia-1), [HTTP: repeticao automatica](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.2.2) e [OAuth: uso do authorization code](https://www.rfc-editor.org/rfc/rfc6749.html#section-4.1.2). A politica GET-only e a escolha conservadora do kit, sem atribuir aos provedores uma proibicao geral de repetir chamadas.

### Registry

Os modulos sao registrados pela config, nao por uma lista fixa no provider raiz.

```php
use App\Modules\ExternalApi\Core\Support\ExternalApiRegistry;

$registry = app(ExternalApiRegistry::class);
$modules = $registry->modules();
$spotify = $registry->manager('spotify');
```

Cada modulo deve declarar `provider`, `manager`, `configuration`, `http_client` e, quando existir, `mock_client`.

### Cache por endpoint

O cache e opt-in. Mesmo com `external-apis.cache.enabled=true`, um endpoint so cacheia se declarar:

```php
'endpoints' => [
    'search' => [
        'cache' => [
            'enabled' => true,
            'ttl' => 300,
        ],
    ],
],
```

Somente requests `GET` com resposta `2xx` sao armazenados.

### Logs e diagnostico

Cada request real registra contexto estruturado no log do Laravel e em snapshots recentes no cache:

- modulo, modo fake/real, metodo, endpoint, status code;
- duracao, cache hit/miss, retry count;
- usuario autenticado quando existir;
- allowlist operacional: id, module, mode, user_id, recorded_at, method, url, endpoint, status, status_code, duration_ms, cache_hit, retry_count e error genérico;
- URLs sem userinfo, query e fragmento; sem corpos, headers, erros externos crus ou metadata arbitrária;
- a projeção também é aplicada ao ler snapshots antigos do cache; cache funcional de respostas não muda.

A tela exige `manageIntegrationSettings` (developer literal/nível 3/sessão first-party, sem PAT). Configurações são projetadas por tipos/chaves conhecidas; credenciais são apenas indicadores booleanos. Rotas Spotify e OAuth preservam seus próprios contratos.

Tela:

```text
GET /external-api/diagnostics    external-api.diagnostics
```

## Spotify

O modulo Spotify implementa:

- Client Credentials para catalogo publico.
- Authorization Code OAuth para conexao de usuario.
- Refresh token com lock por usuario/provider para evitar refresh simultaneo.
- Search de artistas e playlists.
- Consulta de track, artist, album e playlist por ID.
- DTOs para track, artist, album, playlist, token e search result.
- Mock data realista para todos os endpoints implementados.
- Telas Inertia para teste visual.

### Facade

```php
use App\Modules\ExternalApi\Facades\Spotify;

$artists = Spotify::search()->artists('Daft Punk');
$playlists = Spotify::search()->playlists('Focus');
$track = Spotify::tracks()->find('track-id');
$artist = Spotify::artists()->find('artist-id');
$album = Spotify::albums()->find('album-id');
$playlist = Spotify::playlists()->find('playlist-id');
```

### Injecao de dependencia

```php
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;

public function __construct(private SpotifyManager $spotify) {}

public function __invoke(): array
{
    return $this->spotify->search()->artists('Daft Punk')->toArray();
}
```

### OAuth

Rotas autenticadas:

```text
GET    /external-api/spotify/redirect    external-api.spotify.redirect
GET    /external-api/spotify/callback    external-api.spotify.callback
DELETE /external-api/spotify/disconnect  external-api.spotify.disconnect
```

Uso por servico:

```php
$url = Spotify::auth()->getAuthorizationUrl($user);
$connection = Spotify::auth()->handleCallback($user, $code, $state);
Spotify::auth()->disconnect($user);
$token = Spotify::auth()->getUserAccessToken($user);
```

Variaveis `.env` para OAuth real:

```env
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
SPOTIFY_REDIRECT_URI=https://seu-dominio.test/external-api/spotify/callback
SPOTIFY_SCOPES="user-read-email playlist-read-private playlist-read-collaborative"
SPOTIFY_FAKE_ENABLED=false
```

## Telas de teste UX

Rotas Inertia para validar visualmente a integracao:

```text
GET /external-api/spotify/artists    external-api.spotify.artists
GET /external-api/spotify/playlists  external-api.spotify.playlists
GET /external-api/diagnostics         external-api.diagnostics
```

Paginas React:

```text
resources/js/pages/app/external-api/spotify/artists.tsx
resources/js/pages/app/external-api/spotify/playlists.tsx
```

Navegacao em `config/navigation.php`:

- External APIs
  - Diagnostico
  - Spotify Artistas
  - Spotify Playlists

Essas telas usam o mesmo service layer do backend. Em fake mode exibem os mocks; em modo real usam a API do Spotify.

## Iugu e Asaas

Iugu e Asaas foram mantidos como modulos basicos para preservar a estrutura e o autoload:

```php
use App\Modules\ExternalApi\Facades\Iugu;
use App\Modules\ExternalApi\Facades\Asaas;

Iugu::customers()->list();
Iugu::payments()->list();

Asaas::customers()->list();
Asaas::payments()->list();
```

Eles estao padronizados com:

```text
external-apis.modules.iugu.*
external-apis.modules.asaas.*
```

## Adicionando uma nova API

Use o scaffold para criar a base:

```bash
php artisan external-api:make Stripe
php artisan external-api:make Stripe --with-oauth --register
```

O comando gera provider, configuration, manager, HTTP client real, mock client, DTO exemplo, service exemplo, mock data, facade e teste PHPUnit.

Para criar `Modules/Stripe`, siga este checklist:

1. Criar `Modules/Stripe/Configuration/StripeConfiguration.php`.
2. Estender `AbstractConfiguration` e retornar `getModuleKey(): string` como `stripe`.
3. Criar `Modules/Stripe/Http/StripeHttpClient.php` estendendo `AbstractHttpClient`.
4. Criar `Modules/Stripe/Http/StripeMockHttpClient.php` implementando `MockHttpClientInterface`.
5. Criar `Modules/Stripe/Mocks/StripeMockData.php`.
6. Criar `Modules/Stripe/Services/StripeManager.php` estendendo `AbstractApiService`.
7. Criar services por recurso: `CustomerService`, `PaymentService`, etc.
8. Criar DTOs para respostas estaveis.
9. Criar `Modules/Stripe/Providers/StripeServiceProvider.php`.
10. Registrar o provider na config do registry.
11. Criar facade em `Facades/Stripe.php`, se fizer sentido.
12. Adicionar config em `app/Modules/ExternalApi/config/external-apis.php`.
13. Adicionar endpoints com cache/rate limit opt-in quando necessario.
14. Adicionar testes PHPUnit com `Http::fake()` e `Http::preventStrayRequests()`.

Regra importante: todo modulo novo deve ter mock runtime, nao apenas mock de teste.

## Testes e verificacao

Comandos usados para validar o modulo:

```bash
composer dump-autoload
php artisan migrate:fresh --env=testing --no-interaction
php artisan wayfinder:generate --with-form --no-interaction
npm run types:check
vendor/bin/pint app/Modules/ExternalApi config/external-apis.php config/navigation.php tests/Feature/ExternalApi --format agent
php artisan test --compact --filter=ExternalApi
php artisan test --compact --filter=Spotify
```

Cobertura atual:

- Provider e rotas registradas.
- Config carregada.
- Comando `external-api:install`.
- Migration interna carregada pelo provider.
- Fake mode global e override por modulo.
- Spotify mock sem request real.
- Client Credentials com `Http::fake()`.
- OAuth callback, refresh e disconnect.
- Retry policy, cache opt-in, logger estruturado e registry.
- Tela de diagnostico.
- Comando `external-api:make`.
- Views Inertia de artistas e playlists.

## Prompt para criar novo modulo

```text
Adicione um novo modulo em app/Modules/ExternalApi/Modules/{Api}.

Siga a estrutura atual do ExternalApi:
- namespace App\Modules\ExternalApi\Modules\{Api}
- Configuration estendendo AbstractConfiguration
- Http client real estendendo AbstractHttpClient
- MockHttpClient implementando MockHttpClientInterface
- MockData com respostas realistas
- Manager estendendo AbstractApiService
- Services por recurso
- DTOs para respostas estaveis
- Provider proprio registrado na config do registry
- Facade opcional
- Config em app/Modules/ExternalApi/config/external-apis.php dentro de modules.{api}
- Endpoints com cache/rate limit declarados somente quando necessario
- Testes PHPUnit com fake mode e Http::preventStrayRequests

O modulo deve funcionar com dados mockados por config mesmo sem credenciais reais.
Nao use env() fora de config.
Nao crie implementacoes paralelas fora de app/Modules/ExternalApi.
```
