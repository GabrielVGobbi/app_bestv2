<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Webhooks\SesWebhookHandler;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Fixtures\Email\SnsMessageFixture;
use Tests\TestCase;
use Tests\TestEnvironment;

class SesWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private SnsMessageFixture $fixture;

    /** @var list<array{url: string, method: string, options: array<string, mixed>}> */
    private array $outbound = [];

    /** @var list<string> */
    private array $emailQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->isolateProjectFiles();
        $this->fixture = SnsMessageFixture::create($this->testEnvironment->path);
        config(['services.ses.allowed_topic_arns' => [SnsMessageFixture::TOPIC_ARN]]);
        SnsMessageFixture::bindResolver($this->app);
        $this->fakeTransport();
    }

    private function fakeTransport(?\Closure $response = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request, array $options) use ($response) {
            $this->outbound[] = ['url' => $request->url(), 'method' => $request->method(), 'options' => $options];
            if ($response !== null) {
                return $response($request, $options);
            }

            return $request->url() === SnsMessageFixture::CERT_URL
                ? Http::response($this->fixture->certificate(), 200)
                : Http::response('<ConfirmSubscriptionResponse/>', 200);
        });
    }

    /** @param array<string, mixed> $payload */
    private function deliver(array $payload, string $contentType = 'text/plain; charset=UTF-8'): TestResponse
    {
        return $this->call('POST', '/api/email/webhook/ses', [], [], [], [
            'CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json',
        ], SnsMessageFixture::raw($payload));
    }

    private function sentLog(): EmailLog
    {
        return EmailLog::factory()->create(['status' => 'sent', 'message_id' => 'ses-fixture-message', 'metadata' => ['sentinel' => 'unchanged']])->refresh();
    }

    private function recordEmailQueries(): void
    {
        DB::listen(function ($query): void {
            if (str_contains(strtolower($query->sql), 'email_logs')) {
                $this->emailQueries[] = $query->sql;
            }
        });
    }

    /** @return array<string, array{string, string}> */
    public static function signaturesAndContentTypes(): array
    {
        return [
            'SHA1 text/plain' => ['1', 'text/plain; charset=UTF-8'],
            'SHA256 text/plain' => ['2', 'text/plain; charset=UTF-8'],
            'SHA1 JSON' => ['1', 'application/json'],
            'SHA256 JSON' => ['2', 'application/json'],
        ];
    }

    #[DataProvider('signaturesAndContentTypes')]
    public function test_real_signature_accepts_raw_utf8_newlines_and_subject(string $version, string $contentType): void
    {
        $log = $this->sentLog();
        $message = SnsMessageFixture::raw(['eventType' => 'Delivery', 'mail' => ['messageId' => $log->message_id], 'detail' => "ação\nsegunda linha"]);
        $payload = $this->fixture->notification($log->message_id, $version, ['Subject' => "  Aviso ação\nSNS  ", 'Message' => " \n".$message."\n "]);

        $this->deliver($payload, $contentType)->assertNoContent();
        $this->assertSame('delivered', $log->fresh()->status->value);
        $this->assertCount(1, $this->outbound);
        $this->assertSame(SnsMessageFixture::CERT_URL, $this->outbound[0]['url']);
    }

    public function test_omitted_and_empty_subject_and_old_timestamp_remain_protocol_compatible(): void
    {
        $log = $this->sentLog();
        foreach ([[], ['Subject' => '']] as $subject) {
            $payload = $this->fixture->notification($log->message_id, '2', $subject + ['Timestamp' => '2020-01-01T00:00:00.000Z']);
            $this->deliver($payload)->assertNoContent();
        }
        $this->assertSame('delivered', $log->fresh()->status->value);
    }

    /** @return array<string, array{string, string}> */
    public static function eventMappings(): array
    {
        return ['delivery' => ['Delivery', 'delivered'], 'bounce' => ['Bounce', 'bounced'], 'complaint' => ['Complaint', 'bounced']];
    }

    #[DataProvider('eventMappings')]
    public function test_authenticated_notification_preserves_existing_event_mapping(string $event, string $status): void
    {
        $log = $this->sentLog();
        $payload = $this->fixture->notification($log->message_id, '2', ['Message' => SnsMessageFixture::raw(['notificationType' => $event, 'mail' => ['messageId' => $log->message_id]])]);
        $this->deliver($payload)->assertNoContent();
        $this->assertSame($status, $log->fresh()->status->value);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'missing signature' => ['Signature', null],
            'missing message id' => ['MessageId', null],
            'missing timestamp' => ['Timestamp', null],
            'missing cert' => ['SigningCertURL', null],
            'invalid timestamp' => ['Timestamp', 'not-a-timestamp'],
            'unsupported type' => ['Type', 'UnsubscribeConfirmation'],
            'message array' => ['Message', []],
            'subject array' => ['Subject', []],
            'integer version' => ['SignatureVersion', 2],
            'unsupported version' => ['SignatureVersion', '3'],
            'invalid base64' => ['Signature', '%%%'],
            'unlisted topic' => ['TopicArn', 'arn:aws:sns:us-east-1:123456789012:Unlisted'],
            'other partition' => ['TopicArn', 'arn:aws-cn:sns:cn-north-1:123456789012:FixtureEmail'],
            'oversized message' => ['Message', str_repeat('x', 262145)],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_envelope_is_refused_before_http_or_email_queries(string $field, mixed $value): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $payload = $this->fixture->notification();
        if ($value === null) {
            unset($payload[$field]);
        } else {
            $payload[$field] = $value;
        }
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'email_logs')) {
                $queries[] = $query->sql;
            }
        });

        $this->deliver($payload)->assertUnauthorized();
        $this->assertSame([], $queries);
        Http::assertNothingSent();
        $this->assertSame($before, $log->fresh()->getAttributes());
    }

    public function test_empty_allowlist_denies_even_a_valid_signature_without_http(): void
    {
        config(['services.ses.allowed_topic_arns' => []]);
        $this->deliver($this->fixture->notification())->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_malformed_or_oversized_raw_envelope_has_no_http_effects(): void
    {
        foreach (['[]', 'null', '{invalid', str_repeat(' ', 2097153)] as $raw) {
            $this->call('POST', '/api/email/webhook/ses', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], $raw)->assertUnauthorized();
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function invalidCertificateUrls(): array
    {
        $host = 'sns.us-east-1.amazonaws.com';
        $path = '/SimpleNotificationService-0123456789abcdef0123456789abcdef.pem';

        return [
            'http' => ['http://'.$host.$path],
            'private' => ['https://127.0.0.1'.$path],
            'userinfo' => ['https://attacker@'.$host.$path],
            'explicit port' => ['https://'.$host.':443'.$path],
            'different region' => ['https://sns.us-west-2.amazonaws.com'.$path],
            'suffix host' => ['https://'.$host.'.attacker.invalid'.$path],
            'query action' => ['https://'.$host.'/?Action=ConfirmSubscription&unused=.pem'],
            'query' => ['https://'.$host.$path.'?x=1'],
            'fragment' => ['https://'.$host.$path.'#fragment'],
            'encoded path' => ['https://'.$host.'/%53impleNotificationService-0123456789abcdef0123456789abcdef.pem'],
            'arbitrary pem' => ['https://'.$host.'/arbitrary.pem'],
        ];
    }

    #[DataProvider('invalidCertificateUrls')]
    public function test_certificate_url_must_be_exact_regional_certificate_path(string $url): void
    {
        $payload = $this->fixture->notification();
        $payload['SigningCertURL'] = $url;
        $this->deliver($payload)->assertUnauthorized();
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function invalidSubscribeUrls(): array
    {
        $query = http_build_query(['Action' => 'ConfirmSubscription', 'TopicArn' => SnsMessageFixture::TOPIC_ARN, 'Token' => 'SYNTHETIC_CONFIRMATION_TOKEN'], '', '&', PHP_QUERY_RFC3986);
        $url = SnsMessageFixture::ENDPOINT.'?'.$query;

        return [
            'private' => ['http://169.254.169.254/latest/meta-data/'],
            'arbitrary' => ['https://attacker.invalid/confirm'],
            'userinfo' => [str_replace('https://', 'https://attacker@', $url)],
            'explicit port' => [str_replace('.com/', '.com:443/', $url)],
            'wrong path' => [str_replace('/?', '/confirm?', $url)],
            'duplicate token' => [$url.'&Token=another'],
            'extra action' => [$url.'&Extra=1'],
            'wrong token' => [str_replace('SYNTHETIC_CONFIRMATION_TOKEN', 'wrong-token', $url)],
            'wrong topic' => [str_replace('FixtureEmail', 'OtherTopic', $url)],
            'wrong action' => [str_replace('ConfirmSubscription', 'Unsubscribe', $url)],
            'fragment' => [$url.'#fragment'],
        ];
    }

    #[DataProvider('invalidSubscribeUrls')]
    public function test_invalid_subscribe_url_is_never_requested_even_with_signed_envelope(string $url): void
    {
        $this->deliver($this->fixture->confirmation('2', ['SubscribeURL' => $url]))->assertUnauthorized();
        Http::assertNothingSent();
    }

    /** @return array<string, array{list<string>}> */
    public static function deniedAddresses(): array
    {
        return [
            'loopback' => [['127.0.0.1']],
            'private' => [['10.0.0.1']],
            'link local' => [['169.254.169.254']],
            'unspecified' => [['0.0.0.0']],
            'IPv6' => [['::1']],
            'CGNAT' => [['100.64.0.1']],
            'documentation range' => [['192.0.2.1']],
            'reserved' => [['240.0.0.1']],
            'multicast' => [['224.0.0.1']],
            'multicast SSDP' => [['239.255.255.250']],
            'mixed answer' => [[SnsMessageFixture::PUBLIC_IP, '192.168.1.1']],
        ];
    }

    /** @param list<string> $addresses */
    #[DataProvider('deniedAddresses')]
    public function test_dns_answers_must_all_be_public_ipv4_before_any_http(array $addresses): void
    {
        SnsMessageFixture::bindResolver($this->app, $addresses);
        $this->deliver($this->fixture->notification())->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_valid_shape_with_tampered_signature_can_only_fetch_certificate(): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $this->recordEmailQueries();
        foreach (['notification', 'confirmation'] as $kind) {
            $payload = $kind === 'notification' ? $this->fixture->notification() : $this->fixture->confirmation();
            $payload['Message'] .= 'tampered';
            $this->deliver($payload)->assertUnauthorized();
        }
        $this->assertCount(2, $this->outbound);
        foreach ($this->outbound as $request) {
            $this->assertSame(SnsMessageFixture::CERT_URL, $request['url']);
        }
        $this->assertSame([], $this->emailQueries);
        $this->assertSame($before, $log->fresh()->getAttributes());
    }

    public function test_verified_signing_certificate_is_reused_but_still_checks_every_signature(): void
    {
        $first = $this->sentLog();
        $this->deliver($this->fixture->notification($first->message_id))->assertNoContent();
        $tampered = $this->fixture->notification($first->message_id);
        $tampered['Message'] .= 'tampered';
        $this->deliver($tampered)->assertUnauthorized();
        $this->deliver($this->fixture->notification($first->message_id))->assertNoContent();

        $this->assertCount(1, $this->outbound, 'A certificate that already verified a signature must not be downloaded per webhook.');
        $this->assertSame(SnsMessageFixture::CERT_URL, $this->outbound[0]['url']);
    }

    public function test_confirmation_is_reconstructed_only_after_signature_and_uses_pinned_transport(): void
    {
        $payload = $this->fixture->confirmation('1');
        $payload['SubscribeURL'] .= '&Version=2010-03-31';
        $payload = $this->fixture->sign($payload);
        $request = Request::create('/api/email/webhook/ses', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], SnsMessageFixture::raw($payload));
        $handler = $this->app->make(SesWebhookHandler::class);

        $this->assertTrue($handler->verifySignature($request));
        $this->assertCount(1, $this->outbound);
        $handler->handle($request);
        $this->assertCount(2, $this->outbound);
        $confirmation = $this->outbound[1];
        $this->assertSame('GET', $confirmation['method']);
        $this->assertSame('sns.us-east-1.amazonaws.com', parse_url($confirmation['url'], PHP_URL_HOST));
        $this->assertSame('/', parse_url($confirmation['url'], PHP_URL_PATH));
        parse_str((string) parse_url($confirmation['url'], PHP_URL_QUERY), $query);
        $this->assertSame('ConfirmSubscription', $query['Action'] ?? null);
        $this->assertSame(SnsMessageFixture::TOPIC_ARN, $query['TopicArn'] ?? null);
        $this->assertSame($payload['Token'], $query['Token'] ?? null);
        foreach ($this->outbound as $sent) {
            $options = $sent['options'];
            $this->assertTrue($options['verify'] ?? false);
            $this->assertFalse($options['allow_redirects'] ?? true);
            $this->assertFalse($options['stream'] ?? true);
            $this->assertSame('', $options['proxy'] ?? null);
            $this->assertGreaterThan(0, $options['connect_timeout'] ?? 0);
            $this->assertGreaterThan(0, $options['timeout'] ?? 0);
            $this->assertSame(['sns.us-east-1.amazonaws.com:443:'.SnsMessageFixture::PUBLIC_IP], $options['curl'][CURLOPT_RESOLVE] ?? null);
            $this->assertSame('', $options['curl'][CURLOPT_PROXY] ?? null);
            $this->assertSame('*', $options['curl'][CURLOPT_NOPROXY] ?? null);
            $this->assertSame(CURL_IPRESOLVE_V4, $options['curl'][CURLOPT_IPRESOLVE] ?? null);
            $this->assertTrue($options['curl'][CURLOPT_FRESH_CONNECT] ?? false);
            $this->assertTrue($options['curl'][CURLOPT_FORBID_REUSE] ?? false);
        }
    }

    public function test_handle_without_verification_cannot_mutate_email_or_confirm(): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], SnsMessageFixture::raw($this->fixture->notification()));
        $this->assertHandleDenied($this->app->make(SesWebhookHandler::class), $request);
        Http::assertNothingSent();
        $this->assertSame($before, $log->fresh()->getAttributes());
    }

    public function test_mutated_raw_body_or_failed_reverification_invalidates_request_proof(): void
    {
        $handler = $this->app->make(SesWebhookHandler::class);
        $payload = $this->fixture->confirmation();
        $raw = SnsMessageFixture::raw($payload);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $raw);
        $this->assertTrue($handler->verifySignature($request));
        $request->initialize([], [], $request->attributes->all(), [], [], $request->server->all(), $raw.' ');
        $this->assertHandleDenied($handler, $request);
        $this->assertCount(1, $this->outbound);

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $raw);
        $this->assertTrue($handler->verifySignature($request));
        config(['services.ses.allowed_topic_arns' => []]);
        $this->assertFalse($handler->verifySignature($request));
        $this->assertHandleDenied($handler, $request);
        // The certificate verified by the first signature is reused; the failed reverification still denies.
        $this->assertCount(1, $this->outbound);
        $another = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $raw);
        $this->assertHandleDenied($handler, $another);
    }

    private function assertHandleDenied(SesWebhookHandler $handler, Request $request): void
    {
        try {
            $handler->handle($request);
            $this->fail('Unverified request must be refused.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
    }

    /** @return array<string, array{int}> */
    public static function unavailableCertificates(): array
    {
        return ['redirect' => [302], 'missing certificate' => [404], 'temporary outage' => [503]];
    }

    #[DataProvider('unavailableCertificates')]
    public function test_certificate_download_failure_is_retryable_without_following_redirect(int $status): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $this->fakeTransport(fn () => Http::response('', $status, ['Location' => 'http://127.0.0.1/private']));
        $response = $this->deliver($this->fixture->notification())->assertStatus(503);
        $this->assertStringNotContainsString('127.0.0.1', $response->getContent());
        $this->assertCount(1, $this->outbound);
        $this->assertSame($before, $log->fresh()->getAttributes());
    }

    public function test_invalid_certificate_is_unauthorized_and_transport_failure_recovers(): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $this->recordEmailQueries();
        $this->fakeTransport(fn () => Http::response('not a certificate', 200));
        $this->deliver($this->fixture->notification())->assertUnauthorized();
        $this->assertSame([], $this->emailQueries);
        $this->assertSame($before, $log->fresh()->getAttributes());
        $attempt = 0;
        $this->fakeTransport(function () use (&$attempt) {
            return ++$attempt === 1 ? Http::failedConnection('Synthetic transport failure') : Http::response($this->fixture->certificate(), 200);
        });
        $this->deliver($this->fixture->notification())->assertStatus(503);
        $this->assertSame('sent', $log->fresh()->status->value);
        $this->deliver($this->fixture->notification())->assertNoContent();
        $this->assertSame('delivered', $log->fresh()->status->value);
    }

    public function test_confirmation_failure_is_generic_retryable_and_next_attempt_succeeds(): void
    {
        $confirmationAttempts = 0;
        $this->fakeTransport(function (ClientRequest $request) use (&$confirmationAttempts) {
            if ($request->url() === SnsMessageFixture::CERT_URL) {
                return Http::response($this->fixture->certificate(), 200);
            }

            return Http::response('', ++$confirmationAttempts === 1 ? 503 : 200);
        });
        $payload = $this->fixture->confirmation();
        $response = $this->deliver($payload)->assertStatus(503);
        $this->assertStringNotContainsString($payload['Token'], $response->getContent());
        $this->assertStringNotContainsString($payload['SubscribeURL'], $response->getContent());
        $this->deliver($payload)->assertNoContent();
        $this->assertSame(2, $confirmationAttempts);
    }

    public function test_empty_dns_answer_is_retryable_without_http_or_email_queries(): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        SnsMessageFixture::bindResolver($this->app, []);
        $this->recordEmailQueries();

        $this->deliver($this->fixture->notification())->assertStatus(503);
        Http::assertNothingSent();
        $this->assertSame([], $this->emailQueries);
        $this->assertSame($before, $log->fresh()->getAttributes());
    }

    /** @return array<string, array{string, string}> */
    public static function oversizedResponses(): array
    {
        return [
            'certificate declared length' => ['certificate', 'headers'],
            'certificate chunked download' => ['certificate', 'progress'],
            'certificate final body' => ['certificate', 'body'],
            'confirmation declared length' => ['confirmation', 'headers'],
            'confirmation chunked download' => ['confirmation', 'progress'],
            'confirmation final body' => ['confirmation', 'body'],
        ];
    }

    #[DataProvider('oversizedResponses')]
    public function test_oversized_responses_stop_before_processing_and_allow_retry(string $target, string $boundary): void
    {
        $log = $this->sentLog();
        $before = $log->getAttributes();
        $probed = false;
        $progressAbort = null;
        $this->fakeTransport(function (ClientRequest $request, array $options) use ($target, $boundary, &$probed, &$progressAbort) {
            $certificate = $request->url() === SnsMessageFixture::CERT_URL;
            $body = $certificate ? $this->fixture->certificate() : '<ConfirmSubscriptionResponse/>';
            if (($target === 'certificate') !== $certificate) {
                return Http::response($body, 200);
            }
            $probed = true;
            if ($boundary === 'headers' && is_callable($options['on_headers'] ?? null)) {
                $options['on_headers'](new PsrResponse(200, ['Content-Length' => '65537']));
            }
            if ($boundary === 'progress' && is_callable($options['curl'][CURLOPT_PROGRESSFUNCTION] ?? null)) {
                $handle = curl_init();
                $this->assertInstanceOf(\CurlHandle::class, $handle);
                try {
                    $progressAbort = $options['curl'][CURLOPT_PROGRESSFUNCTION]($handle, 0, 65537, 0, 0);
                } finally {
                    curl_close($handle);
                }
                if ($progressAbort === 1) {
                    return Http::failedConnection('Synthetic response-cap cURL abort.');
                }
            }
            if ($boundary === 'body') {
                $body = str_pad($body, 65537, ' ');
            }

            return Http::response($body, 200);
        });
        $this->recordEmailQueries();
        $payload = $target === 'certificate' ? $this->fixture->notification() : $this->fixture->confirmation();

        $response = $this->deliver($payload)->assertStatus(503);
        $this->assertTrue($probed);
        if ($boundary === 'progress') {
            $this->assertSame(1, $progressAbort);
        }
        $this->assertStringNotContainsString(SnsMessageFixture::CERT_URL, $response->getContent());
        $this->assertStringNotContainsString('SYNTHETIC_CONFIRMATION_TOKEN', $response->getContent());
        $this->assertSame([], $this->emailQueries);
        $this->assertSame($before, $log->fresh()->getAttributes());
        $this->assertCount($target === 'certificate' ? 1 : 2, $this->outbound);

        $this->fakeTransport();
        $this->deliver($payload)->assertNoContent();
        $this->assertSame($target === 'certificate' ? 'delivered' : 'sent', $log->fresh()->status->value);
    }

    public function test_certificate_and_confirmation_responses_accept_exact_64kib_limit(): void
    {
        $progressResults = [];
        $this->fakeTransport(function (ClientRequest $request, array $options) use (&$progressResults) {
            $body = $request->url() === SnsMessageFixture::CERT_URL ? $this->fixture->certificate() : '<ConfirmSubscriptionResponse/>';
            $body = str_pad($body, 65536, ' ');
            if (is_callable($options['on_headers'] ?? null)) {
                $options['on_headers'](new PsrResponse(200, ['Content-Length' => '65536']));
            }
            if (is_callable($options['curl'][CURLOPT_PROGRESSFUNCTION] ?? null)) {
                $handle = curl_init();
                $this->assertInstanceOf(\CurlHandle::class, $handle);
                try {
                    $progressResults[] = $options['curl'][CURLOPT_PROGRESSFUNCTION]($handle, 65536, 65536, 0, 0);
                } finally {
                    curl_close($handle);
                }
            }

            return Http::response($body, 200);
        });

        $this->deliver($this->fixture->confirmation())->assertNoContent();
        $this->assertCount(2, $this->outbound);
        $this->assertSame([0, 0], $progressResults);
    }

    public function test_message_and_raw_envelope_accept_exact_documented_limits(): void
    {
        $log = $this->sentLog();
        $message = SnsMessageFixture::raw(['eventType' => 'Delivery', 'mail' => ['messageId' => $log->message_id]]);
        $payload = $this->fixture->notification($log->message_id, '2', ['Message' => str_pad($message, 262144, ' ')]);
        $raw = str_pad(SnsMessageFixture::raw($payload), 2097152, ' ');
        $this->assertSame(262144, strlen($payload['Message']));
        $this->assertSame(2097152, strlen($raw));

        $this->call('POST', '/api/email/webhook/ses', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], $raw)->assertNoContent();
        $this->assertSame('delivered', $log->fresh()->status->value);
    }
}
