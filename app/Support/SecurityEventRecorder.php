<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class SecurityEventRecorder
{
    /**
     * Non-privileged authentication failures must retain their original response
     * even if auditing is unavailable. Privileged writes use SecurityAudit directly
     * and fail closed instead.
     */
    public static function authenticationFailure(string $code, ?int $userId = null): void
    {
        try {
            SecurityAudit::record($code, 'warning', $userId, [], null, request()->ip());
        } catch (Throwable) {
            Log::critical('auth_security_audit_unavailable', ['event_code' => $code]);
        }
    }
}
