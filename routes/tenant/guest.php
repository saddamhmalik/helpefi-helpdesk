<?php

declare(strict_types=1);

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Auth\Controllers\PasswordResetController;
use App\Domains\Security\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/auth/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
    Route::post('/auth/sso/acs', [SsoController::class, 'acs'])->name('sso.acs');
    Route::get('/auth/sso/metadata', [SsoController::class, 'metadata'])->name('sso.metadata');
    Route::get('/auth/sso/slo', [SsoController::class, 'slo'])->name('sso.slo');
    Route::get('/welcome', [\App\Domains\Tenancy\Controllers\WelcomeController::class, 'accept'])
        ->name('welcome');
});
