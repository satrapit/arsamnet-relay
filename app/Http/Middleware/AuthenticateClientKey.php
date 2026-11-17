<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = (string) $request->bearerToken();
        if (!preg_match('/\Arelay_[a-f0-9]{64}\z/D', $raw)) {
            return $this->deny();
        }

        $key = DB::table('api_keys')
            ->join('clients', 'clients.id', '=', 'api_keys.client_id')
            ->where('api_keys.key_hash', hash('sha256', $raw))
            ->where('clients.status', 'active')
            ->whereNull('api_keys.revoked_at')
            ->where(function ($query): void {
                $query->whereNull('api_keys.expires_at')->orWhere('api_keys.expires_at', '>', now()->utc());
            })
            ->select('api_keys.*', 'clients.slug as client_slug')
            ->first();

        if (!$key || !$this->allowedIp((int) $key->client_id, $request->ip())) {
            return $this->deny();
        }

        $scopes = json_decode((string) $key->scopes, true);
        if (!is_array($scopes)) {
            return $this->deny();
        }

        $request->attributes->set('relay.client_id', (int) $key->client_id);
        $request->attributes->set('relay.client_slug', (string) $key->client_slug);
        $request->attributes->set('relay.key_id', (int) $key->id);
        $request->attributes->set('relay.scopes', $scopes);

        return $next($request);
    }

    private function allowedIp(int $clientId, ?string $ip): bool
    {
        $ranges = DB::table('client_ip_allowlist')
            ->where('client_id', $clientId)->where('enabled', true)->pluck('cidr');
        if ($ranges->isEmpty()) {
            return true;
        }

        if (!$ip) {
            return false;
        }

        foreach ($ranges as $cidr) {
            if ($this->matchesCidr($ip, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function matchesCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            return false;
        }

        $remote = @inet_pton($ip);
        $network = @inet_pton($parts[0]);
        if ($remote === false || $network === false || strlen($remote) !== strlen($network)) {
            return false;
        }

        $mask = (int) $parts[1];
        $maxBits = strlen($remote) * 8;
        if ($mask < 0 || $mask > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remaining = $mask % 8;
        if (substr($remote, 0, $fullBytes) !== substr($network, 0, $fullBytes)) {
            return false;
        }

        return $remaining === 0
            || ((ord($remote[$fullBytes]) ^ ord($network[$fullBytes])) & (0xff << (8 - $remaining))) === 0;
    }

    private function deny(): Response
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Valid client credentials are required.',
        ], 401, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }
}
