<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('dashboard.view', fn (User $user): bool => $user->isPlatformStaff());

        foreach (array_keys(config('relay.flags', [])) as $feature) {
            Feature::define($feature, fn (): bool => false);
        }
    }
}
