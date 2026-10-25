<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlLimitCompatibilityTest extends TestCase
{
    public function test_static_parse_refuses_inline_collections_beyond_the_depth_limit(): void
    {
        $this->expectException(ParseException::class);

        Yaml::parse('[[[Café]]]', 0, 2, 1);
    }

    public function test_static_parse_refuses_collection_aliases_beyond_the_alias_limit(): void
    {
        $this->expectException(ParseException::class);

        Yaml::parse("base: &items [Café]\nfirst: *items\nsecond: *items\n", 0, 2, 1);
    }

    public function test_parser_recovers_after_refusing_excessive_inline_depth(): void
    {
        $parser = new Parser(2, 1);
        $refused = false;

        try {
            $parser->parse('[[[[Café]]]]');
        } catch (ParseException) {
            $refused = true;
        }

        $recovered = $parser->parse("label: Café ç\nitems: [um, dois]\n");

        $this->assertTrue($refused, 'The parser must enforce the configured nesting limit.');
        $this->assertSame(['label' => 'Café ç', 'items' => ['um', 'dois']], $recovered);
        $this->assertSame(['Recuperação'], $parser->parse('[Recuperação]'));
    }

    public function test_parser_recovers_after_refusing_excessive_collection_aliases(): void
    {
        $parser = new Parser(2, 1);
        $refused = false;

        try {
            $parser->parse("base: &items [Café]\nfirst: *items\nsecond: *items\n");
        } catch (ParseException) {
            $refused = true;
        }

        $validDocument = "base: &items [Café]\ncopy: *items\n";
        $expected = ['base' => ['Café'], 'copy' => ['Café']];

        $this->assertTrue($refused, 'The parser must enforce the configured collection alias limit.');
        $this->assertSame($expected, $parser->parse($validDocument));
        $this->assertSame($expected, $parser->parse($validDocument));
    }
}
