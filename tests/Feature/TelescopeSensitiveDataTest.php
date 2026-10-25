<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\EventWatcher;
use Laravel\Telescope\Watchers\RequestWatcher;
use Tests\TestCase;

class TelescopeSensitiveDataTest extends TestCase
{
    public function test_slack_webhook_parameter_is_redacted_before_request_recording(): void
    {
        $watcher = new class extends RequestWatcher
        {
            /**
             * @param  array<string, mixed>  $parameters
             * @return array<string, mixed>
             */
            public function redactParameters(array $parameters): array
            {
                return $this->payload($parameters);
            }
        };
        $webhook = 'https://hooks.slack.com/services/TSEC004/BSEC004/SEC004_TELESCOPE_WEBHOOK';
        $request = Request::create('/admin/slack', 'PUT', [
            'webhook_url' => $webhook, 'enabled' => true, 'log_level' => 'warning',
        ]);
        $parameters = $watcher->redactParameters($request->all());

        $this->assertSame('********', $parameters['webhook_url']);
        $this->assertStringNotContainsString('SEC004_TELESCOPE_WEBHOOK', json_encode($parameters, JSON_THROW_ON_ERROR));
        $this->assertTrue($parameters['enabled']);
        $this->assertSame('warning', $parameters['log_level']);
    }

    public function test_two_factor_codes_are_redacted_before_request_recording(): void
    {
        $watcher = new class extends RequestWatcher
        {
            /**
             * @param  array<string, mixed>  $parameters
             * @return array<string, mixed>
             */
            public function redactParameters(array $parameters): array
            {
                return $this->payload($parameters);
            }
        };
        $code = '735281';
        $recoveryCode = 'SEC013_SYNTHETIC_RECOVERY_CODE';
        $request = Request::create('/api/v1/auth/login', 'POST', [
            'email' => 'mfa-fixture@example.test',
            'code' => $code,
            'recovery_code' => $recoveryCode,
            'device_name' => 'SEC013 fixture device',
        ]);

        $parameters = $watcher->redactParameters($request->all());
        $serialized = json_encode($parameters, JSON_THROW_ON_ERROR);

        $this->assertSame('********', $parameters['code']);
        $this->assertSame('********', $parameters['recovery_code']);
        $this->assertStringNotContainsString($code, $serialized);
        $this->assertStringNotContainsString($recoveryCode, $serialized);
        $this->assertSame('mfa-fixture@example.test', $parameters['email']);
        $this->assertSame('SEC013 fixture device', $parameters['device_name']);
    }

    public function test_recovery_event_code_is_redacted_without_suppressing_event_diagnostics(): void
    {
        $watcher = new class extends EventWatcher
        {
            /**
             * @param  list<object>  $payload
             * @return array<string, mixed>
             */
            public function eventPayload(string $name, array $payload): array
            {
                return $this->extractPayload($name, $payload);
            }
        };
        $user = new User;
        $user->setAttribute('id', 713);
        $code = 'SEC013_RECOVERY_EVENT_CODE';
        $event = new RecoveryCodeReplaced($user, $code);
        $payload = $watcher->eventPayload(RecoveryCodeReplaced::class, [$event]);
        $entry = new class(['name' => RecoveryCodeReplaced::class, 'payload' => $payload, 'listeners' => [], 'broadcast' => false]) extends IncomingEntry
        {
            public function hasMonitoredTag(): bool
            {
                return true;
            }
        };
        $entry->type(EntryType::EVENT);
        $benignEntry = clone $entry;
        $benignEntry->content['name'] = \stdClass::class;
        $benignEntry->content['payload'] = $watcher->eventPayload(\stdClass::class, [
            (object) ['code' => 'ordinary-diagnostic-code', 'label' => 'Diagnóstico útil Café'],
        ]);
        $benignBefore = $benignEntry->content;
        $queueBefore = Telescope::$entriesQueue;
        $recordingBefore = Telescope::isRecording();
        $this->assertNotEmpty(Telescope::$filterUsing);
        $this->assertFalse($recordingBefore);

        foreach (Telescope::$filterUsing as $filter) {
            $this->assertTrue($filter($entry));
            $this->assertTrue($filter($benignEntry));
        }

        $this->assertSame('********', $entry->content['payload']['code']);
        $this->assertStringNotContainsString($code, json_encode($entry->toArray(), JSON_THROW_ON_ERROR));
        $this->assertSame($payload['user'], $entry->content['payload']['user']);
        $this->assertSame(RecoveryCodeReplaced::class, $entry->content['name']);
        $this->assertSame($benignBefore, $benignEntry->content);
        $this->assertSame($code, $event->code);
        $this->assertSame($queueBefore, Telescope::$entriesQueue);
        $this->assertSame($recordingBefore, Telescope::isRecording());
    }

    public function test_sensitive_password_request_parameters_are_hidden(): void
    {
        $this->assertContains('current_password', Telescope::$hiddenRequestParameters);
        $this->assertContains('password', Telescope::$hiddenRequestParameters);
        $this->assertContains('password_confirmation', Telescope::$hiddenRequestParameters);
    }

    public function test_sensitive_auth_headers_are_hidden(): void
    {
        $this->assertContains('authorization', Telescope::$hiddenRequestHeaders);
        $this->assertContains('cookie', Telescope::$hiddenRequestHeaders);
        $this->assertContains('php-auth-pw', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-csrf-token', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-xsrf-token', Telescope::$hiddenRequestHeaders);
    }

    public function test_postmark_webhook_token_is_redacted_before_request_recording(): void
    {
        $watcher = new class extends RequestWatcher
        {
            /** @param array<string, list<string>> $headers
             * @return array<string, string>
             */
            public function redactHeaders(array $headers): array
            {
                return $this->headers($headers);
            }
        };
        $token = 'SEC009_SYNTHETIC_TOKEN_MUST_NOT_BE_RECORDED';
        $request = Request::create('/api/email/webhook/postmark', 'POST', [], [], [], [
            'HTTP_X_POSTMARK_WEBHOOK_TOKEN' => $token,
            'HTTP_X_TRACE_ID' => 'ordinary-fixture-header',
        ]);

        $headers = $watcher->redactHeaders($request->headers->all());

        $this->assertArrayHasKey('x-postmark-webhook-token', $headers);
        $this->assertSame('********', $headers['x-postmark-webhook-token']);
        $this->assertStringNotContainsString($token, json_encode($headers, JSON_THROW_ON_ERROR));
        $this->assertSame('ordinary-fixture-header', $headers['x-trace-id']);
    }
}
