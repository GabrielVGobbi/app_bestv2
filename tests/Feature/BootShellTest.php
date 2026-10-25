<?php

namespace Tests\Feature;

use Tests\TestCase;

class BootShellTest extends TestCase
{
    public function test_initial_html_includes_the_brand_boot_shell(): void
    {
        config(['branding.hue' => 30, 'branding.name' => 'Minha Marca']);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('style="--brand-hue: 30"', false)
            ->assertSee('<title>Minha Marca</title>', false)
            ->assertSee('href="/brand/favicon.svg"', false)
            ->assertSee('id="boot-shell"', false)
            ->assertSee('aria-label="Minha Marca"', false)
            ->assertSee('id="boot-loading"', false)
            ->assertSee('Carregando...', false)
            ->assertSee('id="noscript-notice"', false)
            ->assertSee('Esta página precisa de JavaScript.', false)
            ->assertSee('boot-error', false)
            ->assertDontSee('Runey', false)
            ->assertDontSee('Poppins', false)
            ->assertDontSee('href="/favicon.svg"', false);
    }

    public function test_boot_shell_canvas_uses_the_app_css_background_formulas(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $background = function (string $selector) use ($css): string {
            preg_match('~(?:^|\n)'.preg_quote($selector, '~').'\s*\{.*?--background:\s*([^;]+);~s', $css, $match);

            return $match[1];
        };
        $blade = function (string $selector) use ($html): string {
            preg_match('~\n\s*'.preg_quote($selector, '~').'\s*\{[^}]*?background(?:-color)?:\s*([^;]+);~', $html, $match);

            return str_replace('var(--brand-hue, 215)', 'var(--brand-hue)', $match[1] ?? 'ausente');
        };

        $this->assertSame($background(':root'), $blade('html'));
        $this->assertSame($background(':root'), $blade('#boot-shell'));
        $this->assertSame($background('.dark'), $blade('html.dark'));
        $this->assertSame($background('.dark'), $blade('html.dark #boot-shell'));
    }
}
