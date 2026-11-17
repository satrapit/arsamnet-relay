<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only guard before a first migration on an empty, dedicated local MySQL 8 schema.
 *
 * This command never modifies database state, and intentionally refuses to
 * approve existing tables or cached / production configurations.
 */
final class MigrationTargetPreflight extends Command
{
    protected $signature = 'relay:db-preflight {--expect= : Exact name of the disposable destination schema}';

    protected $description = 'Read-only check that a fresh migration targets the correct empty local MySQL 8 database';

    public function handle(): int
    {
        $expected = (string) $this->option('expect');

        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $expected)) {
            $this->error('Provide an explicit --expect=database_name using only ASCII letters, digits, and underscores.');
            return self::FAILURE;
        }

        if (!app()->environment('local')) {
            $this->error('This preflight only approves a local development environment.');
            return self::FAILURE;
        }

        if (app()->configurationIsCached()) {
            $this->error('Configuration is cached. Run php artisan config:clear and retry.');
            return self::FAILURE;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            $this->error('The migration target must use the MySQL driver.');
            return self::FAILURE;
        }

        try {
            $name = (string) (DB::selectOne('SELECT DATABASE() AS selected_database')?->selected_database ?? '');
            $version = (string) (DB::selectOne('SELECT VERSION() AS server_version')?->server_version ?? '');
            $tables = (int) (DB::selectOne(
                'SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE()'
            )?->table_count ?? -1);
        } catch (Throwable) {
            $this->error('Unable to inspect the selected database. Verify the private local DB configuration.');
            return self::FAILURE;
        }

        if ($name !== $expected) {
            $this->error('Actual selected database does not match --expect. No migration was run.');
            return self::FAILURE;
        }

        if (str_contains(strtolower($version), 'mariadb')
            || !preg_match('/\A8\.[0-9]+\.[0-9]+(?:\D|$)/', $version)) {
            $this->error('Expected a MySQL 8.x server; verify server type/version before migration.');
            return self::FAILURE;
        }

        if ($tables !== 0) {
            $this->error("Target contains {$tables} table(s)/view(s); this command only approves an EMPTY schema.");
            return self::FAILURE;
        }

        $this->info("PASS: selected local MySQL 8 schema '{$name}' is empty (0 tables/views).");
        $this->warn('Read-only preflight complete. No migration executed; schema approval and an actual MySQL migration test remain separate.');
        return self::SUCCESS;
    }
}
