<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException;

class MimeAddressCompatibilityTest extends TestCase
{
    /** @return array<string, array{string, string, string, string}> */
    public static function legitimateAddresses(): array
    {
        return [
            'ASCII' => ['fixture@example.test', '', 'fixture@example.test', 'fixture@example.test'],
            'plus tag' => ['fixture+tag@example.test', '', 'fixture+tag@example.test', 'fixture+tag@example.test'],
            'quoted local part' => ['"fixture name"@example.test', '', '"fixture name"@example.test', '"fixture name"@example.test'],
            'UTF8 display name' => ['fixture@example.test', 'Café ç', 'fixture@example.test', '"Café ç" <fixture@example.test>'],
            'IDN domain' => ['fixture@bücher.example', '', 'fixture@xn--bcher-kva.example', 'fixture@xn--bcher-kva.example'],
        ];
    }

    #[DataProvider('legitimateAddresses')]
    public function test_legitimate_addresses_preserve_their_public_encoding_contract(string $input, string $name, string $encoded, string $serialized): void
    {
        $address = new Address($input, $name);

        $this->assertSame($input, $address->getAddress());
        $this->assertSame($name, $address->getName());
        $this->assertSame($encoded, $address->getEncodedAddress());
        $this->assertSame($serialized, $address->toString());
    }

    /** @return array<string, array{string}> */
    public static function addressesWithInternalLineBreaks(): array
    {
        return [
            'CRLF' => ["\"fixture\r\nmarker\"@example.test"],
            'CR' => ["\"fixture\rmarker\"@example.test"],
            'LF' => ["\"fixture\nmarker\"@example.test"],
        ];
    }

    #[DataProvider('addressesWithInternalLineBreaks')]
    public function test_internal_line_breaks_are_refused_and_a_subsequent_valid_address_recovers(string $input): void
    {
        $refused = false;

        try {
            new Address($input);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Email address contains control characters.', $exception->getMessage());
            $refused = true;
        }

        $recovery = new Address('recovery+fixture@example.test', 'Recuperação ç');

        $this->assertTrue($refused, 'Raw internal line breaks must be refused by the Address constructor.');
        $this->assertSame('recovery+fixture@example.test', $recovery->getAddress());
        $this->assertSame('recovery+fixture@example.test', $recovery->getEncodedAddress());
        $this->assertSame('"Recuperação ç" <recovery+fixture@example.test>', $recovery->toString());
    }
}
