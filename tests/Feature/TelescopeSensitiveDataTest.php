<?php

namespace Tests\Feature;

use Laravel\Telescope\Telescope;
use Tests\TestCase;

class TelescopeSensitiveDataTest extends TestCase
{
    public function test_sensitive_password_request_parameters_are_hidden(): void
    {
        $this->assertContains('current_password', Telescope::$hiddenRequestParameters);
        $this->assertContains('password', Telescope::$hiddenRequestParameters);
        $this->assertContains('password_confirmation', Telescope::$hiddenRequestParameters);
    }

    public function test_sensitive_auth_headers_are_hidden(): void
    {
        $this->assertContains('authorization', Telescope::$hiddenRequestHeaders);
        $this->assertContains('cookie', Telescope::$hiddenRequestHeaders);
        $this->assertContains('php-auth-pw', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-csrf-token', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-xsrf-token', Telescope::$hiddenRequestHeaders);
    }
}
