<?php

namespace App\Console\Commands;

use App\Support\SchemaManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verify deployed MySQL metadata against the proposed DBML without writes.
 * Checks table/column names, data types, required columns, unique/primary
 * indexes and all (including tenant-composite) restricted foreign keys.
 */
final class VerifyDatabaseSchema extends Command
{
    protected $signature = 'relay:db-verify {--expect= : Exact schema name to verify}';

    protected $description = 'Read-only compare the migrated local MySQL schema with docs/ERD.dbml';

    public function handle(): int
    {
        $expectedName = (string) $this->option('expect');
        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $expectedName)) {
            $this->error('Specify an exact database name with --expect=database_name.');
            return self::FAILURE;
        }
        if (!app()->environment('local') || app()->configurationIsCached()) {
            $this->error('Verification requires a local environment and uncached configuration.');
            return self::FAILURE;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This verification requires MySQL, not SQLite or MariaDB.');
            return self::FAILURE;
        }

        try {
            $name = (string) (DB::selectOne('SELECT DATABASE() AS selected_database')?->selected_database ?? '');
            $version = (string) (DB::selectOne('SELECT VERSION() AS server_version')?->server_version ?? '');
            if ($name !== $expectedName
                || str_contains(strtolower($version), 'mariadb')
                || !preg_match('/\A8\.\d+\.\d+(?:\D|$)/', $version)) {
                $this->error('Target schema or MySQL 8 server version does not match.');
                return self::FAILURE;
            }

            $source = file_get_contents(base_path('docs/ERD.dbml'));
            if ($source === false) {
                $this->error('ERD.dbml could not be read.');
                return self::FAILURE;
            }
            $design = SchemaManifest::parse($source);

            $actualTables = DB::select(
                'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            );
            $actualColumns = DB::select(
                'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type, '.
                'CHARACTER_MAXIMUM_LENGTH AS max_length, IS_NULLABLE AS is_nullable '.
                'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            );
            $actualIndexes = DB::select(
                'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, '.
                'SEQ_IN_INDEX AS sequence_no, COLUMN_NAME AS column_name '.
                'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0 '.
                'ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
            );
            $actualForeignKeys = DB::select(
                'SELECT k.TABLE_NAME AS child_table, k.CONSTRAINT_NAME AS constraint_name, '.
                'k.COLUMN_NAME AS child_column, k.REFERENCED_TABLE_NAME AS parent_table, '.
                'k.REFERENCED_COLUMN_NAME AS parent_column, k.ORDINAL_POSITION AS sequence_no, '.
                'r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k '.
                'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '.
                'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME '.
                'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '.
                'WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL '.
                'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
            );
            $migrations = DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable $exception) {
            $this->error('Cannot inspect local schema or parse the ERD: '.$exception->getMessage());
            return self::FAILURE;
        }

        $issues = [];
        $tables = array_map(fn (object $row) => (string) $row->table_name, $actualTables);
        $expectedTables = array_merge(array_keys($design['tables']), ['migrations']);

        foreach (array_diff($expectedTables, $tables) as $table) {
            $issues[] = 'Missing table: '.$table;
        }
        foreach (array_diff($tables, $expectedTables) as $table) {
            $issues[] = 'Unexpected table/view: '.$table;
        }

        $columns = [];
        foreach ($actualColumns as $row) {
            $columns[$row->table_name][$row->column_name] = $row;
        }
        $types = ['boolean' => 'tinyint'];
        foreach ($design['tables'] as $table => $fields) {
            foreach ($fields as $field => $spec) {
                $actual = $columns[$table][$field] ?? null;
                if (!$actual) {
                    $issues[] = 'Missing field: '.$table.'.'.$field;
                    continue;
                }

                $expectedType = $types[$spec['type']] ?? $spec['type'];
                if (strtolower((string) $actual->data_type) !== $expectedType) {
                    $issues[] = 'Type mismatch: '.$table.'.'.$field.' expected '.$expectedType;
                }
                if ($spec['length'] !== null && (int) $actual->max_length !== $spec['length']) {
                    $issues[] = 'Length mismatch: '.$table.'.'.$field;
                }
                if ($spec['required'] && strtoupper((string) $actual->is_nullable) !== 'NO') {
                    $issues[] = 'Required field is nullable: '.$table.'.'.$field;
                }
            }
            foreach (array_diff(array_keys($columns[$table] ?? []), array_keys($fields)) as $extra) {
                $issues[] = 'Unexpected field: '.$table.'.'.$extra;
            }
        }

        $realUnique = [];
        foreach ($actualIndexes as $row) {
            $key = $row->table_name."\0".$row->index_name;
            $realUnique[$key]['table'] = (string) $row->table_name;
            $realUnique[$key]['fields'][] = (string) $row->column_name;
        }
        foreach ($design['unique_indexes'] as $table => $expectedIndexes) {
            foreach ($expectedIndexes as $expectedFields) {
                $found = false;
                foreach ($realUnique as $index) {
                    if ($index['table'] === $table && $index['fields'] === $expectedFields) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $issues[] = 'Missing unique/primary index: '.$table.'('.implode(',', $expectedFields).')';
                }
            }
        }

        $realRefs = [];
        foreach ($actualForeignKeys as $row) {
            $key = $row->child_table."\0".$row->constraint_name;
            $realRefs[$key]['child'] = (string) $row->child_table;
            $realRefs[$key]['parent'] = (string) $row->parent_table;
            $realRefs[$key]['child_cols'][] = (string) $row->child_column;
            $realRefs[$key]['parent_cols'][] = (string) $row->parent_column;
            $realRefs[$key]['delete_rule'] = strtoupper((string) $row->delete_rule);
        }
        $actualRefKeys = [];
        foreach ($realRefs as $ref) {
            $key = SchemaManifest::referenceKey(
                $ref['child'], $ref['child_cols'], $ref['parent'], $ref['parent_cols']
            );
            $actualRefKeys[] = $key;
            if ($ref['delete_rule'] !== 'RESTRICT') {
                $issues[] = 'Unexpected FK deletion rule: '.$key.' ('.$ref['delete_rule'].')';
            }
        }
        foreach (array_diff($design['references'], $actualRefKeys) as $missing) {
            $issues[] = 'Missing FK: '.$missing;
        }
        foreach (array_diff($actualRefKeys, $design['references']) as $extra) {
            $issues[] = 'Unexpected FK: '.$extra;
        }

        $expectedMigrations = [
            '2026_11_17_000000_create_identity_and_security_tables',
            '2026_11_17_000001_create_laravel_runtime_tables',
            '2026_11_17_000002_create_provider_and_subscription_tables',
            '2026_11_17_000003_create_messaging_tables',
        ];
        if (array_diff($expectedMigrations, $migrations) || array_diff($migrations, $expectedMigrations)) {
            $issues[] = 'Migration history differs from the four expected initial migrations.';
        }

        if ($issues !== []) {
            foreach (array_slice($issues, 0, 60) as $issue) {
                $this->error($issue);
            }
            $this->error('FAIL: '.count($issues).' schema discrepancies. Read-only; no changes made.');
            return self::FAILURE;
        }

        $fieldCount = array_sum(array_map('count', $design['tables']));
        $indexCount = array_sum(array_map('count', $design['unique_indexes']));
        $this->info('PASS: '.count($expectedTables).' tables (including migrations), '.
            $fieldCount.' documented fields, '.$indexCount.' unique/primary indexes, '.
            count($design['references']).' restricted FKs and 4 migration records.');
        $this->warn('Read-only verification. Does not prove runtime tenant isolation, column defaults, concurrent transactions or safe rollback.');
        return self::SUCCESS;
    }
}
