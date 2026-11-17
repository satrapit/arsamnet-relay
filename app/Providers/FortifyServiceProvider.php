<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input('email', '')));
            $user = User::query()->where('email', $email)->first();

            return $user && $user->status === 'active'
                && $user->password && Hash::check((string) $request->input('password'), $user->password)
                    ? $user
                    : null;
        });

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(hash('sha256', Str::lower((string) $request->input('email', '')).'|'.$request->ip())),
            Limit::perMinute(30)->by(hash('sha256', (string) $request->ip())),
        ]);
        RateLimiter::for('two-factor', fn (Request $request) =>
            Limit::perMinute(5)->by(hash('sha256', (string) $request->session()->get('login.id').'|'.$request->ip()))
        );
    }
}
