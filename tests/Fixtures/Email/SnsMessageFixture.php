<?php

declare(strict_types=1);

namespace Tests\Fixtures\Email;

use App\Modules\Email\Webhooks\SesWebhookHandler;
use Illuminate\Foundation\Application;
use Mockery;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Tests\TestEnvironment;

final class SnsMessageFixture
{
    public const TOPIC_ARN = 'arn:aws:sns:us-east-1:123456789012:FixtureEmail';

    public const ENDPOINT = 'https://sns.us-east-1.amazonaws.com/';

    public const CERT_URL = self::ENDPOINT.'SimpleNotificationService-0123456789abcdef0123456789abcdef.pem';

    public const PUBLIC_IP = '93.184.216.34';

    private function __construct(private OpenSSLAsymmetricKey $key, private string $certificate) {}

    public static function create(string $ownedRuntime): self
    {
        TestEnvironment::assertSafeEnvironment();
        $root = realpath($ownedRuntime);
        $temporary = realpath(sys_get_temp_dir());

        if ($root === false || $temporary === false || is_link($ownedRuntime)
            || realpath(dirname($root)) !== $temporary
            || preg_match('/^start-kit-tests-[a-f0-9]{32}$/D', basename($root)) !== 1) {
            throw new RuntimeException('SNS fixture requires an owned test runtime.');
        }

        $config = $root.'/sns-fixture-'.bin2hex(random_bytes(12)).'.cnf';
        $handle = fopen($config, 'x');
        if ($handle === false) {
            throw new RuntimeException('Cannot create owned OpenSSL fixture configuration.');
        }
        try {
            $contents = "[req]\ndistinguished_name = fixture_dn\nprompt = no\n[fixture_dn]\nCN = SNS Synthetic Fixture\n";
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new RuntimeException('Cannot write OpenSSL fixture configuration.');
            }
        } finally {
            fclose($handle);
        }

        $options = ['config' => $config, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $key = openssl_pkey_new($options);
        if ($key === false) {
            throw new RuntimeException('Cannot generate synthetic RSA key.');
        }
        $csr = openssl_csr_new(['commonName' => 'SNS Synthetic Fixture'], $key, $options);
        $certificate = $csr === false ? false : openssl_csr_sign($csr, null, $key, 30, $options);
        if ($certificate === false || ! openssl_x509_export($certificate, $pem)) {
            throw new RuntimeException('Cannot generate synthetic SNS certificate.');
        }

        return new self($key, $pem);
    }

    public function certificate(): string
    {
        return $this->certificate;
    }

    /** @param list<string> $addresses */
    public static function bindResolver(Application $app, array $addresses = [self::PUBLIC_IP]): SesWebhookHandler
    {
        $handler = Mockery::mock(SesWebhookHandler::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $handler->shouldReceive('resolveAddresses')->andReturn($addresses);
        $app->instance(SesWebhookHandler::class, $handler);

        return $handler;
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function notification(string $messageId = 'ses-fixture-message', string $version = '2', array $changes = []): array
    {
        return $this->sign(array_replace([
            'Type' => 'Notification',
            'MessageId' => '12345678-1234-4234-8234-123456789012',
            'TopicArn' => self::TOPIC_ARN,
            'Message' => self::raw(['eventType' => 'Delivery', 'mail' => ['messageId' => $messageId]]),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => $version,
            'SigningCertURL' => self::CERT_URL,
        ], $changes));
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function confirmation(string $version = '2', array $changes = []): array
    {
        $token = 'SYNTHETIC_CONFIRMATION_TOKEN';

        return $this->sign(array_replace([
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => '22345678-1234-4234-8234-123456789012',
            'TopicArn' => self::TOPIC_ARN,
            'Message' => "Synthetic subscription\nConfirmação UTF-8",
            'Token' => $token,
            'SubscribeURL' => self::ENDPOINT.'?'.http_build_query(['Action' => 'ConfirmSubscription', 'TopicArn' => self::TOPIC_ARN, 'Token' => $token], '', '&', PHP_QUERY_RFC3986),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => $version,
            'SigningCertURL' => self::CERT_URL,
        ], $changes));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sign(array $payload): array
    {
        $fields = $payload['Type'] === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $canonical = '';
        foreach ($fields as $field) {
            if ($field === 'Subject' && ! array_key_exists($field, $payload)) {
                continue;
            }
            $canonical .= $field."\n".$payload[$field]."\n";
        }
        if (! openssl_sign($canonical, $signature, $this->key, $payload['SignatureVersion'] === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Cannot sign synthetic SNS envelope.');
        }
        $payload['Signature'] = base64_encode($signature);

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function raw(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
