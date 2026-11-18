<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformStaff()) {
            abort(403);
        }

        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return redirect()->route('security.setup');
        }

        return $next($request);
    }
}
