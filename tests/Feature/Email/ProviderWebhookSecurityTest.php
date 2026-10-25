<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestCase;
use Tests\TestEnvironment;

class ProviderWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const POSTMARK_TOKEN = 'SEC009_SYNTHETIC_POSTMARK_TOKEN';

    /** @var list<string> */
    private array $emailQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->isolateProjectFiles();
        $this->travelTo('2026-10-16 12:00:00 UTC');
        config([
            'services.postmark.webhook_token' => self::POSTMARK_TOKEN,
            'services.resend.webhook_secret' => SvixMessageFixture::secret(),
        ]);
        Http::preventStrayRequests();
    }

    /** @param array<string, string> $headers */
    private function deliver(string $provider, string $raw, array $headers = []): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/api/email/webhook/'.$provider, [], [], [], $server, $raw);
    }

    private function resendRaw(): string
    {
        return SvixMessageFixture::raw(['type' => 'email.delivered', 'data' => ['email_id' => 'sec009-message']]);
    }

    private function postmarkRaw(): string
    {
        return SvixMessageFixture::raw(['RecordType' => 'Delivery', 'MessageID' => 'sec009-message']);
    }

    /** @return array{log: EmailLog, attributes: array<string, mixed>, events: array<int, array<string, mixed>>} */
    private function observeEffects(): array
    {
        $log = EmailLog::factory()->create([
            'status' => 'sent', 'message_id' => 'sec009-message', 'metadata' => ['sentinel' => 'unchanged'],
        ])->refresh();
        $baseline = ['log' => $log, 'attributes' => $log->getAttributes(), 'events' => DB::table('email_webhook_events')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
        DB::listen(function ($query): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'email_logs') || str_contains($sql, 'email_webhook_events')) {
                $this->emailQueries[] = $query->sql;
            }
        });

        return $baseline;
    }

    /** @param array{log: EmailLog, attributes: array<string, mixed>, events: array<int, array<string, mixed>>} $baseline */
    private function assertNoEffects(array $baseline): void
    {
        $this->assertSame([], $this->emailQueries, 'Authentication/structure refusal must precede email queries.');
        $this->assertSame($baseline['attributes'], $baseline['log']->fresh()->getAttributes());
        $this->assertSame($baseline['events'], DB::table('email_webhook_events')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        Http::assertNothingSent();
    }

    public function test_postmark_configuration_wires_its_own_environment_token_without_api_key_fallback(): void
    {
        TestEnvironment::assertSafeEnvironment();
        $property = new ReflectionProperty(Env::class, 'repository');
        $original = $property->getValue();
        $repository = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();
        $repository->set('POSTMARK_API_KEY', 'SYNTHETIC_API_KEY_MUST_NOT_AUTHENTICATE');
        $repository->set('POSTMARK_WEBHOOK_TOKEN', self::POSTMARK_TOKEN);
        $source = dirname(__DIR__, 3).'/config/services.php';
        $this->assertFileExists($source);

        try {
            $property->setValue(null, $repository);
            $services = require $source;
            $this->assertSame(self::POSTMARK_TOKEN, $services['postmark']['webhook_token'] ?? null);
            $this->assertSame('SYNTHETIC_API_KEY_MUST_NOT_AUTHENTICATE', $services['postmark']['key'] ?? null);
            $repository->clear('POSTMARK_WEBHOOK_TOKEN');
            $services = require $source;
            $this->assertNull($services['postmark']['webhook_token'] ?? null);
        } finally {
            $property->setValue(null, $original);
        }
        $this->assertSame($original, $property->getValue());
    }

    /** @return array<string, array{mixed}> */
    public static function invalidPostmarkConfiguration(): array
    {
        return ['absent' => [null], 'empty' => [''], 'blank' => ['  '], 'boolean' => [false], 'integer' => [123], 'array' => [[]]];
    }

    #[DataProvider('invalidPostmarkConfiguration')]
    public function test_postmark_configuration_absent_or_invalid_is_denied_without_effects(mixed $token): void
    {
        $baseline = $this->observeEffects();
        config(['services.postmark.webhook_token' => $token]);
        $this->deliver('postmark', $this->postmarkRaw(), ['X-Postmark-Webhook-Token' => self::POSTMARK_TOKEN])->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function invalidPostmarkHeaders(): array
    {
        return [
            'absent' => [[]], 'empty' => [['X-Postmark-Webhook-Token' => '']],
            'blank' => [['X-Postmark-Webhook-Token' => ' ']], 'wrong' => [['X-Postmark-Webhook-Token' => 'wrong']],
            'padded token' => [['X-Postmark-Webhook-Token' => ' '.self::POSTMARK_TOKEN.' ']],
            'API credential header' => [['X-Postmark-Server-Token' => self::POSTMARK_TOKEN]],
        ];
    }

    #[DataProvider('invalidPostmarkHeaders')]
    public function test_postmark_missing_or_wrong_header_is_denied_without_effects(array $headers): void
    {
        $baseline = $this->observeEffects();
        $this->deliver('postmark', $this->postmarkRaw(), $headers)->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }

    /** @return array<string, array{string, string}> */
    public static function postmarkEvents(): array
    {
        return ['delivery' => ['Delivery', 'delivered'], 'bounce' => ['Bounce', 'bounced'], 'complaint' => ['SpamComplaint', 'bounced']];
    }

    #[DataProvider('postmarkEvents')]
    public function test_postmark_authenticated_event_keeps_mapping_and_returns_empty_200(string $type, string $status): void
    {
        $log = EmailLog::factory()->create(['status' => 'sent', 'message_id' => 'sec009-message']);
        $raw = SvixMessageFixture::raw(['RecordType' => $type, 'MessageID' => $log->message_id, 'Description' => "  ação\nlinha  "]);
        $response = $this->deliver('postmark', $raw, ['X-Postmark-Webhook-Token' => self::POSTMARK_TOKEN])->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertSame($status, $log->fresh()->status->value);
        $this->assertSame("  ação\nlinha  ", $log->fresh()->metadata['Description']);
        Http::assertNothingSent();
    }

    public function test_postmark_missing_configuration_can_recover_without_reusing_a_bypass(): void
    {
        $baseline = $this->observeEffects();
        config(['services.postmark.webhook_token' => null]);
        $headers = ['X-Postmark-Webhook-Token' => self::POSTMARK_TOKEN];
        $this->deliver('postmark', $this->postmarkRaw(), $headers)->assertUnauthorized();
        $this->assertNoEffects($baseline);

        config(['services.postmark.webhook_token' => self::POSTMARK_TOKEN]);
        $this->deliver('postmark', $this->postmarkRaw(), $headers)->assertOk();
        $this->assertSame('delivered', $baseline['log']->fresh()->status->value);
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidResendConfiguration(): array
    {
        return [
            'absent' => [null, SvixMessageFixture::KEY], 'empty' => ['', SvixMessageFixture::KEY],
            'blank' => [' ', ''], 'boolean' => [false, SvixMessageFixture::KEY], 'integer' => [123, SvixMessageFixture::KEY],
            'array' => [[], SvixMessageFixture::KEY], 'empty decoded key' => ['whsec_', ''],
            'invalid decoded key' => ['whsec_%%%', ''], 'invalid padding' => ['whsec_c2VjcmV0====', 'secret'],
            'embedded whitespace' => ['whsec_c2Vj cmV0', 'secret'], 'embedded prefix' => ['c2Vjwhsec_cmV0', 'secret'],
        ];
    }

    #[DataProvider('invalidResendConfiguration')]
    public function test_resend_configuration_absent_or_malformed_is_denied_without_effects(mixed $secret, string $signingKey): void
    {
        $baseline = $this->observeEffects();
        config(['services.resend.webhook_secret' => $secret]);
        $raw = $this->resendRaw();
        $this->deliver('resend', $raw, SvixMessageFixture::headers($raw, 'evt-sec009', (string) now()->timestamp, $signingKey))->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }

    /** @return array<string, array{string, ?string}> */
    public static function invalidResendHeaders(): array
    {
        return [
            'missing id' => ['svix-id', null], 'empty id' => ['svix-id', ''],
            'missing timestamp' => ['svix-timestamp', null], 'empty timestamp' => ['svix-timestamp', ''],
            'missing signature' => ['svix-signature', null], 'empty signature' => ['svix-signature', ''],
            'wrong id' => ['svix-id', 'evt-other'], 'wrong signature' => ['svix-signature', 'v1,invalid'],
            'unsupported version' => ['svix-signature', 'v2,invalid'],
        ];
    }

    #[DataProvider('invalidResendHeaders')]
    public function test_resend_headers_missing_or_mismatching_are_denied_without_effects(string $header, ?string $value): void
    {
        $baseline = $this->observeEffects();
        $raw = $this->resendRaw();
        $headers = SvixMessageFixture::headers($raw, 'evt-sec009', (string) now()->timestamp);
        if ($value === null) {
            unset($headers[$header]);
        } else {
            $headers[$header] = $value;
        }
        $this->deliver('resend', $raw, $headers)->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }

    /** @return array<string, array{int, bool}> */
    public static function timestampWindow(): array
    {
        return ['old boundary' => [-300, true], 'current' => [0, true], 'future boundary' => [300, true], 'old outside' => [-301, false], 'future outside' => [301, false]];
    }

    #[DataProvider('timestampWindow')]
    public function test_resend_timestamp_window_is_inclusive_and_refuses_outside_attempts(int $offset, bool $accepted): void
    {
        $baseline = $this->observeEffects();
        $raw = $this->resendRaw();
        $response = $this->deliver('resend', $raw, SvixMessageFixture::headers($raw, 'evt-sec009', (string) (now()->timestamp + $offset)));
        if (! $accepted) {
            $response->assertUnauthorized();
            $this->assertNoEffects($baseline);

            return;
        }
        $response->assertNoContent();
        $this->assertSame('delivered', $baseline['log']->fresh()->status->value);
        $this->assertSame(1, EmailWebhookEvent::where('event_id', 'evt-sec009')->count());
    }

    /** @return array<string, array{string}> */
    public static function noncanonicalTimestamps(): array
    {
        return ['zero' => ['0'], 'negative' => ['-1'], 'plus sign' => ['+1792152000'], 'leading zero' => ['01792152000'], 'fraction' => ['1792152000.0'], 'exponent' => ['1792152e3'], 'whitespace' => [' 1792152000 '], 'overflow' => ['999999999999999999999999999999']];
    }

    #[DataProvider('noncanonicalTimestamps')]
    public function test_resend_noncanonical_timestamp_is_denied_even_with_matching_hmac(string $timestamp): void
    {
        $baseline = $this->observeEffects();
        $raw = $this->resendRaw();
        $this->deliver('resend', $raw, SvixMessageFixture::headers($raw, 'evt-sec009', $timestamp))->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }

    /** @return array<string, array{bool, bool}> */
    public static function signingSecretFormatsAndRotation(): array
    {
        return ['prefix valid first' => [true, true], 'prefix valid last' => [true, false], 'bare valid first' => [false, true], 'bare valid last' => [false, false]];
    }

    #[DataProvider('signingSecretFormatsAndRotation')]
    public function test_resend_multiple_signatures_support_rotation_and_legacy_bare_secret(bool $prefix, bool $validFirst): void
    {
        $log = EmailLog::factory()->create(['status' => 'sent', 'message_id' => 'sec009-message']);
        config(['services.resend.webhook_secret' => $prefix ? SvixMessageFixture::secret() : base64_encode(SvixMessageFixture::KEY)]);
        $raw = $this->resendRaw();
        $headers = SvixMessageFixture::headers($raw, 'evt-rotation', (string) now()->timestamp);
        $other = SvixMessageFixture::signature($raw, 'evt-rotation', (string) now()->timestamp, 'SYNTHETIC_OLD_OR_NEW_KEY');
        $headers['svix-signature'] = implode(' ', $validFirst ? [$headers['svix-signature'], $other, 'v2,ignored'] : [$other, 'v2,ignored', $headers['svix-signature']]);
        $this->deliver('resend', $raw, $headers)->assertNoContent();
        $this->assertSame('delivered', $log->fresh()->status->value);
    }

    public function test_resend_wrong_key_or_raw_tamper_is_denied_and_next_valid_request_recovers(): void
    {
        $baseline = $this->observeEffects();
        $raw = $this->resendRaw();
        $this->deliver('resend', $raw, SvixMessageFixture::headers($raw, 'evt-sec009', (string) now()->timestamp, 'SYNTHETIC_WRONG_KEY'))->assertUnauthorized();
        $this->assertSame([], $this->emailQueries);
        $headers = SvixMessageFixture::headers($raw, 'evt-sec009', (string) now()->timestamp);
        $this->deliver('resend', $raw.' ', $headers)->assertUnauthorized();
        $this->assertNoEffects($baseline);
        $this->deliver('resend', $raw, $headers)->assertNoContent();
        $this->assertSame('delivered', $baseline['log']->fresh()->status->value);
        $this->assertSame(1, EmailWebhookEvent::where('event_id', 'evt-sec009')->count());
    }

    public function test_resend_raw_utf8_metadata_and_persisted_body_are_not_transformed(): void
    {
        $log = EmailLog::factory()->create(['status' => 'sent', 'message_id' => 'sec009-message']);
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => $log->message_id, 'detail' => "  ação\nlinha  ", 'optional' => '']];
        $raw = " \n".SvixMessageFixture::raw($payload)."\n ";
        $this->deliver('resend', $raw, SvixMessageFixture::headers($raw, 'evt-raw', (string) now()->timestamp))->assertNoContent();
        $event = EmailWebhookEvent::where('event_id', 'evt-raw')->firstOrFail();
        $this->assertSame($raw, $event->raw_payload);
        $this->assertSame($payload, $event->payload);
        $this->assertSame($payload['data'], $log->fresh()->metadata);
    }

    public function test_svix_official_signature_vector_is_verified_independently_of_fixture_signer(): void
    {
        $raw = '{"event_type":"ping","data":{"success":true}}';
        $id = 'msg_loFOjxBNrRLzqYUf';
        $timestamp = '1731705121';
        $signature = 'v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=';
        $key = base64_decode('plJ3nmyCDGBKInavdOK15jsl', true);
        $this->assertIsString($key);
        $this->assertSame($signature, SvixMessageFixture::signature($raw, $id, $timestamp, $key));
        $this->travelTo(Carbon::createFromTimestampUTC((int) $timestamp));
        config(['services.resend.webhook_secret' => 'whsec_plJ3nmyCDGBKInavdOK15jsl']);

        $this->deliver('resend', $raw, ['svix-id' => $id, 'svix-timestamp' => $timestamp, 'svix-signature' => $signature])->assertNoContent();
        $event = EmailWebhookEvent::where('event_id', $id)->firstOrFail();
        $this->assertSame($raw, $event->raw_payload);
    }

    /** @return array<string, array{string, string}> */
    public static function literalIdentifiers(): array
    {
        return ['resend type' => ['resend', 'type'], 'resend id' => ['resend', 'id'], 'postmark type' => ['postmark', 'type'], 'postmark id' => ['postmark', 'id']];
    }

    #[DataProvider('literalIdentifiers')]
    public function test_raw_literal_event_type_and_message_id_are_not_trimmed_into_another_event(string $provider, string $field): void
    {
        $log = EmailLog::factory()->create(['status' => 'sent', 'message_id' => 'sec009-message'])->refresh();
        $before = $log->getAttributes();
        $payload = $provider === 'resend' ? ['type' => 'email.delivered', 'data' => ['email_id' => $log->message_id]] : ['RecordType' => 'Delivery', 'MessageID' => $log->message_id];
        if ($provider === 'resend') {
            if ($field === 'type') {
                $payload['type'] = ' email.delivered ';
            } else {
                $payload['data']['email_id'] = ' '.$log->message_id.' ';
            }
        } else {
            $payload[$field === 'type' ? 'RecordType' : 'MessageID'] = ' '.$payload[$field === 'type' ? 'RecordType' : 'MessageID'].' ';
        }
        $raw = SvixMessageFixture::raw($payload);
        $headers = $provider === 'resend' ? SvixMessageFixture::headers($raw, 'evt-literal', (string) now()->timestamp) : ['X-Postmark-Webhook-Token' => self::POSTMARK_TOKEN];
        $response = $this->deliver($provider, $raw, $headers);
        $provider === 'resend' ? $response->assertNoContent() : $response->assertOk();
        $this->assertSame($before, $log->fresh()->getAttributes());
        if ($provider === 'resend') {
            $this->assertSame($payload, EmailWebhookEvent::where('event_id', 'evt-literal')->firstOrFail()->payload);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function malformedBodies(): array
    {
        return ['resend invalid' => ['resend', '{invalid'], 'resend list' => ['resend', '[]'], 'resend null' => ['resend', 'null'], 'postmark invalid' => ['postmark', '{invalid'], 'postmark list' => ['postmark', '[]'], 'postmark null' => ['postmark', 'null']];
    }

    #[DataProvider('malformedBodies')]
    public function test_authenticated_nonobject_or_invalid_json_is_refused_before_email_queries(string $provider, string $raw): void
    {
        $baseline = $this->observeEffects();
        $headers = $provider === 'resend' ? SvixMessageFixture::headers($raw, 'evt-malformed', (string) now()->timestamp) : ['X-Postmark-Webhook-Token' => self::POSTMARK_TOKEN];
        $this->deliver($provider, $raw, $headers)->assertUnauthorized();
        $this->assertNoEffects($baseline);
    }
}
