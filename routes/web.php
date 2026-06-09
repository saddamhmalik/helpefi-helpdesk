<?php

use App\Domains\Tenancy\Controllers\Central\HomeController;
use App\Domains\Tenancy\Controllers\Central\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('central.home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('central.register');
    Route::post('/register', [RegisterController::class, 'store'])->name('central.register.store');
});
