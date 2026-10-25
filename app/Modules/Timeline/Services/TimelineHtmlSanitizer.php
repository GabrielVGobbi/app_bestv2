<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class TimelineHtmlSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'ul',
        'ol',
        'li',
        'blockquote',
        'code',
        'pre',
        'a',
        'h1',
        'h2',
        'h3',
    ];

    public function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
                LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementsByTagName('body')->item(0);

        if (! $loaded || ! $root instanceof DOMElement) {
            return '';
        }

        return trim($this->renderChildren($root));
    }

    private function renderChildren(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $this->renderNode($child);
        }

        return $html;
    }

    private function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style'], true)) {
            return '';
        }

        $children = $this->renderChildren($node);
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return $children;
        }
        if ($tag === 'br') {
            return '<br>';
        }

        $attributes = $tag === 'a' ? $this->linkAttributes($node) : '';

        return '<'.$tag.$attributes.'>'.$children.'</'.$tag.'>';
    }

    private function linkAttributes(DOMElement $element): string
    {
        if (! $element->hasAttribute('href') || ! $this->isSafeHref($element->getAttribute('href'))) {
            return '';
        }
        $href = htmlspecialchars($element->getAttribute('href'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return ' href="'.$href.'" target="_blank" rel="noopener noreferrer"';
    }

    private function isSafeHref(string $href): bool
    {
        $href = strtolower(trim($href));

        return str_starts_with($href, 'http://')
            || str_starts_with($href, 'https://')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, '/');
    }
}
