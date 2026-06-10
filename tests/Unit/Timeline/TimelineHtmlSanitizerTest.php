<?php

declare(strict_types=1);

namespace Tests\Unit\Timeline;

use App\Modules\Timeline\Services\TimelineHtmlSanitizer;
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
}
