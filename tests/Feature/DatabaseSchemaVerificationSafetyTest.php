<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseSchemaVerificationSafetyTest extends TestCase
{
    public function test_schema_verifier_rejects_nonlocal_test_environment(): void
    {
        $this->artisan('relay:db-verify', ['--expect' => 'arsamnet_relay'])
            ->expectsOutputToContain('local environment')
            ->assertExitCode(1);
    }

    public function test_schema_verifier_requires_exact_expected_schema(): void
    {
        $this->artisan('relay:db-verify')
            ->expectsOutputToContain('exact database name')
            ->assertExitCode(1);
    }
}
