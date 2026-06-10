<?php

namespace Tests\Feature;

use Tests\TestCase;

class RuneyBootShellTest extends TestCase
{
    public function test_initial_html_includes_runey_boot_shell(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('id="boot-shell"', false)
            ->assertSee('id="boot-loading"', false)
            ->assertSee('Rendering...', false)
            ->assertSee('id="noscript-notice"', false)
            ->assertSee('boot-error', false);
    }
}
