<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SecurityEventRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('feature-flags.write', fn (User $user): bool => $user->hasPlatformRole('super_admin'));
        Gate::define('feature-flags.view', fn (User $user): bool => $user->hasPlatformRole('super_admin') || $user->hasPlatformRole('platform_admin')
        );
        Gate::define('clients.manage', fn (User $user): bool => $user->hasPlatformRole('super_admin') || $user->hasPlatformRole('platform_admin')
        );
        Gate::define('kavenegar.client-configure', function (User $user, int $clientId): bool {
            if ($user->status !== 'active'
                || ! $user->two_factor_confirmed_at
                || ! $user->two_factor_secret
                || ! Feature::active('client-provider-overrides')) {
                return false;
            }

            return \Illuminate\Support\Facades\DB::table('client_memberships as memberships')
                ->join('roles', 'roles.id', '=', 'memberships.role_id')
                ->join('clients', 'clients.id', '=', 'memberships.client_id')
                ->where('memberships.client_id', $clientId)
                ->where('memberships.user_id', $user->getKey())
                ->where('memberships.status', 'active')
                ->where('clients.status', 'active')
                ->where('roles.scope', 'client')
                ->whereIn('roles.code', ['owner', 'client_admin'])
                ->exists();
        });
        Gate::define('security.monitor', fn (User $user): bool => $user->hasPlatformRole('super_admin') || $user->hasPlatformRole('platform_admin')
        );
        Gate::define('dashboard.view', fn (User $user): bool => $user->isPlatformStaff());

        Event::listen(Failed::class, function (Failed $event): void {
            $id = $event->user?->getAuthIdentifier();
            SecurityEventRecorder::authenticationFailure('auth.login.failed', $id === null ? null : (int) $id);
        });
        Event::listen(Lockout::class, function (Lockout $event): void {
            SecurityEventRecorder::authenticationFailure('auth.login.locked');
        });
        Event::listen(TwoFactorAuthenticationFailed::class, function (TwoFactorAuthenticationFailed $event): void {
            SecurityEventRecorder::authenticationFailure('auth.mfa.failed', (int) $event->user->getAuthIdentifier());
        });

        RateLimiter::for('relay-machine', fn (Request $request) => [
            Limit::perMinute(120)->by(hash('sha256', (string) $request->ip())),
            Limit::perMinute(60)->by(hash('sha256', (string) $request->bearerToken())),
        ]);
        foreach (array_keys(config('relay.flags', [])) as $feature) {
            Feature::define($feature, fn (): bool => false);
        }
    }
}
