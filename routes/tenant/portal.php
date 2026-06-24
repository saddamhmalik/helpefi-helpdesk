<?php

declare(strict_types=1);

use App\Domains\Brands\Services\BrandService;
use App\Domains\Auth\Controllers\PortalAuthController;
use App\Domains\Csat\Controllers\PortalCsatController;
use App\Domains\Knowledge\Controllers\PortalController;
use App\Domains\ServiceCatalog\Controllers\PortalServiceCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/portal', fn (BrandService $brands) => redirect()->route('portal.index', ['brand' => $brands->defaultSlug()]));

Route::prefix('portal/{brand:slug}')->middleware(['brand', 'portal.locale'])->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('/collections/{collectionSlug}', [PortalController::class, 'collection'])->name('collection');
    Route::get('/articles/{articleSlug}', [PortalController::class, 'article'])->name('article');
    Route::get('/search', [PortalController::class, 'search'])->name('search');
    Route::get('/submit', [PortalController::class, 'showSubmit'])->name('submit');
    Route::post('/submit', [PortalController::class, 'submit'])->middleware('throttle:10,1')->name('submit.store');
    Route::get('/services', [PortalServiceCatalogController::class, 'index'])->name('services');
    Route::get('/services/{service}', [PortalServiceCatalogController::class, 'show'])->name('services.show');
    Route::post('/services/{service}', [PortalServiceCatalogController::class, 'submit'])->middleware('throttle:10,1')->name('services.submit');
    Route::get('/track', [PortalController::class, 'showTrack'])->name('track');
    Route::post('/track', [PortalController::class, 'track'])->middleware('throttle:10,1')->name('track.lookup');
    Route::post('/csat', [PortalCsatController::class, 'submitGuest'])->name('csat');

    Route::middleware('signed')->prefix('csat/email')->name('csat.email.')->group(function () {
        Route::get('/{ticket}', [PortalCsatController::class, 'showEmailSurvey'])->whereNumber('ticket')->name('survey');
        Route::post('/{ticket}', [PortalCsatController::class, 'submitEmailSurvey'])->whereNumber('ticket')->name('submit');
        Route::get('/{ticket}/rate/{rating}', [PortalCsatController::class, 'quickEmailRate'])->whereNumber(['ticket', 'rating'])->name('rate');
    });

    Route::middleware('guest')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->middleware('throttle:5,1');
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register'])->middleware('throttle:5,1');
    });

    Route::middleware(['auth', 'customer'])->group(function () {
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
        Route::get('/my-tickets', [PortalController::class, 'myTickets'])->name('my-tickets');
        Route::get('/my-tickets/{ticket}', [PortalController::class, 'myTicket'])->whereNumber('ticket')->name('my-tickets.show');
        Route::post('/my-tickets/{ticket}/csat', [PortalCsatController::class, 'submitAuthenticated'])->whereNumber('ticket')->name('my-tickets.csat');
    });
});
