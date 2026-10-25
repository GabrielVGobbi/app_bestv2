<?php

namespace Tests\Unit;

use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\PathTraversalDetected;
use League\Flysystem\WhitespacePathNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlysystemPathNormalizationTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function malformedUtf8Paths(): array
    {
        return [
            'invalid leading byte without controls' => ['ff666978747572652e747874'],
            'lone continuation byte without controls' => ['66697874757265802e747874'],
            'invalid leading byte with tab' => ['ff66697874757265092e747874'],
            'invalid leading byte with line feed' => ['ff666978747572650a2e747874'],
        ];
    }

    #[DataProvider('malformedUtf8Paths')]
    public function test_malformed_utf8_paths_are_refused_without_poisoning_subsequent_normalization(string $hex): void
    {
        $path = hex2bin($hex);
        $this->assertIsString($path);
        $normalizer = new WhitespacePathNormalizer;
        $refused = false;

        try {
            $normalizer->normalizePath($path);
        } catch (CorruptedPathDetected) {
            $refused = true;
        }

        $recovered = $normalizer->normalizePath('pasta/./Café.txt');

        $this->assertTrue($refused, 'Malformed UTF8 must be refused with CorruptedPathDetected.');
        $this->assertSame('pasta/Café.txt', $recovered);
    }

    /** @return array<string, array{string, string}> */
    public static function legitimatePaths(): array
    {
        return [
            'ASCII path' => ['notes/fixture.txt', 'notes/fixture.txt'],
            'ASCII query-delimiter filename' => ['fixture.txt?pad=x', 'fixture.txt?pad=x'],
            'UTF8 path' => ['pasta/Café ç.txt', 'pasta/Café ç.txt'],
            'backslash separators' => ['pasta\\sub\\arquivo.txt', 'pasta/sub/arquivo.txt'],
            'current directory segments' => ['./pasta/./arquivo.txt', 'pasta/arquivo.txt'],
            'internal parent segment' => ['pasta/sub/../arquivo.txt', 'pasta/arquivo.txt'],
        ];
    }

    #[DataProvider('legitimatePaths')]
    public function test_legitimate_paths_preserve_default_normalization(string $input, string $expected): void
    {
        $this->assertSame($expected, (new WhitespacePathNormalizer)->normalizePath($input));
    }

    public function test_parent_traversal_is_refused_independently_of_utf8_validation_and_recovers(): void
    {
        $normalizer = new WhitespacePathNormalizer;

        foreach (['../outside.txt', 'pasta/../../outside.txt'] as $path) {
            $refused = false;

            try {
                $normalizer->normalizePath($path);
            } catch (PathTraversalDetected) {
                $refused = true;
            }

            $this->assertTrue($refused, 'Parent traversal must be refused with PathTraversalDetected.');
            $this->assertSame('safe/fixture.txt', $normalizer->normalizePath('safe/./fixture.txt'));
        }
    }
}
