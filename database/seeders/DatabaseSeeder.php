<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Do not seed default accounts or passwords.
 *
 * The first platform administrator is provisioned explicitly through the
 * guarded relay:provision-admin command, never as a public example user.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty: no default users or credentials.
    }
}
