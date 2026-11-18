<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasPlatformRole(string $role): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return DB::table('platform_role_assignments')
            ->join('roles', 'roles.id', '=', 'platform_role_assignments.role_id')
            ->where('platform_role_assignments.user_id', $this->getKey())
            ->where('roles.scope', 'platform')
            ->where('roles.code', $role)
            ->exists();
    }

    public function isPlatformStaff(): bool
    {
        foreach (['super_admin', 'platform_admin', 'support'] as $role) {
            if ($this->hasPlatformRole($role)) {
                return true;
            }
        }

        return false;
    }
}
