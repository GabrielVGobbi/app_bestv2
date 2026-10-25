<?php

namespace Tests\Unit;

use Illuminate\Mail\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTableCompatibilityTest extends TestCase
{
    public function test_single_column_header_without_a_pipe_is_recognized_as_a_table(): void
    {
        $html = Markdown::parse("Título\n| --- |\n| Café |\n")->toHtml();

        $this->assertSame(1, substr_count($html, '<table>'));
        $this->assertStringContainsString('<th>Título</th>', $html);
        $this->assertStringContainsString('<td>Café</td>', $html);
        $this->assertStringNotContainsString('<p>Título', $html);
    }

    public function test_header_and_delimiter_column_counts_must_match(): void
    {
        $html = Markdown::parse("Nome | Nota\n| --- | --- | --- |\nCafé | forte")->toHtml();

        $this->assertStringNotContainsString('<table>', $html);
        $this->assertStringNotContainsString('<thead>', $html);
        $this->assertStringContainsString('<p>', $html);
        $this->assertStringContainsString('Nome | Nota', $html);
        $this->assertStringContainsString('Café | forte', $html);
    }

    public function test_legitimate_tables_preserve_utf8_formatting_alignment_and_escaped_pipes(): void
    {
        $html = Markdown::parse("| Nome | Nota |\n| :--- | ---: |\n| Café \\| chá | **Ótimo** |\n")->toHtml();

        $this->assertSame(1, substr_count($html, '<table>'));
        $this->assertSame(2, substr_count($html, '<th '));
        $this->assertSame(2, substr_count($html, '<td '));
        $this->assertStringContainsString('<th align="left">Nome</th>', $html);
        $this->assertStringContainsString('<th align="right">Nota</th>', $html);
        $this->assertStringContainsString('<td align="left">Café | chá</td>', $html);
        $this->assertStringContainsString('<td align="right"><strong>Ótimo</strong></td>', $html);
    }

    public function test_small_core_markdown_preserves_text_code_references_and_formatting(): void
    {
        $html = Markdown::parse("Primeira linha ç.\nSegunda linha & simples.\nTerceira linha.")->toHtml();

        $this->assertSame("<p>Primeira linha ç.\nSegunda linha &amp; simples.\nTerceira linha.</p>\n", $html);
        $this->assertStringNotContainsString('<table>', $html);

        $formatted = Markdown::parse("```text\nCafé & chá\n```\n\n[Referência][fixture] com **forte** e *ênfase*.\n\n[fixture]: https://example.test\n")->toHtml();

        $this->assertStringContainsString("<pre><code class=\"language-text\">Café &amp; chá\n</code></pre>", $formatted);
        $this->assertStringContainsString('<a href="https://example.test">Referência</a>', $formatted);
        $this->assertStringContainsString('<strong>forte</strong>', $formatted);
        $this->assertStringContainsString('<em>ênfase</em>', $formatted);
    }

    public function test_a_fresh_valid_conversion_recovers_after_non_table_markdown(): void
    {
        $ordinary = Markdown::parse('Texto comum sem tabela.')->toHtml();
        $this->assertSame("<p>Texto comum sem tabela.</p>\n", $ordinary);

        $recovery = Markdown::parse("| Estado | Valor |\n| --- | --- |\n| Recuperação ç | íntegro |\n")->toHtml();

        $this->assertSame(1, substr_count($recovery, '<table>'));
        $this->assertStringContainsString('<th>Estado</th>', $recovery);
        $this->assertStringContainsString('<th>Valor</th>', $recovery);
        $this->assertStringContainsString('<td>Recuperação ç</td>', $recovery);
        $this->assertStringContainsString('<td>íntegro</td>', $recovery);
    }
}
