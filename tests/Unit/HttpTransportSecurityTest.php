<?php

declare(strict_types=1);

namespace Tests\Unit;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpTransportSecurityTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function invalidHosts(): array
    {
        return [
            'userinfo delimiter' => ['allowed.test@other.test'],
            'path delimiter' => ['allowed.test/other.test'],
            'backslash delimiter' => ['allowed.test\\other.test'],
            'header delimiter' => ["allowed.test\r\nX-Injected: yes"],
        ];
    }

    #[DataProvider('invalidHosts')]
    public function test_uri_rejects_hosts_that_can_change_authority_or_headers(string $host): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Uri('https://allowed.test/resource'))->withHost($host);
    }

    /** @return array<string, array{string, string}> */
    public static function supportedHosts(): array
    {
        return [
            'case normalized DNS' => ['API.Example.test', 'api.example.test'],
            'canonical IPv4' => ['127.0.0.1', '127.0.0.1'],
            'bracketed IPv6' => ['[::1]', '[::1]'],
            'local HTTP custom domain' => ['www2.start_kit.com.br', 'www2.start_kit.com.br'],
        ];
    }

    #[DataProvider('supportedHosts')]
    public function test_uri_preserves_supported_hosts_without_resolving_them(string $input, string $expected): void
    {
        $uri = (new Uri('http://placeholder.test/preview'))->withHost($input);

        $this->assertSame($expected, $uri->getHost());
        $this->assertSame('http://'.$expected.'/preview', (string) $uri);
        $this->assertSame($expected, (new Uri('http://'.$input.'/preview'))->getHost());
    }

    /** @return array<string, array{string, string}> */
    public static function invalidStartLineFields(): array
    {
        return [
            'method CR' => ['method', "GET\rX-Injected: yes"],
            'method LF' => ['method', "GET\nX-Injected: yes"],
            'version CR' => ['version', "1.1\rX-Injected: yes"],
            'version LF' => ['version', "1.1\nX-Injected: yes"],
            'reason CR' => ['reason', "OK\rX-Injected: yes"],
            'reason LF' => ['reason', "OK\nX-Injected: yes"],
        ];
    }

    #[DataProvider('invalidStartLineFields')]
    public function test_start_line_fields_reject_header_injection(string $field, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        match ($field) {
            'method' => new Request($value, 'https://example.test/'),
            'version' => (new Request('GET', 'https://example.test/'))->withProtocolVersion($value),
            'reason' => new Response(200, [], null, '1.1', $value),
        };
    }

    public function test_legitimate_start_lines_remain_serializable_in_memory(): void
    {
        $request = new Request('GET', 'http://www2.start_kit.com.br/preview');
        $response = new Response(200, [], null, '1.1', 'OK');

        $this->assertSame("GET /preview HTTP/1.1\r\nHost: www2.start_kit.com.br\r\n\r\n", Message::toString($request));
        $this->assertSame("HTTP/1.1 200 OK\r\n\r\n", Message::toString($response));
    }

    public function test_response_cookie_without_domain_is_only_sent_to_its_origin_host(): void
    {
        $jar = new CookieJar;
        $origin = new Request('GET', 'https://example.test/');
        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'session=origin-only; Path=/']));

        $this->assertCount(1, $jar);
        $this->assertSame('session=origin-only', $jar->withCookieHeader($origin)->getHeaderLine('Cookie'));
        $this->assertFalse($jar->withCookieHeader(new Request('GET', 'https://child.example.test/'))->hasHeader('Cookie'));
        $this->assertFalse($jar->withCookieHeader(new Request('GET', 'https://unrelated.test/'))->hasHeader('Cookie'));
    }

    public function test_explicit_parent_domain_cookie_still_reaches_a_legitimate_subdomain(): void
    {
        $jar = new CookieJar;
        $jar->extractCookies(
            new Request('GET', 'https://example.test/'),
            new Response(200, ['Set-Cookie' => 'shared=allowed; Domain=example.test; Path=/']),
        );

        $this->assertCount(1, $jar);
        $this->assertSame('shared=allowed', $jar->withCookieHeader(new Request('GET', 'https://child.example.test/'))->getHeaderLine('Cookie'));
        $this->assertFalse($jar->withCookieHeader(new Request('GET', 'https://unrelated.test/'))->hasHeader('Cookie'));
    }

    /** @return array<string, array{string}> */
    public static function invalidCookieDomains(): array
    {
        return [
            'one dot' => ['.'],
            'two dots' => ['..'],
            'three dots' => ['...'],
            'multiple leading dots' => ['..example.test'],
        ];
    }

    #[DataProvider('invalidCookieDomains')]
    public function test_invalid_domain_cookie_is_discarded_without_poisoning_later_responses(string $domain): void
    {
        $jar = new CookieJar;
        $origin = new Request('GET', 'https://example.test/');
        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'rejected=value; Domain='.$domain.'; Path=/']));

        $this->assertCount(0, $jar);
        $this->assertFalse($jar->withCookieHeader(new Request('GET', 'https://victim.test/'))->hasHeader('Cookie'));

        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'recovered=valid; Path=/']));

        $this->assertCount(1, $jar);
        $this->assertSame('recovered=valid', $jar->withCookieHeader($origin)->getHeaderLine('Cookie'));
    }

    /** @return array<string, array{string}> */
    public static function numericCookieDomains(): array
    {
        return [
            'canonical IP' => ['127.0.0.1'],
            'hexadecimal IP' => ['0x7f000001'],
            'percent escaped IP' => ['127.0.0.%31'],
        ];
    }

    #[DataProvider('numericCookieDomains')]
    public function test_numeric_domain_cookie_never_leaks_to_a_lookalike_child_host(string $domain): void
    {
        $jar = new CookieJar;
        $origin = new Request('GET', 'http://'.$domain.'/');
        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'numeric=origin-only; Domain='.$domain.'; Path=/']));

        $this->assertCount(1, $jar);
        $this->assertSame('numeric=origin-only', $jar->withCookieHeader($origin)->getHeaderLine('Cookie'));
        $this->assertFalse($jar->withCookieHeader(new Request('GET', 'http://evil.'.$domain.'/'))->hasHeader('Cookie'));
    }

    public function test_cookie_admission_limit_is_per_response_and_preserves_existing_cookies(): void
    {
        $jar = new CookieJar;
        $origin = new Request('GET', 'https://example.test/');
        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'existing=preserved; Path=/']));
        $headers = [];

        for ($index = 0; $index < 51; $index++) {
            $headers[] = 'batch'.$index.'=value; Path=/';
        }

        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => $headers]));

        $this->assertCount(51, $jar);
        $this->assertNotNull($jar->getCookieByName('existing'));
        $this->assertNotNull($jar->getCookieByName('batch49'));
        $this->assertNull($jar->getCookieByName('batch50'));

        $jar->extractCookies($origin, new Response(200, ['Set-Cookie' => 'later=accepted; Path=/']));

        $this->assertCount(52, $jar);
        $this->assertNotNull($jar->getCookieByName('later'));
        $this->assertStringContainsString('existing=preserved', $jar->withCookieHeader($origin)->getHeaderLine('Cookie'));
        $this->assertStringContainsString('later=accepted', $jar->withCookieHeader($origin)->getHeaderLine('Cookie'));
    }
}
