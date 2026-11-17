<?php

use App\Http\Controllers\Admin\FeatureFlagController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/security/setup', 'security.setup')->name('security.setup');
    Route::middleware(['platform.mfa'])->group(function (): void {
        Route::view('/dashboard', 'dashboard.index')->name('dashboard');
        Route::get('/system/feature-flags', [FeatureFlagController::class, 'index'])
            ->name('feature-flags.index');
        Route::post('/system/feature-flags/{key}', [FeatureFlagController::class, 'update'])
            ->middleware('password.confirm')
            ->name('feature-flags.update');
    });
});
