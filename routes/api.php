<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/status', fn () => response()->json([
    'service' => 'arsam-relay',
    'status' => 'ok',
    'version' => '0.1.0-alpha.1',
]));

Route::middleware(['throttle:relay-machine', 'relay.machine'])->group(function (): void {
    Route::get('/v1/me', function (Request $request) {
        return response()->json([
            'data' => [
                'client_id' => $request->attributes->get('relay.client_id'),
                'client_slug' => $request->attributes->get('relay.client_slug'),
                'scopes' => $request->attributes->get('relay.scopes'),
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    });
});
