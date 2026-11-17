<?php

use Illuminate\Support\Facades\Route;

// This endpoint provides no tenancy, queue, or provider internals.
Route::get('/v1/status', fn () => response()->json([
    'service' => 'arsam-relay',
    'status' => 'ok',
    'version' => '0.1.0-alpha.1',
]));
