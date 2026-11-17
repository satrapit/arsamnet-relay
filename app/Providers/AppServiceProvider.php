<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        Gate::define('feature-flags.view', fn (User $user): bool =>
            $user->hasPlatformRole('super_admin') || $user->hasPlatformRole('platform_admin')
        );
        Gate::define('clients.manage', fn (User $user): bool =>
            $user->hasPlatformRole('super_admin') || $user->hasPlatformRole('platform_admin')
        );
        Gate::define('dashboard.view', fn (User $user): bool => $user->isPlatformStaff());

        RateLimiter::for('relay-machine', fn (Request $request) => [
            Limit::perMinute(120)->by(hash('sha256', (string) $request->ip())),
            Limit::perMinute(60)->by(hash('sha256', (string) $request->bearerToken())),
        ]);
        foreach (array_keys(config('relay.flags', [])) as $feature) {
            Feature::define($feature, fn (): bool => false);
        }
    }
}
