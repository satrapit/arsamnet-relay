<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input('email', '')));
            $user = User::query()->where('email', $email)->first();

            // Perform one password verification even when the user is absent or suspended.
            // This dummy value is a public sentinel hash, never an account credential.
            $dummyHash = '$2y$12$O0Lxs5GcV5AAw/l.Bi1unu9L8pTtpT0keBGT6dnbqVyJhleGafIpW';
            $hash = $user?->password ?: $dummyHash;
            $validPassword = Hash::check((string) $request->input('password'), $hash);

            return $user && $user->status === 'active' && $user->password && $validPassword
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
