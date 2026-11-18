<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Dependency-free, read-only checks of the project's restricted DBML/PHP
 * migration source formats. This is not an official DBML or SQL parser.
 */
final class OfflineDesignChecks
{
    /** @return array{tables: int, fields: int, refs: int} */
    public static function design(string $root): array
    {
        // DBML is the only ERD source: no duplicate CSV dictionary or samples.
        $manifest = self::manifest($root);
        $tables = $manifest['tables'];

        return [
            'tables' => count($tables),
            'fields' => array_sum(array_map('count', $tables)),
            'refs' => count($manifest['references']),
        ];
    }

    /** @return array{tables: int, fields: int} */
    public static function migrationColumns(string $root): array
    {
        $expected = self::manifest($root)['tables'];
        $found = self::parseMigrationTables(self::migrationSource($root));
        $issues = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($found))) as $table) {
            if (! isset($expected[$table])) {
                $issues[] = 'Migration-only table: '.$table;
            } elseif (! isset($found[$table])) {
                $issues[] = 'DBML-only table: '.$table;
            } else {
                foreach (array_diff(array_keys($expected[$table]), $found[$table]) as $field) {
                    $issues[] = "DBML-only field: {$table}.{$field}";
                }
                foreach (array_diff($found[$table], array_keys($expected[$table])) as $field) {
                    $issues[] = "Migration-only field: {$table}.{$field}";
                }
            }
        }
        self::assertNoDrift($issues, 'Migration table/field drift');

        return ['tables' => count($expected), 'fields' => array_sum(array_map('count', $expected))];
    }

    /** @return array{references: int} */
    public static function migrationReferences(string $root): array
    {
        $expected = self::manifest($root)['references'];
        $actual = self::parseMigrationReferences(self::migrationSource($root));
        $issues = [];
        foreach (array_diff($expected, $actual) as $ref) {
            $issues[] = 'DBML-only reference: '.$ref;
        }
        foreach (array_diff($actual, $expected) as $ref) {
            $issues[] = 'Migration-only reference: '.$ref;
        }
        self::assertNoDrift($issues, 'Migration foreign-key drift');

        return ['references' => count($expected)];
    }

    /** @return array<string, list<string>> */
    public static function parseMigrationTables(string $source): array
    {
        $tables = [];
        foreach (self::migrationBlocks($source) as [$table, $body]) {
            $fields = [];
            preg_match_all('/^\s*\$table->([A-Za-z]\w*)\(([^)]*)\)/m', $body, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                [, $method, $args] = $match;
                if (in_array($method, ['primary', 'unique', 'index', 'foreign', 'fullText',
                    'spatialIndex', 'dropColumn', 'dropForeign', 'dropIndex', 'dropUnique'], true)) {
                    continue;
                }
                if ($method === 'rememberToken') {
                    $fields[] = 'remember_token';
                } elseif ($method === 'timestamps') {
                    array_push($fields, 'created_at', 'updated_at');
                } elseif ($method === 'id' && trim($args) === '') {
                    $fields[] = 'id';
                } elseif (preg_match('/^\s*[\'"]([a-z_]+)[\'"]/', $args, $name)) {
                    $fields[] = $name[1];
                } else {
                    throw new InvalidArgumentException("Unsupported migration field: {$table}.{$method}");
                }
            }
            if (count($fields) !== count(array_unique($fields))) {
                throw new InvalidArgumentException('Duplicate migration fields in '.$table);
            }
            $tables[$table] = $fields;
        }

        return $tables;
    }

    /** @return list<string> */
    public static function parseMigrationReferences(string $source): array
    {
        $references = [];
        foreach (self::migrationBlocks($source) as [$table, $body]) {
            foreach (preg_split('/\R/', $body) as $line) {
                if (! str_contains($line, '->constrained(') && ! str_contains($line, '$table->foreign(')) {
                    continue;
                }
                if (preg_match('/\$table->foreignId\(\'([a-z_]+)\'\)(?:->nullable\(\))?->constrained\(\'([a-z_]+)\'\)->restrictOnDelete\(\)/', $line, $match)) {
                    $references[] = SchemaManifest::referenceKey($table, [$match[1]], $match[2], ['id']);

                    continue;
                }
                if (preg_match('/\$table->foreign\((.*?)\)->references\((.*?)\)->on\(\'([a-z_]+)\'\)->restrictOnDelete\(\)/', $line, $match)) {
                    $references[] = SchemaManifest::referenceKey($table, self::phpColumns($match[1]),
                        $match[3], self::phpColumns($match[2]));

                    continue;
                }
                throw new InvalidArgumentException('Unsupported FK declaration in '.$table.': '.trim($line));
            }
        }
        if (count($references) !== count(array_unique($references))) {
            throw new InvalidArgumentException('Duplicate migration foreign-key declaration.');
        }

        return $references;
    }

    /** @return array{tables: array, references: list<string>, unique_indexes: array, ordinary_indexes: array} */
    private static function manifest(string $root): array
    {
        $source = @file_get_contents($root.'/docs/ERD.dbml');
        if ($source === false) {
            throw new RuntimeException('Cannot read docs/ERD.dbml.');
        }

        return SchemaManifest::parse($source);
    }

    private static function migrationSource(string $root): string
    {
        $files = glob($root.'/database/migrations/*.php');
        if ($files === false || $files === []) {
            throw new RuntimeException('No PHP migrations found.');
        }
        sort($files);
        $all = [];
        foreach ($files as $path) {
            $source = @file_get_contents($path);
            if ($source === false) {
                throw new RuntimeException('Could not read migration: '.basename($path));
            }
            $all[] = $source;
        }

        return implode("\n", $all);
    }

    /** @return list<array{string, string}> */
    private static function migrationBlocks(string $source): array
    {
        if (str_contains($source, 'Schema::table(')) {
            throw new InvalidArgumentException('Schema::table alterations require parser review.');
        }
        preg_match_all('/Schema::create\([\'"]([a-z_]+)[\'"],\s*function\s*\(\s*Blueprint\s+\$table\s*\)\s*:\s*void\s*\{([\s\S]*?)^\s{8}\}\);/m',
            $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== substr_count($source, 'Schema::create(')) {
            throw new InvalidArgumentException('Unsupported Schema::create syntax: review parser.');
        }
        $blocks = [];
        $names = [];
        foreach ($matches as $match) {
            if (isset($names[$match[1]])) {
                throw new InvalidArgumentException('Duplicate migration table: '.$match[1]);
            }
            $names[$match[1]] = true;
            $blocks[] = [$match[1], $match[2]];
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function phpColumns(string $expression): array
    {
        $trimmed = trim($expression);
        if (preg_match('/^\'([a-z_]+)\'$/', $trimmed, $single)) {
            return [$single[1]];
        }
        if (! preg_match('/^\[\s*\'[a-z_]+\'(?:\s*,\s*\'[a-z_]+\')*\s*\]$/', $trimmed)) {
            throw new InvalidArgumentException('Unsupported FK columns: '.$trimmed);
        }
        preg_match_all('/\'([a-z_]+)\'/', $trimmed, $matches);

        return $matches[1];
    }

    /** @param list<string> $issues */
    private static function assertNoDrift(array $issues, string $context): void
    {
        if ($issues !== []) {
            throw new InvalidArgumentException($context.":\n".implode("\n", $issues));
        }
    }
}
