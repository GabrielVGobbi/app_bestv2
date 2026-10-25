<?php

namespace Tests\Feature\ExternalApi;

use App\Modules\ExternalApi\Core\Abstracts\AbstractHttpClient;
use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;
use App\Modules\ExternalApi\Core\Contracts\MockHttpClientInterface;
use App\Modules\ExternalApi\Core\DTOs\BaseResponse;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use App\Modules\ExternalApi\Core\Exceptions\HttpException;
use App\Modules\ExternalApi\Core\Support\ExternalApiRegistry;
use App\Modules\ExternalApi\Modules\Asaas\Configuration\AsaasConfiguration;
use App\Modules\ExternalApi\Modules\Asaas\Http\AsaasHttpClient;
use App\Modules\ExternalApi\Modules\Asaas\Services\AsaasManager;
use App\Modules\ExternalApi\Modules\Iugu\Configuration\IuguConfiguration;
use App\Modules\ExternalApi\Modules\Iugu\Http\IuguHttpClient;
use App\Modules\ExternalApi\Modules\Iugu\Services\IuguManager;
use App\Modules\ExternalApi\Modules\Spotify\Configuration\SpotifyConfiguration;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalApiProviderTest extends TestCase
{
    public function test_external_api_provider_resolves_managers(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
            'external-apis.modules.iugu.fake' => true,
            'external-apis.modules.asaas.fake' => true,
        ]);

        $this->assertInstanceOf(SpotifyManager::class, app(SpotifyManager::class));
        $this->assertInstanceOf(IuguManager::class, app(IuguManager::class));
        $this->assertInstanceOf(AsaasManager::class, app(AsaasManager::class));
        $this->assertInstanceOf(SpotifyManager::class, app('spotify'));
        $this->assertArrayHasKey('spotify', app(ExternalApiRegistry::class)->modules());
    }

    public function test_spotify_oauth_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('external-api.spotify.redirect'));
        $this->assertTrue(Route::has('external-api.spotify.callback'));
        $this->assertTrue(Route::has('external-api.spotify.disconnect'));
        $this->assertTrue(Route::has('external-api.diagnostics'));
    }

    public function test_external_api_install_command_is_registered(): void
    {
        $this->isolateProjectFiles();
        $this->assertFileDoesNotExist(config_path('external-apis.php'));

        $this->artisan('external-api:install')
            ->assertSuccessful();

        $this->assertFileExists(config_path('external-apis.php'));
        file_put_contents(config_path('external-apis.php'), 'preserved fixture');
        $this->artisan('external-api:install')->assertSuccessful();
        $this->assertSame('preserved fixture', file_get_contents(config_path('external-apis.php')));
        $this->artisan('external-api:install --force')->assertSuccessful();
        $this->assertSame(file_get_contents(app_path('Modules/ExternalApi/config/external-apis.php')), file_get_contents(config_path('external-apis.php')));
    }

    public function test_external_api_config_is_loaded(): void
    {
        $this->assertIsArray(config('external-apis.modules.spotify'));
        $this->assertSame('https://api.spotify.com/v1', config('external-apis.modules.spotify.base_url'));
    }

    public function test_spotify_fake_mode_uses_global_default_and_module_override(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => null,
        ]);

        $this->assertTrue(app(SpotifyConfiguration::class)->isFake());

        config([
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
        ]);

        $this->assertFalse(app(SpotifyConfiguration::class)->isFake());
    }

    public function test_spotify_real_mode_requires_credentials(): void
    {
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => '',
            'external-apis.modules.spotify.client_secret' => '',
        ]);

        $this->expectException(ConfigurationException::class);

        app(SpotifyManager::class);
    }

    #[DataProvider('financialFakeOperations')]
    public function test_financial_fake_read_and_creation_refuse_before_any_http(string $module, bool $globalFake, ?bool $moduleFake, string $method): void
    {
        $this->configureFinancial($module, $globalFake, $moduleFake);
        Http::fake([$this->financialBaseUrl($module).'/*' => Http::response(['id' => 'sec018-synthetic'], 200)]);
        $managerClass = $module === 'iugu' ? IuguManager::class : AsaasManager::class;
        $this->assertInstanceOf($managerClass, app($module));
        $this->assertInstanceOf($managerClass, app(ExternalApiRegistry::class)->manager($module));
        $this->assertUnsupportedFake(fn (): array => $this->financialOperation($module, $method));
    }

    /** @return array<string, array{string, bool, ?bool, string}> */
    public static function financialFakeOperations(): array
    {
        $cases = [];
        foreach (['iugu', 'asaas'] as $module) {
            foreach (['global fallback' => [true, null], 'module override' => [false, true]] as $mode => [$global, $override]) {
                foreach (['GET', 'POST'] as $method) {
                    $cases[$module.' '.$mode.' '.$method] = [$module, $global, $override, $method];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('financialModules')]
    public function test_direct_financial_client_refuses_all_inherited_network_verbs_in_fake(string $module): void
    {
        $this->configureFinancial($module, false, true);
        Http::fake([$this->financialBaseUrl($module).'/*' => Http::response(['id' => 'sec018-synthetic'], 200)]);
        $client = $this->directFinancialClient($module);
        foreach (['get', 'post', 'put', 'delete'] as $method) {
            $this->assertUnsupportedFake(fn (): BaseResponse => $client->{$method}('/customers'));
        }
    }

    #[DataProvider('financialModules')]
    public function test_mock_marker_does_not_allow_inherited_raw_transport_or_header_reads(string $module): void
    {
        $this->configureFinancial($module, true, null);
        Http::fake([$this->financialBaseUrl($module).'/*' => Http::response(['id' => 'sec018-synthetic'], 200)]);
        $client = new class($this->financialConfiguration($module)) extends AbstractHttpClient implements MockHttpClientInterface
        {
            public int $headerReads = 0;

            public function inheritedRaw(string $url): BaseResponse
            {
                return $this->rawRequest('POST', $url, ['json' => ['synthetic' => true]]);
            }

            protected function getHeaders(): array
            {
                $this->headerReads++;

                return [];
            }
        };
        $this->assertUnsupportedFake(fn (): BaseResponse => $client->get('/customers'));
        $this->assertUnsupportedFake(fn (): BaseResponse => $client->inheritedRaw($this->financialBaseUrl($module).'/raw?private=sec018-query'));
        $this->assertSame(0, $client->headerReads);
    }

    #[DataProvider('financialModules')]
    public function test_resolved_real_client_rechecks_fake_before_warm_cache_and_recovers_when_real(string $module): void
    {
        $this->configureFinancial($module, true, false);
        config([
            'external-apis.cache.enabled' => true,
            'external-apis.modules.'.$module.'.cache_fake' => true,
            'external-apis.modules.'.$module.'.endpoints.customers.cache.enabled' => true,
            'external-apis.modules.'.$module.'.endpoints.customers.cache.ttl' => 300,
        ]);
        Http::fake([$this->financialBaseUrl($module).'/*' => Http::response(['id' => 'sec018-cached-real'], 200)]);
        $manager = $module === 'iugu' ? app(IuguManager::class) : app(AsaasManager::class);
        $client = $manager->getHttpClient();
        $this->assertSame(['id' => 'sec018-cached-real'], $client->get('/customers')->getData());
        Http::assertSentCount(1);
        config(['external-apis.modules.'.$module.'.fake' => true]);
        $this->assertUnsupportedFake(fn (): BaseResponse => $client->get('/customers'), 1);
        $this->assertUnsupportedFake(fn (): BaseResponse => $client->post('/customers', ['name' => 'Synthetic customer']), 1);
        config(['external-apis.modules.'.$module.'.fake' => false]);
        $this->assertSame(['id' => 'sec018-cached-real'], $client->get('/customers')->getData());
        Http::assertSentCount(1);
    }

    #[DataProvider('financialModules')]
    public function test_explicit_real_override_keeps_headers_http_error_and_recovery(string $module): void
    {
        $this->configureFinancial($module, true, false);
        Http::fake([$this->financialBaseUrl($module).'/*' => Http::sequence()
            ->push(['message' => 'Synthetic upstream failure'], 500)
            ->push(['records' => [['id' => 'sec018-readable']]], 200)
            ->push(['id' => 'sec018-created'], 201)]);
        try {
            $this->financialOperation($module, 'GET');
            $this->fail('The explicit real client must propagate its synthetic upstream failure.');
        } catch (HttpException $exception) {
            $this->assertSame(500, $exception->getCode());
        }
        $this->assertSame(['records' => [['id' => 'sec018-readable']]], $this->financialOperation($module, 'GET'));
        $this->assertSame(['id' => 'sec018-created'], $this->financialOperation($module, 'POST'));
        Http::assertSentCount(3);
        $this->assertSame(['GET', 'GET', 'POST'], Http::recorded()->map(fn (array $pair): string => $pair[0]->method())->all());
        foreach (Http::recorded() as [$request]) {
            $this->assertSame($this->financialBaseUrl($module).'/customers', $request->url());
            $this->assertTrue($request->hasHeader(
                $module === 'iugu' ? 'Authorization' : 'access_token',
                $module === 'iugu' ? 'Basic '.base64_encode('sec018-dummy-key:') : 'sec018-dummy-key',
            ));
        }
    }

    public function test_supported_spotify_mock_still_returns_local_data_without_http(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
            'external-apis.modules.spotify.base_url' => 'https://sec018-spotify.example.test/v1',
            'external-apis.modules.spotify.auth_url' => 'https://sec018-spotify.example.test/auth',
            'external-apis.modules.spotify.client_id' => 'sec018-dummy-client',
            'external-apis.modules.spotify.client_secret' => 'sec018-dummy-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://sec018-spotify.example.test/*' => Http::response([], 200)]);
        $client = app(SpotifyManager::class)->getHttpClient();
        $this->assertInstanceOf(MockHttpClientInterface::class, $client);
        $response = $client->get('/search', ['q' => 'synthetic', 'type' => 'track']);
        $this->assertTrue($response->isSuccessful());
        $this->assertArrayHasKey('tracks', $response->getData());
        $this->assertNotEmpty($response->getData()['tracks']['items']);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function financialModules(): array
    {
        return ['Iugu' => ['iugu'], 'Asaas' => ['asaas']];
    }

    private function configureFinancial(string $module, bool $globalFake, ?bool $moduleFake): void
    {
        config([
            'external-apis.fake.enabled' => $globalFake,
            'external-apis.modules.'.$module.'.fake' => $moduleFake,
            'external-apis.modules.'.$module.'.base_url' => $this->financialBaseUrl($module),
            'external-apis.modules.'.$module.'.api_key' => 'sec018-dummy-key',
            'external-apis.modules.'.$module.'.retry_attempts' => 1,
            'external-apis.modules.'.$module.'.rate_limit' => 100,
            'external-apis.logging.enabled' => true,
            'external-apis.cache.enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    private function financialBaseUrl(string $module): string
    {
        return 'https://sec018-'.$module.'.example.test/v1';
    }

    private function financialConfiguration(string $module): ConfigurationInterface
    {
        return match ($module) {
            'iugu' => app(IuguConfiguration::class),
            'asaas' => app(AsaasConfiguration::class),
            default => throw new LogicException('Unknown financial module fixture.'),
        };
    }

    private function directFinancialClient(string $module): HttpClientInterface
    {
        return match ($module) {
            'iugu' => new IuguHttpClient($this->financialConfiguration($module)),
            'asaas' => new AsaasHttpClient($this->financialConfiguration($module)),
            default => throw new LogicException('Unknown financial module fixture.'),
        };
    }

    /** @return array<string, mixed> */
    private function financialOperation(string $module, string $method): array
    {
        return match ([$module, $method]) {
            ['iugu', 'GET'] => app(IuguManager::class)->customers()->list(),
            ['asaas', 'GET'] => app(AsaasManager::class)->customers()->list(),
            ['iugu', 'POST'] => app(IuguManager::class)->getHttpClient()->post('/customers', ['name' => 'Synthetic customer'])->getData(),
            ['asaas', 'POST'] => app(AsaasManager::class)->customers()->create(['name' => 'Synthetic customer']),
            default => throw new LogicException('Unknown financial operation fixture.'),
        };
    }

    private function assertUnsupportedFake(callable $operation, int $previousRequests = 0): void
    {
        $store = Cache::getStore();
        $this->assertInstanceOf(ArrayStore::class, $store);
        $before = $store->all();
        try {
            $operation();
            $this->fail('An unsupported fake transport must refuse rather than return a real or cached response.');
        } catch (ConfigurationException $exception) {
            $this->assertSame('Fake mode is not supported by this HTTP client.', $exception->getMessage());
        } finally {
            $previousRequests === 0 ? Http::assertNothingSent() : Http::assertSentCount($previousRequests);
            $this->assertSame($before, $store->all(), 'Fake refusal must not change response cache, rate counters or diagnostics.');
        }
    }
}
