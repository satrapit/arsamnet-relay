<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBootstrapSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_does_not_install_a_predictable_user(): void
    {
        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\DatabaseSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_migration_preflight_rejects_non_local_environment(): void
    {
        $this->artisan('relay:db-preflight', ['--expect' => 'arsamnet_relay'])
            ->expectsOutputToContain('local development environment')
            ->assertExitCode(1);
    }

    public function test_migration_preflight_requires_an_explicit_database_name(): void
    {
        $this->artisan('relay:db-preflight')
            ->expectsOutputToContain('explicit --expect')
            ->assertExitCode(1);
    }
}
