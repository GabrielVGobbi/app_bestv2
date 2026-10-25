<?php

declare(strict_types=1);

namespace Tests\Unit\Timeline;

use App\Modules\Timeline\Services\TimelineHtmlSanitizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TimelineHtmlSanitizerTest extends TestCase
{
    public function test_sanitizer_keeps_safe_rich_text_markup(): void
    {
        $html = (new TimelineHtmlSanitizer)->clean(
            '<h2>Titulo</h2><p><strong>Negrito</strong> e <em>italico</em></p><ul><li>Item</li></ul>',
        );

        $this->assertSame(
            '<h2>Titulo</h2><p><strong>Negrito</strong> e <em>italico</em></p><ul><li>Item</li></ul>',
            $html,
        );
    }

    public function test_sanitizer_removes_unsafe_tags_attributes_and_urls(): void
    {
        $html = (new TimelineHtmlSanitizer)->clean(
            '<p onclick="alert(1)">Oi<script>alert(1)</script><img src=x><a href="javascript:alert(1)" style="color:red">link</a></p>',
        );

        $this->assertSame('<p>Oi<a>link</a></p>', $html);
    }

    public function test_sanitizer_normalizes_safe_links(): void
    {
        $html = (new TimelineHtmlSanitizer)->clean(
            '<p><a href="https://example.com" onclick="alert(1)">site</a></p>',
        );

        $this->assertSame(
            '<p><a href="https://example.com" target="_blank" rel="noopener noreferrer">site</a></p>',
            $html,
        );
    }

    /** @return array<string, array{string, string}> */
    public static function promotedDescendants(): array
    {
        return [
            'reconstructed historical onerror class' => ['<div><img src=x onerror="alert(1)"><strong>Texto</strong></div>', '<strong>Texto</strong>'],
            'reconstructed historical javascript class' => ['<section><a href="javascript:alert(1)" onclick="alert(2)">link</a></section>', '<a>link</a>'],
            'multiple removed ancestors' => ['<div><section><article><p onclick="alert(1)"><em>Texto</em><img src=x onerror="alert(2)"></p></article></section></div>', '<p><em>Texto</em></p>'],
            'script and style under removed ancestor' => ['<section><script>alert(1)</script><style>body{display:none}</style><p>Texto</p></section>', '<p>Texto</p>'],
        ];
    }

    #[DataProvider('promotedDescendants')]
    public function test_removed_ancestors_never_promote_unsanitized_descendants(string $input, string $expected): void
    {
        $sanitizer = new TimelineHtmlSanitizer;
        $output = $sanitizer->clean($input);

        $this->assertSame($expected, $output);
        $this->assertAllowedOutput($output);
        $this->assertSame($output, $sanitizer->clean($output));
    }

    /** @return array<string, array{string}> */
    public static function malformedAndForeignMarkup(): array
    {
        return [
            'unclosed removed elements' => ['<section><div><p>Texto<img src=x onerror="alert(1)"><a href="javascript:alert(2)">link'],
            'closing synthetic wrapper' => ['</div><section><img src=x onerror="alert(1)"><p>Texto</p></section>'],
            'svg foreign content' => ['<svg onload="alert(1)"><foreignObject><p onclick="alert(2)">Texto<img src=x onerror="alert(3)"></p></foreignObject><a xlink:href="javascript:alert(4)">link</a></svg>'],
            'math style mutation' => ['<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(1)>">Texto'],
            'raw text ancestors' => ['<section><textarea><img src=x onerror="alert(1)"></textarea><xmp><a href="javascript:alert(2)">link</a></xmp><p>Texto</p></section>'],
            'comments and processing instructions' => ['<section><!-- <img src=x onerror=alert(1)> --><?unsafe instruction?><p onclick="alert(2)">Texto</p></section>'],
        ];
    }

    #[DataProvider('malformedAndForeignMarkup')]
    public function test_malformed_and_foreign_markup_has_only_allowed_nodes_after_reparse(string $input): void
    {
        $sanitizer = new TimelineHtmlSanitizer;
        $output = $sanitizer->clean($input);

        $this->assertAllowedOutput($output);
        $this->assertSame($output, $sanitizer->clean($output));
    }

    /** @return array<string, array{string}> */
    public static function supportedLinks(): array
    {
        return [
            'https' => ['https://example.com/path?q=1'],
            'http' => ['http://example.com'],
            'mail' => ['mailto:user@example.com'],
            'root relative' => ['/clients/123'],
            'protocol relative existing contract' => ['//example.com/path'],
        ];
    }

    #[DataProvider('supportedLinks')]
    public function test_supported_link_contract_is_preserved_under_removed_ancestors(string $href): void
    {
        $output = (new TimelineHtmlSanitizer)->clean('<section><a href="'.$href.'" target="opener" rel="opener" style="color:red">link</a></section>');

        $this->assertSame('<a href="'.$href.'" target="_blank" rel="noopener noreferrer">link</a>', $output);
        $this->assertAllowedOutput($output);
    }

    /** @return array<string, array{string}> */
    public static function unsafeLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'entity scheme' => ['java&#x73;cript:alert(1)'],
            'control in scheme' => ['java&#x09;script:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
        ];
    }

    #[DataProvider('unsafeLinks')]
    public function test_unsafe_link_loses_navigation_attributes(string $href): void
    {
        $output = (new TimelineHtmlSanitizer)->clean('<div><a href="'.$href.'" target="opener" rel="opener" onclick="alert(2)">link</a></div>');

        $this->assertSame('<a>link</a>', $output);
        $this->assertAllowedOutput($output);
    }

    public function test_utf8_and_escaped_markup_remain_text_after_reparse(): void
    {
        $sanitizer = new TimelineHtmlSanitizer;
        $output = $sanitizer->clean('<p>Ação 😀 &lt;img src=x onerror=alert(1)&gt; &amp; &quot;aspas&quot;</p>');
        $root = $this->assertAllowedOutput($output);

        $this->assertSame('Ação 😀 <img src=x onerror=alert(1)> & "aspas"', $root->textContent);
        $this->assertCount(0, $root->getElementsByTagName('img'));
        $this->assertSame($output, $sanitizer->clean($output));
    }

    public function test_full_existing_rich_text_allowlist_is_preserved(): void
    {
        $input = '<h1>Um</h1><h2>Dois</h2><h3>Tres</h3><p><strong>Forte</strong><b>Negrito</b><em>Enfase</em><i>Italico</i><u>Sublinhado</u><s>Riscado</s><br></p><ol><li>Primeiro</li></ol><ul><li>Item</li></ul><blockquote><p>Citacao</p></blockquote><pre><code>x &lt; y</code></pre>';
        $sanitizer = new TimelineHtmlSanitizer;
        $output = $sanitizer->clean($input);

        $this->assertSame($input, $output);
        $this->assertAllowedOutput($output);
        $this->assertSame($output, $sanitizer->clean($output));
    }

    /** @return array<string, array{bool}> */
    public static function libxmlModes(): array
    {
        return ['external errors' => [false], 'internal errors' => [true]];
    }

    #[DataProvider('libxmlModes')]
    public function test_sanitizer_restores_previous_libxml_error_mode(bool $internalErrors): void
    {
        $previous = libxml_use_internal_errors($internalErrors);
        try {
            (new TimelineHtmlSanitizer)->clean('<section><p>Texto');
            $this->assertSame($internalErrors, libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    private function assertAllowedOutput(string $output): DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<html><head><meta charset="UTF-8"></head><body>'.$output.'</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $this->assertTrue($loaded);
        $root = $document->getElementsByTagName('body')->item(0);
        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertAllowedChildren($root);

        return $root;
    }

    private function assertAllowedChildren(DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            $this->assertInstanceOf(DOMElement::class, $child);
            $tag = strtolower($child->tagName);
            $this->assertContains($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'h1', 'h2', 'h3']);
            $this->assertNull($child->namespaceURI);
            foreach ($child->attributes as $attribute) {
                $this->assertSame('a', $tag);
                $this->assertContains(strtolower($attribute->name), ['href', 'target', 'rel']);
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $this->assertMatchesRegularExpression('~\A(?:https?://|mailto:|/)~i', trim($child->getAttribute('href')));
                $this->assertSame('_blank', $child->getAttribute('target'));
                $this->assertSame('noopener noreferrer', $child->getAttribute('rel'));
            } elseif ($tag === 'a') {
                $this->assertFalse($child->hasAttribute('target'));
                $this->assertFalse($child->hasAttribute('rel'));
            }
            $this->assertAllowedChildren($child);
        }
    }
}
