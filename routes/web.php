<?php

use App\Http\Controllers\Admin\ClientApiKeyController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\SecurityMonitorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/security/setup', 'security.setup')->name('security.setup');
    Route::middleware(['platform.mfa'])->group(function (): void {
        Route::view('/dashboard', 'dashboard.index')->name('dashboard');
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])
            ->middleware('password.confirm')->name('clients.store');
        Route::post('/clients/{client}/keys', [ClientApiKeyController::class, 'store'])
            ->middleware('password.confirm')->name('clients.keys.store');
        Route::post('/clients/{client}/keys/{key}/revoke', [ClientApiKeyController::class, 'revoke'])
            ->middleware('password.confirm')->name('clients.keys.revoke');
        Route::get('/system/security', [SecurityMonitorController::class, 'index'])
            ->name('security-monitor.index');
        Route::get('/system/feature-flags', [FeatureFlagController::class, 'index'])
            ->name('feature-flags.index');
        Route::post('/system/feature-flags/{key}', [FeatureFlagController::class, 'update'])
            ->middleware('password.confirm')->name('feature-flags.update');
    });
});
