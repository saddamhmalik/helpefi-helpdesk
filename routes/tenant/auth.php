<?php

declare(strict_types=1);

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\ProfileController;
use App\Domains\Billing\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('/settings/appearance', [ProfileController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::put('/settings/locale', [ProfileController::class, 'updateLocale'])->name('settings.locale.update');

    Route::get('/subscription-required', [\App\Domains\Billing\Controllers\SubscriptionRequiredController::class, 'show'])
        ->name('subscription.required');

    Route::middleware('admin')->group(function () {
        Route::get('/settings/billing', [BillingController::class, 'index'])->name('settings.billing');
        Route::post('/settings/billing/checkout', [BillingController::class, 'checkout'])->name('settings.billing.checkout');
        Route::post('/settings/billing/razorpay/verify', [BillingController::class, 'verifyRazorpayCheckout'])->name('settings.billing.razorpay.verify');
        Route::put('/settings/billing/plan', [BillingController::class, 'updatePlan'])->name('settings.billing.plan');
        Route::post('/settings/billing/cancel', [BillingController::class, 'cancel'])->name('settings.billing.cancel');
        Route::post('/settings/billing/addons/{addon}', [BillingController::class, 'purchaseAddon'])->name('settings.billing.addons.purchase');
        Route::delete('/settings/billing/addons/{addon}', [BillingController::class, 'cancelAddon'])->name('settings.billing.addons.cancel');
    });

    Route::middleware(['workspace.setup', 'subscription.active'])->group(function () {
        require __DIR__.'/setup.php';
        require __DIR__.'/agent.php';
    });
});
