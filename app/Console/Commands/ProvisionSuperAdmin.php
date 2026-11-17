<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'relay:provision-admin {email : Verified email under your control} {--name= : Display name}';
    protected $description = 'Provision the first Super Admin from an explicitly enabled secure CLI session';

    public function handle(): int
    {
        if (!config('relay.provision_admin')) {
            $this->error('Admin provisioning disabled. Explicitly set ARSAM_PROVISION_ADMIN=true for this operation only.');

            return self::FAILURE;
        }

        if (DB::table('platform_role_assignments')
            ->join('roles', 'roles.id', '=', 'platform_role_assignments.role_id')
            ->where('roles.scope', 'platform')->where('roles.code', 'super_admin')->exists()) {
            $this->error('A Super Admin already exists. Automatic privilege escalation is prohibited.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = (string) ($this->option('name') ?: $email);
        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account already uses this email. Manual review is required.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New administrator password (never a CLI argument)');
        $confirmation = (string) $this->secret('Confirm administrator password');
        Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        DB::transaction(function () use ($email, $name, $password): void {
            $roleId = DB::table('roles')->where('scope', 'platform')->where('code', 'super_admin')->value('id');
            if (!$roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'scope' => 'platform', 'code' => 'super_admin', 'created_at' => now()->utc(),
                ]);
            }

            $user = User::create([
                'name' => $name, 'email' => $email, 'password' => $password,
            ]);
            $user->forceFill(['status' => 'active', 'email_verified_at' => now()->utc()])->save();

            DB::table('platform_role_assignments')->insert([
                'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now()->utc(),
            ]);
            SecurityAudit::record('admin.role.changed', 'high', $user->id, [
                'role_code' => 'super_admin', 'target_user_id' => $user->id,
            ]);
        });

        $this->info('Administrator created. Disable ARSAM_PROVISION_ADMIN and enroll TOTP before accessing the dashboard.');

        return self::SUCCESS;
    }
}
