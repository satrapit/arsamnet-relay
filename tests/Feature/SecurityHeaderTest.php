<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeaderTest extends TestCase
{
    public function test_http_response_includes_safe_security_headers(): void
    {
        $this->get('/api/v1/status')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
