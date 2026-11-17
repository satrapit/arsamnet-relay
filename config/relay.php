<?php

return [
    'audit_hmac_key' => env('ARSAM_AUDIT_HMAC_KEY'),
    'provision_admin' => (bool) env('ARSAM_PROVISION_ADMIN', false),
    'flags' => [
        'webhook-delivery' => 'Allow signed outbound webhook deliveries',
        'scheduled-messages' => 'Allow publishing messages with a scheduled send time',
        'client-provider-overrides' => 'Allow client-owned provider configurations',
        'provider-status-queries' => 'Allow provider status polling',
    ],
];
