<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\TestEnvironment;

class ApiErrorResponsesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->isolateProjectFiles();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
    }

    #[DataProvider('missingApiPaths')]
    public function test_api_root_and_unknown_api_routes_return_json_regardless_of_accept(string $path, ?string $accept): void
    {
        $this->requestAccept($accept);
        $this->assertJsonError($this->get($path), 404);
    }

    /** @return array<string, array{string, string|null}> */
    public static function missingApiPaths(): array
    {
        return [
            'api root JSON' => ['/api', 'application/json'],
            'api root HTML' => ['/api', 'text/html'],
            'api root no Accept' => ['/api', null],
            'unknown API JSON' => ['/api/rel004-missing', 'application/json'],
            'unknown API HTML' => ['/api/rel004-missing', 'text/html'],
            'unknown API no Accept' => ['/api/rel004-missing', null],
        ];
    }

    public function test_api_with_an_inertia_header_still_returns_a_plain_json_error(): void
    {
        $this->withHeaders(['Accept' => 'text/html', 'X-Inertia' => 'true']);
        $this->assertJsonError($this->get('/api/rel004-missing'), 404);
    }

    #[DataProvider('policyRequests')]
    public function test_stateful_api_policy_denials_keep_json_without_changing_authority(string $name, ?string $accept): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $before = $user->refresh()->getAttributes();
        $this->requestAccept($accept);
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost');

        $this->assertJsonError($this->get(route($name)), 403);

        $this->assertSame($before, $user->refresh()->getAttributes());
        $this->assertSame(0, $user->permissions()->count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @return array<string, array{string, string|null}> */
    public static function policyRequests(): array
    {
        return [
            'client policy JSON' => ['api.playlists.index', 'application/json'],
            'client policy HTML' => ['api.playlists.index', 'text/html'],
            'client policy no Accept' => ['api.playlists.index', null],
            'authority JSON' => ['api.sales.index', 'application/json'],
            'authority HTML' => ['api.sales.index', 'text/html'],
            'authority no Accept' => ['api.sales.index', null],
        ];
    }

    #[DataProvider('acceptHeaders')]
    public function test_real_login_throttle_preserves_json_retry_after_and_rate_limit_headers(?string $accept): void
    {
        $payload = ['email' => 'rel004-absent@example.test', 'password' => 'REL004-incorrect-password'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('api.auth.login'), $payload)->assertUnprocessable();
        }
        $this->requestAccept($accept);
        $response = $this->post(route('api.auth.login'), $payload);
        $response->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '5')->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('X-RateLimit-Reset');
        $retryAfter = $response->headers->get('Retry-After');
        $this->assertIsString($retryAfter);
        $this->assertMatchesRegularExpression('/\A[0-9]+\z/', $retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(60, (int) $retryAfter);
        $this->assertJsonError($response, 429);
        $response->assertDontSee('REL004-incorrect-password');
        $this->travel(61)->seconds();
        $this->postJson(route('api.auth.login'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('email')->assertHeader('X-RateLimit-Remaining', '4')
            ->assertHeaderMissing('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('acceptHeaders')]
    public function test_actual_method_not_allowed_response_keeps_allow_header_and_json(?string $accept): void
    {
        $this->requestAccept($accept);
        $response = $this->get(route('api.auth.login'));
        $response->assertMethodNotAllowed()->assertHeader('Allow', 'POST');
        $this->assertJsonError($response, 405);
    }

    /** @return array<string, array{string|null}> */
    public static function acceptHeaders(): array
    {
        return ['JSON' => ['application/json'], 'HTML' => ['text/html'], 'no Accept' => [null]];
    }

    public function test_existing_api_validation_response_keeps_json_and_field_errors(): void
    {
        $response = $this->postJson(route('api.auth.login'), []);
        $this->assertJsonError($response, 422);
        $response->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_guest_authentication_response_keeps_json_and_401(): void
    {
        $this->assertJsonError($this->getJson(route('api.auth.me')), 401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_web_html_policy_denial_keeps_the_inertia_error_page(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]), 'web');
        $this->withHeader('Accept', 'text/html')->get(route('playlists.index'))
            ->assertForbidden()->assertHeaderContains('Content-Type', 'text/html')
            ->assertHeaderMissing('X-Inertia')
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    public function test_web_inertia_navigation_keeps_the_error_component_status_and_protocol_header(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]), 'web');
        $this->withHeaders(['Accept' => 'text/html', 'X-Inertia' => 'true'])->get(route('playlists.index'))
            ->assertForbidden()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'error')->assertJsonPath('props.status', 403)
            ->assertJsonMissingPath('props.trace');
    }

    public function test_web_request_explicitly_accepting_json_receives_a_json_policy_error(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]), 'web');
        $this->assertJsonError($this->getJson(route('playlists.index')), 403);
    }

    #[DataProvider('webHeaderChannels')]
    public function test_web_error_rendering_preserves_headers_from_the_http_exception(bool $inertia): void
    {
        Route::middleware('web')->get('/rel004-header-fixture', function (): never {
            throw new HttpException(503, 'Synthetic maintenance fixture.', null, [
                'Retry-After' => '17', 'X-REL004-Reason' => 'synthetic-maintenance',
            ]);
        });
        $this->withHeader('Accept', 'text/html');
        if ($inertia) {
            $this->withHeader('X-Inertia', 'true');
        }
        $response = $this->get('/rel004-header-fixture');
        $response->assertStatus(503)->assertHeader('Retry-After', '17')
            ->assertHeader('X-REL004-Reason', 'synthetic-maintenance');
        if ($inertia) {
            $response->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'error')->assertJsonPath('props.status', 503);
        } else {
            $response->assertHeaderContains('Content-Type', 'text/html')
                ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 503));
        }
    }

    /** @return array<string, array{bool}> */
    public static function webHeaderChannels(): array
    {
        return ['HTML document' => [false], 'Inertia navigation' => [true]];
    }

    public function test_public_ping_and_signed_webhook_keep_their_status_and_signature_contract(): void
    {
        $this->getJson('/api/ping')->assertOk();
        config(['services.postmark.webhook_token' => 'rel004-synthetic-webhook-token']);
        $this->assertJsonError($this->postJson(route('email.webhook', ['provider' => 'postmark']), ['RecordType' => 'Delivery']), 401);
        $this->withHeader('X-Postmark-Webhook-Token', 'rel004-synthetic-webhook-token')
            ->postJson(route('email.webhook', ['provider' => 'postmark']), ['RecordType' => 'Delivery'])->assertOk();
        Http::assertNothingSent();
    }

    private function requestAccept(?string $accept): void
    {
        $this->flushHeaders();
        $this->withServerVariables(['HTTP_ACCEPT' => $accept]);
        if ($accept !== null) {
            $this->withHeader('Accept', $accept);
        }
    }

    private function assertJsonError(TestResponse $response, int $status): void
    {
        $response->assertStatus($status)->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('X-Inertia')->assertHeaderMissing('Location')
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPaths(['component', 'props', 'exception', 'trace', 'file', 'line', 'access_token']);
        $this->assertIsString($response->json('message'));
        $this->assertNotSame('', $response->json('message'));
    }
}
