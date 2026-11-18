<?php

use App\Http\Controllers\Admin\ClientApiKeyController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientIpAllowlistController;
use App\Http\Controllers\Admin\DeliveryReviewController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\KavenegarAccountController;
use App\Http\Controllers\Admin\SecurityMonitorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/security/setup', 'security.setup')->name('security.setup');
    // Client-owned Kavenegar configuration is intentionally opt-in.
    // Authorization also checks active membership, MFA and Pennant flag.
    Route::get('/account/clients/{client}/providers/kavenegar', [KavenegarAccountController::class, 'index'])
        ->name('account.clients.providers.kavenegar.index');
    Route::post('/account/clients/{client}/providers/kavenegar', [KavenegarAccountController::class, 'save'])
        ->middleware('password.confirm')->name('account.clients.providers.kavenegar.save');
    Route::middleware(['platform.mfa'])->group(function (): void {
        Route::view('/dashboard', 'dashboard.index')->name('dashboard');
        Route::view('/security/recovery-codes', 'security.recovery-codes')
            ->middleware('password.confirm')->name('security.recovery-codes');
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])
            ->middleware('password.confirm')->name('clients.store');
        Route::post('/clients/{client}/keys', [ClientApiKeyController::class, 'store'])
            ->middleware('password.confirm')->name('clients.keys.store');
        Route::post('/clients/{client}/keys/{key}/rotate', [ClientApiKeyController::class, 'rotate'])
            ->middleware('password.confirm')->name('clients.keys.rotate');
        Route::post('/clients/{client}/keys/{key}/revoke', [ClientApiKeyController::class, 'revoke'])
            ->middleware('password.confirm')->name('clients.keys.revoke');
        Route::get('/clients/{client}/delivery-reviews', [DeliveryReviewController::class, 'index'])
            ->name('clients.delivery-reviews.index');
        Route::post('/clients/{client}/delivery-reviews/{delivery}/acknowledge', [DeliveryReviewController::class, 'acknowledge'])
            ->middleware('password.confirm')->name('clients.delivery-reviews.acknowledge');
        Route::get('/clients/{client}/providers/kavenegar', [KavenegarAccountController::class, 'index'])
            ->name('clients.providers.kavenegar.index');
        Route::post('/clients/{client}/providers/kavenegar', [KavenegarAccountController::class, 'save'])
            ->middleware('password.confirm')->name('clients.providers.kavenegar.save');
        Route::get('/clients/{client}/ips', [ClientIpAllowlistController::class, 'index'])
            ->name('clients.ips.index');
        Route::post('/clients/{client}/ips', [ClientIpAllowlistController::class, 'store'])
            ->middleware('password.confirm')->name('clients.ips.store');
        Route::delete('/clients/{client}/ips/{rule}', [ClientIpAllowlistController::class, 'destroy'])
            ->middleware('password.confirm')->name('clients.ips.destroy');
        Route::get('/system/security', [SecurityMonitorController::class, 'index'])
            ->name('security-monitor.index');
        Route::get('/system/feature-flags', [FeatureFlagController::class, 'index'])
            ->name('feature-flags.index');
        Route::post('/system/feature-flags/{key}', [FeatureFlagController::class, 'update'])
            ->middleware('password.confirm')->name('feature-flags.update');
    });
});
