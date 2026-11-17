<?php

use App\Http\Controllers\Api\PublishMessageController;
use App\Http\Controllers\Api\KavenegarSmsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/status', fn () => response()->json([
    'service' => 'arsam-relay',
    'status' => 'ok',
    'version' => '0.1.0-alpha.1',
]));

Route::middleware(['throttle:relay-machine', 'relay.machine'])->group(function (): void {
    Route::post('/v1/messages', PublishMessageController::class);

    Route::post('/v1/sms/send', [KavenegarSmsController::class, 'single']);
    Route::post('/v1/sms/bulk', [KavenegarSmsController::class, 'bulk']);
    Route::post('/v1/sms/otp', [KavenegarSmsController::class, 'otp']);
    Route::get('/v1/sms/messages/{message}', [KavenegarSmsController::class, 'status']);

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
