<?php

declare(strict_types=1);

use App\Domains\Tenancy\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/setup', [SetupController::class, 'index'])->name('setup');
    Route::post('/setup/steps/{step}', [SetupController::class, 'completeStep'])->name('setup.steps.complete');
    Route::post('/setup/finish', [SetupController::class, 'finish'])->name('setup.finish');
    Route::post('/setup/dummy-data', [\App\Domains\Tenancy\Controllers\TenantDummyDataController::class, 'store'])->name('setup.dummy-data.store');
    Route::post('/setup/dummy-data/skip', [\App\Domains\Tenancy\Controllers\TenantDummyDataController::class, 'skip'])->name('setup.dummy-data.skip');
    Route::delete('/setup/dummy-data', [\App\Domains\Tenancy\Controllers\TenantDummyDataController::class, 'destroy'])->name('setup.dummy-data.destroy');
    Route::delete('/setup/bootstrap-demo', [\App\Domains\Tenancy\Controllers\TenantDummyDataController::class, 'destroyBootstrap'])->name('setup.bootstrap-demo.destroy');
});
