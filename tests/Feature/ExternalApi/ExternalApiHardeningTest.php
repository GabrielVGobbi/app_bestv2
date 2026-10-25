<?php

namespace Tests\Feature\ExternalApi;

use App\Models\User;
use App\Modules\ExternalApi\Core\Enums\ExternalApiMode;
use App\Modules\ExternalApi\Core\Support\ExternalApiRegistry;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyAlbum;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyPlaylist;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyTrack;
use App\Modules\ExternalApi\Modules\Spotify\Mocks\SpotifyMockData;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
    }

    public function test_registry_resolves_configured_modules(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        $registry = app(ExternalApiRegistry::class);
        $spotify = $registry->module('spotify');

        $this->assertArrayHasKey('spotify', $registry->modules());
        $this->assertSame('spotify', $spotify->key);
        $this->assertSame(ExternalApiMode::Fake, $spotify->mode);
        $this->assertInstanceOf(SpotifyManager::class, $registry->manager('spotify'));
    }

    public function test_diagnostics_route_renders_registered_modules(): void
    {
        $this->assertTrue(Route::has('external-api.diagnostics'));

        $user = $this->diagnosticsActor('developer');

        $this->actingAs($user)
            ->get(route('external-api.diagnostics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/external-api/diagnostics')
                ->has('modules')
                ->has('recentRequests')
                ->where('global.fake', true));
    }

    public function test_diagnostics_guests_cannot_read_global_history(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->get(route('external-api.diagnostics'))->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    #[DataProvider('deniedDiagnosticsRoles')]
    public function test_diagnostics_denies_non_developers_before_connection_queries_and_diagnostics_flag(?string $slug): void
    {
        $actor = $this->diagnosticsActor($slug);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $history = [['module' => 'spotify', 'body' => 'OLD_HISTORY_SECRET_MARKER']];
        Cache::put('external-api:diagnostics:recent-requests', $history);
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        try {
            $this->actingAs($actor)->get(route('external-api.diagnostics'))->assertForbidden()->assertDontSee('OLD_HISTORY_SECRET_MARKER');
            config(['external-apis.diagnostics.enabled' => false]);
            $this->get(route('external-api.diagnostics'))->assertForbidden();
            $queries = DB::connection()->getQueryLog();
        } finally {
            DB::connection()->disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('external_api_connections', strtolower($query['query']));
        }
        $this->assertSame($history, Cache::get('external-api:diagnostics:recent-requests'));
        Http::assertNothingSent();
    }

    public static function deniedDiagnosticsRoles(): array
    {
        return ['no role' => [null], 'user' => ['user'], 'admin' => ['admin'], 'super-admin' => ['super-admin'], 'display alias' => ['Developer']];
    }

    #[DataProvider('diagnosticsTokenModes')]
    public function test_diagnostics_rejects_developer_pat_and_session_bearer(string $mode): void
    {
        $actor = $this->diagnosticsActor('developer');
        $token = $actor->createToken('Synthetic diagnostics token', ['*', 'manageIntegrationSettings']);
        if ($mode === 'attached') {
            $actor->withAccessToken($token->accessToken);
        } else {
            $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);
        }
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->actingAs($actor, 'web')->get(route('external-api.diagnostics'))->assertForbidden();
        Http::assertNothingSent();
    }

    public static function diagnosticsTokenModes(): array
    {
        return ['PAT attached' => ['attached'], 'session and bearer' => ['bearer']];
    }

    public function test_diagnostics_developer_session_is_available_in_production_but_disabled_feature_stays_404(): void
    {
        $actor = $this->diagnosticsActor('developer');
        $this->attachSessionToken($actor, new TransientToken);
        $this->app->instance('env', 'production');
        config(['app.env' => 'production']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->actingAs($actor, 'web')->get(route('external-api.diagnostics'))->assertOk();
        config(['external-apis.diagnostics.enabled' => false]);
        $this->get(route('external-api.diagnostics'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_diagnostics_does_not_accept_a_custom_super_role_as_developer(): void
    {
        $actor = $this->diagnosticsActor('developer');
        config(['permissions.super_role' => 'admin']);
        $this->actingAs($actor)->get(route('external-api.diagnostics'))->assertForbidden();
    }

    public function test_diagnostics_projects_known_operational_configuration_and_boolean_credentials_only(): void
    {
        $actor = $this->diagnosticsActor('developer');
        $spotify = config('external-apis.modules.spotify');
        $this->assertIsArray($spotify);
        $spotify['api_key'] = 'CREDENTIAL_SECRET_MARKER';
        $spotify['client_id'] = '';
        $spotify['client_secret'] = 'CLIENT_SECRET_MARKER';
        $spotify['redirect_uri'] = null;
        $spotify['timeout'] = 30;
        $spotify['connect_timeout'] = 10;
        $spotify['retry_attempts'] = 2;
        $spotify['rate_limit'] = 100;
        $spotify['endpoints'] = [
            'search' => [
                'cache' => ['enabled' => true, 'ttl' => 60, 'extra' => 'ENDPOINT_CACHE_SECRET_MARKER'],
                'rate_limit' => 10,
                'headers' => ['Authorization' => 'ENDPOINT_HEADER_SECRET_MARKER'],
                'arbitrary' => ['nested' => 'ENDPOINT_BODY_SECRET_MARKER'],
            ],
            'https://example.test/ENDPOINT_NAME_SECRET_MARKER' => ['cache' => ['enabled' => true]],
        ];
        config([
            'external-apis.modules' => ['spotify' => $spotify],
            'external-apis.cache' => ['enabled' => false, 'default_ttl' => 45, 'driver' => 'array', 'recent_requests_limit' => 7, 'extra' => 'GLOBAL_CACHE_SECRET_MARKER'],
            'external-apis.retry' => ['base_delay_ms' => 25, 'max_delay_ms' => 100, 'jitter_ms' => 0, 'retryable_statuses' => [429, 503, 'GLOBAL_STATUS_SECRET_MARKER'], 'extra' => ['nested' => 'GLOBAL_RETRY_SECRET_MARKER']],
            'external-apis.rate_limit' => ['decay_seconds' => 60, 'lock_seconds' => 3, 'extra' => 'GLOBAL_RATE_SECRET_MARKER'],
        ]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->actingAs($actor)->get(route('external-api.diagnostics'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('app/external-api/diagnostics')->has('modules', 1)
                ->where('modules.0.key', 'spotify')->where('modules.0.connections_count', 0)
                ->where('modules.0.credentials', ['api_key' => true, 'client_id' => false, 'client_secret' => true, 'redirect_uri' => false])
                ->where('modules.0.settings', [
                    'timeout' => 30, 'connect_timeout' => 10, 'retry_attempts' => 2, 'rate_limit' => 100,
                    'endpoints' => ['search' => ['cache' => ['enabled' => true, 'ttl' => 60], 'rate_limit' => 10]],
                ])
                ->where('global.cache', ['enabled' => false, 'default_ttl' => 45, 'driver' => 'array', 'recent_requests_limit' => 7])
                ->where('global.retry', ['base_delay_ms' => 25, 'max_delay_ms' => 100, 'jitter_ms' => 0, 'retryable_statuses' => [429, 503]])
                ->where('global.rate_limit', ['decay_seconds' => 60, 'lock_seconds' => 3]))
            ->assertDontSee('SECRET_MARKER');
        Http::assertNothingSent();
    }

    public function test_diagnostics_does_not_serialize_secret_containers_in_known_operational_fields(): void
    {
        $actor = $this->diagnosticsActor('developer');
        config([
            'external-apis.modules.spotify.timeout' => ['secret' => 'TIMEOUT_SECRET_MARKER'],
            'external-apis.modules.spotify.endpoints.search.cache.ttl' => ['secret' => 'TTL_SECRET_MARKER'],
            'external-apis.cache.default_ttl' => ['secret' => 'CACHE_SECRET_MARKER'],
            'external-apis.retry.base_delay_ms' => ['secret' => 'RETRY_SECRET_MARKER'],
            'external-apis.rate_limit.lock_seconds' => ['secret' => 'RATE_SECRET_MARKER'],
        ]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->actingAs($actor)->get(route('external-api.diagnostics'))->assertOk()->assertDontSee('SECRET_MARKER');
        Http::assertNothingSent();
    }

    /** Sanctum's native untyped setter accepts session tokens as well as personal tokens. */
    private function attachSessionToken(User $actor, mixed $token): void
    {
        $actor->withAccessToken($token);
    }

    private function diagnosticsActor(?string $slug): User
    {
        $actor = User::factory()->create();
        if ($slug !== null) {
            $role = Role::create(['name' => 'Developer', 'slug' => 'fixture-diagnostics']);
            DB::table('roles')->where('id', $role->getKey())->update(['slug' => $slug]);
            $actor->roles()->attach($role);
        }

        return $actor;
    }

    public function test_endpoint_cache_is_opt_in_for_successful_get_requests(): void
    {
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.cache.enabled' => true,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
            'external-apis.modules.spotify.endpoints.search.cache.enabled' => true,
            'external-apis.modules.spotify.endpoints.search.cache.ttl' => 60,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(SpotifyMockData::token()),
            'https://api.spotify.com/v1/search*' => Http::response(SpotifyMockData::search('track')),
        ]);

        $spotify = app(SpotifyManager::class);

        $spotify->search()->tracks('Daft Punk');
        $spotify->search()->tracks('Daft Punk');

        Http::assertSentCount(2);
    }

    public function test_retry_policy_retries_retryable_http_statuses(): void
    {
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.cache.enabled' => false,
            'external-apis.retry.jitter_ms' => 0,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
            'external-apis.modules.spotify.retry_attempts' => 2,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(SpotifyMockData::token()),
            'https://api.spotify.com/v1/search*' => Http::sequence()
                ->push(['error' => ['message' => 'temporary']], 500)
                ->push(SpotifyMockData::search('track'), 200),
        ]);

        $tracks = app(SpotifyManager::class)->search()->tracks('Daft Punk');

        $this->assertCount(1, $tracks->items);
        Http::assertSentCount(3);
    }

    public function test_structured_request_log_redacts_sensitive_values(): void
    {
        Cache::forget('external-api:diagnostics:recent-requests');

        $configuration = app(SpotifyManager::class)->getConfiguration();

        app(ExternalApiRequestLogger::class)->record($configuration, [
            'method' => 'GET',
            'endpoint' => '/me',
            'status' => 'success',
            'authorization' => 'Bearer secret-token',
            'client_secret' => 'super-secret',
        ]);

        $recent = app(ExternalApiRequestLogger::class)->recent();

        $this->assertArrayNotHasKey('authorization', $recent[0]);
        $this->assertArrayNotHasKey('client_secret', $recent[0]);
        $this->assertStringNotContainsString('secret-token', json_encode($recent, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('super-secret', json_encode($recent, JSON_THROW_ON_ERROR));
    }

    public function test_spotify_dtos_tolerate_missing_and_null_nested_values(): void
    {
        $track = SpotifyTrack::fromArray([
            'id' => 'track',
            'name' => 'Track',
            'artists' => [null, ['id' => 'artist', 'name' => 'Artist']],
            'album' => [
                'id' => 'album',
                'name' => 'Album',
                'artists' => [null],
                'images' => [null, ['url' => 'https://example.test/image.jpg']],
            ],
        ]);

        $playlist = SpotifyPlaylist::fromArray([
            'id' => 'playlist',
            'name' => 'Playlist',
            'images' => [null, ['url' => 'https://example.test/playlist.jpg']],
            'tracks' => null,
        ]);

        $album = SpotifyAlbum::fromArray([
            'id' => 'album',
            'name' => 'Album',
            'artists' => [null, ['id' => 'artist', 'name' => 'Artist']],
        ]);

        $this->assertCount(1, $track->artists);
        $this->assertCount(0, $track->album->artists);
        $this->assertSame(0, $playlist->tracksTotal);
        $this->assertCount(1, $playlist->images);
        $this->assertCount(1, $album->artists);
    }

    public function test_scaffold_command_generates_core_files_and_tests(): void
    {
        $facade = app_path('Modules/ExternalApi/Facades/DemoApi.php');
        File::ensureDirectoryExists(dirname($facade));
        File::put($facade, 'existing fixture');

        $this->artisan('external-api:make DemoApi --force')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Modules/ExternalApi/Modules/DemoApi/Providers/DemoApiServiceProvider.php'));
        $this->assertFileExists(app_path('Modules/ExternalApi/Modules/DemoApi/Services/DemoApiManager.php'));
        $this->assertFileExists(app_path('Modules/ExternalApi/Modules/DemoApi/Http/DemoApiMockHttpClient.php'));
        $this->assertFileExists(app_path('Modules/ExternalApi/Facades/DemoApi.php'));
        $this->assertFileExists(base_path('tests/Feature/ExternalApi/DemoApiTest.php'));
        $this->assertStringContainsString('class DemoApi extends Facade', File::get($facade));
    }

    public function test_scaffold_preserves_existing_files_without_force(): void
    {
        $this->artisan('external-api:make DemoApi')->assertSuccessful();
        $facade = app_path('Modules/ExternalApi/Facades/DemoApi.php');
        File::put($facade, 'preserved fixture');

        $this->artisan('external-api:make DemoApi')->assertSuccessful();

        $this->assertSame('preserved fixture', File::get($facade));
    }
}
