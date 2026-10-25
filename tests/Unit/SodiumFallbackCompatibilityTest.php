<?php

declare(strict_types=1);

namespace Tests\Unit;

use ParagonIE\Sodium\Compat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SodiumException;

class SodiumFallbackCompatibilityTest extends TestCase
{
    private const RFC8032_TEST_ONE_PUBLIC_KEY = 'd75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a';

    private const EXPECTED_CURVE25519_PUBLIC_KEY = 'd85e07ec22b0ad881537c2f44d662d1a143cf830c57aca4305d85c7a90f6b62e';

    public function test_forced_fallback_converts_the_public_rfc8032_vector(): void
    {
        $originalFallbackSetting = Compat::$disableFallbackForUnitTests;

        try {
            Compat::$disableFallbackForUnitTests = true;
            $publicKey = hex2bin(self::RFC8032_TEST_ONE_PUBLIC_KEY);
            $this->assertIsString($publicKey);

            $converted = Compat::crypto_sign_ed25519_pk_to_curve25519($publicKey);

            $this->assertSame(32, strlen($converted));
            $this->assertSame(self::EXPECTED_CURVE25519_PUBLIC_KEY, bin2hex($converted));
        } finally {
            Compat::$disableFallbackForUnitTests = $originalFallbackSetting;
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidPublicKeys(): array
    {
        return [
            '31 byte public key' => [str_repeat('00', 31)],
            '32 byte identity point' => ['01'.str_repeat('00', 31)],
        ];
    }

    #[DataProvider('invalidPublicKeys')]
    public function test_forced_fallback_refuses_invalid_keys_and_recovers(string $invalidHex): void
    {
        $originalFallbackSetting = Compat::$disableFallbackForUnitTests;

        try {
            Compat::$disableFallbackForUnitTests = true;
            $invalidKey = hex2bin($invalidHex);
            $this->assertIsString($invalidKey);
            $refused = false;

            try {
                Compat::crypto_sign_ed25519_pk_to_curve25519($invalidKey);
            } catch (SodiumException) {
                $refused = true;
            }

            $publicKey = hex2bin(self::RFC8032_TEST_ONE_PUBLIC_KEY);
            $this->assertIsString($publicKey);
            $recovered = Compat::crypto_sign_ed25519_pk_to_curve25519($publicKey);

            $this->assertTrue($refused, 'The fallback must refuse an invalid Ed25519 public key.');
            $this->assertSame(self::EXPECTED_CURVE25519_PUBLIC_KEY, bin2hex($recovered));
        } finally {
            Compat::$disableFallbackForUnitTests = $originalFallbackSetting;
        }
    }
}
