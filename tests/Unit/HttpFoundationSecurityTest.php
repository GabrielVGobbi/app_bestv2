<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\IsSignatureValid;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Polyfill\Intl\Idn\Idn;

class HttpFoundationSecurityTest extends TestCase
{
    public function test_header_split_preserves_directives_before_bounded_whitespace(): void
    {
        $originalLimit = ini_get('pcre.backtrack_limit');
        $this->assertIsString($originalLimit);

        try {
            $this->assertNotFalse(ini_set('pcre.backtrack_limit', '10000'));

            $parts = HeaderUtils::split('a'.str_repeat(' ', 25).',b', ',=');
            $parseError = preg_last_error();

            $this->assertSame(PREG_NO_ERROR, $parseError);
            $this->assertSame([['a'], ['b']], $parts);

            $normalParts = HeaderUtils::split('max-age=60, public', ',=');
            $normalParseError = preg_last_error();

            $this->assertSame(PREG_NO_ERROR, $normalParseError);
            $this->assertSame([['max-age', '60'], ['public']], $normalParts);
        } finally {
            $this->assertNotFalse(ini_set('pcre.backtrack_limit', $originalLimit));
            $this->assertSame($originalLimit, ini_get('pcre.backtrack_limit'));
        }
    }

    public function test_request_cache_control_preserves_bounded_whitespace_and_recovers(): void
    {
        $originalLimit = ini_get('pcre.backtrack_limit');
        $this->assertIsString($originalLimit);

        try {
            $this->assertNotFalse(ini_set('pcre.backtrack_limit', '10000'));

            $request = Request::create('http://www2.start_kit.com.br/preview', server: [
                'HTTP_CACHE_CONTROL' => 'public'.str_repeat(' ', 25).',max-age=60',
            ]);
            $parseError = preg_last_error();

            $this->assertSame(PREG_NO_ERROR, $parseError);
            $this->assertTrue($request->headers->hasCacheControlDirective('public'));
            $this->assertSame('60', $request->headers->getCacheControlDirective('max-age'));

            $normalRequest = Request::create('http://www2.start_kit.com.br/preview', server: [
                'HTTP_CACHE_CONTROL' => 'private, max-age=30',
            ]);
            $normalParseError = preg_last_error();

            $this->assertSame(PREG_NO_ERROR, $normalParseError);
            $this->assertTrue($normalRequest->headers->hasCacheControlDirective('private'));
            $this->assertFalse($normalRequest->headers->hasCacheControlDirective('public'));
            $this->assertSame('30', $normalRequest->headers->getCacheControlDirective('max-age'));
        } finally {
            $this->assertNotFalse(ini_set('pcre.backtrack_limit', $originalLimit));
            $this->assertSame($originalLimit, ini_get('pcre.backtrack_limit'));
        }
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function legitimateHeaderSeparators(): array
    {
        return [
            'space separator' => ['foo bar', ' ', ['foo', 'bar']],
            'tab separator' => ["foo bar\t baz", "\t", ['foo bar', 'baz']],
            'whitespace inside value' => ['a b  c', ',', ['a b  c']],
            'quoted separator' => ['"a,b",c', ',', ['a,b', 'c']],
        ];
    }

    #[DataProvider('legitimateHeaderSeparators')]
    public function test_header_split_preserves_legitimate_separator_semantics(string $header, string $separators, array $expected): void
    {
        $parts = HeaderUtils::split($header, $separators);
        $parseError = preg_last_error();

        $this->assertSame(PREG_NO_ERROR, $parseError);
        $this->assertSame($expected, $parts);
    }

    /** @return array<string, array{string, bool}> */
    public static function addressClassifications(): array
    {
        return [
            'IPv4 compatible loopback' => ['::7f00:1', true],
            '6to4 loopback' => ['2002:7f00:1::', true],
            'Teredo' => ['2001::1', true],
            'NAT64 well-known' => ['64:ff9b::7f00:1', true],
            'NAT64 local-use' => ['64:ff9b:1::7f00:1', true],
            'private IPv4' => ['127.0.0.1', true],
            'private IPv6' => ['::1', true],
            'public IPv4' => ['8.8.8.8', false],
            'public IPv6' => ['2606:4700:4700::1111', false],
        ];
    }

    #[DataProvider('addressClassifications')]
    public function test_ip_classification_blocks_transition_prefixes_without_network_access(string $address, bool $private): void
    {
        $this->assertSame($private, IpUtils::isPrivateIp($address));
    }

    /** @return array<string, array{string|list<string>, list<string>}> */
    public static function signatureMethodFilters(): array
    {
        return [
            'GET string' => ['GET', ['GET', 'HEAD']],
            'get string' => ['get', ['GET', 'HEAD']],
            'GET array' => [['GET', 'POST'], ['GET', 'POST', 'HEAD']],
            'POST string' => ['POST', ['POST']],
            'post string' => ['post', ['POST']],
            'unfiltered' => [[], []],
        ];
    }

    #[DataProvider('signatureMethodFilters')]
    public function test_signature_attribute_get_filter_also_covers_head(string|array $methods, array $expected): void
    {
        $this->assertSame($expected, (new IsSignatureValid(methods: $methods))->methods);
    }

    public function test_url_generator_rejects_requirement_substrings_and_recovers_on_same_instance(): void
    {
        $routes = new RouteCollection;
        $routes->add('localized', new Route('/{locale}/blog', requirements: ['locale' => 'en|vi|fr']));
        $generator = new UrlGenerator($routes, new RequestContext(host: 'www2.start_kit.com.br'));

        try {
            $generator->generate('localized', ['locale' => '/evil.test']);
            $this->fail('A substring of an alternative must not satisfy the whole route requirement.');
        } catch (InvalidParameterException $exception) {
            $this->assertSame(InvalidParameterException::class, $exception::class);
        }

        $this->assertSame('/vi/blog', $generator->generate('localized', ['locale' => 'vi']));
        $this->assertSame('/en/blog', $generator->generate('localized', ['locale' => 'en']));
        $this->assertSame('/fr/blog', $generator->generate('localized', ['locale' => 'fr']));
        $this->assertSame(
            'http://www2.start_kit.com.br/vi/blog',
            $generator->generate('localized', ['locale' => 'vi'], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    /** @return array<string, array{string, string}> */
    public static function routePathSegments(): array
    {
        return [
            'consecutive parent segments' => ['../../..', '%2E%2E/%2E%2E/%2E%2E'],
            'consecutive current segments' => ['././.', '%2E/%2E/%2E'],
            'mixed relative segments' => ['../././..', '%2E%2E/%2E/%2E/%2E%2E'],
            'ordinary names containing dots' => ['reports/v1.2/file.pdf', 'reports/v1.2/file.pdf'],
        ];
    }

    #[DataProvider('routePathSegments')]
    public function test_url_generator_encodes_each_relative_segment_for_rfc3986_consumers(string $input, string $encoded): void
    {
        $routes = new RouteCollection;
        $routes->add('file', new Route('/files/{path}/view', requirements: ['path' => '.+']));
        $generator = new UrlGenerator($routes, new RequestContext);

        $this->assertSame('/files/'.$encoded.'/view', $generator->generate('file', ['path' => $input]));
        $this->assertSame('/files/safe.pdf/view', $generator->generate('file', ['path' => 'safe.pdf']));
    }

    /** @return array<string, array{string}> */
    public static function invalidAsciiOnlyPunycodeLabels(): array
    {
        return [
            'decoded ASCII-only label' => ['poc.xn--kc1zs4-.com'],
            'empty decoded label' => ['poc.kc1zs4.xn--'],
        ];
    }

    /**
     * Calls the package's public entrypoints directly: the class is @internal,
     * and global idn functions may resolve to native ICU instead of this fallback.
     */
    #[DataProvider('invalidAsciiOnlyPunycodeLabels')]
    public function test_idn_polyfill_rejects_ascii_only_punycode_in_both_directions(string $domain): void
    {
        $options = Idn::IDNA_USE_STD3_RULES | Idn::IDNA_CHECK_BIDI | Idn::IDNA_CHECK_CONTEXTJ;
        $asciiInfo = [];
        $unicodeInfo = [];

        $ascii = Idn::idn_to_ascii($domain, $options | Idn::IDNA_NONTRANSITIONAL_TO_ASCII, Idn::INTL_IDNA_VARIANT_UTS46, $asciiInfo);
        $unicode = Idn::idn_to_utf8($domain, $options | Idn::IDNA_NONTRANSITIONAL_TO_UNICODE, Idn::INTL_IDNA_VARIANT_UTS46, $unicodeInfo);

        $this->assertFalse($ascii);
        $this->assertFalse($unicode);
        $this->assertSame(Idn::ERROR_INVALID_ACE_LABEL, $asciiInfo['errors'] & Idn::ERROR_INVALID_ACE_LABEL);
        $this->assertSame(Idn::ERROR_INVALID_ACE_LABEL, $unicodeInfo['errors'] & Idn::ERROR_INVALID_ACE_LABEL);

        $recoveryInfo = [];
        $this->assertSame('example.test', Idn::idn_to_ascii('example.test', $options, Idn::INTL_IDNA_VARIANT_UTS46, $recoveryInfo));
        $this->assertSame(0, $recoveryInfo['errors']);
    }

    /** @return array<string, array{string, string}> */
    public static function legitimateIdnDomains(): array
    {
        return [
            'Unicode domain' => ['bücher.example', 'xn--bcher-kva.example'],
            'ASCII domain' => ['example.test', 'example.test'],
        ];
    }

    #[DataProvider('legitimateIdnDomains')]
    public function test_idn_polyfill_preserves_legitimate_domain_roundtrips(string $unicodeDomain, string $asciiDomain): void
    {
        $options = Idn::IDNA_USE_STD3_RULES | Idn::IDNA_CHECK_BIDI | Idn::IDNA_CHECK_CONTEXTJ;
        $asciiInfo = [];
        $unicodeInfo = [];

        $this->assertSame($asciiDomain, Idn::idn_to_ascii($unicodeDomain, $options | Idn::IDNA_NONTRANSITIONAL_TO_ASCII, Idn::INTL_IDNA_VARIANT_UTS46, $asciiInfo));
        $this->assertSame(0, $asciiInfo['errors']);
        $this->assertSame($unicodeDomain, Idn::idn_to_utf8($asciiDomain, $options | Idn::IDNA_NONTRANSITIONAL_TO_UNICODE, Idn::INTL_IDNA_VARIANT_UTS46, $unicodeInfo));
        $this->assertSame(0, $unicodeInfo['errors']);
    }
}
