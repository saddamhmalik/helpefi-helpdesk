<?php

declare(strict_types=1);

use App\Domains\Api\Controllers\OpenApiController;
use App\Domains\Auth\Controllers\InvitationAcceptController;
use App\Domains\Security\Controllers\TwoFactorController;
use App\Domains\ServiceDesk\Controllers\ApprovalEmailController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/workspace-blocked', [\App\Domains\Tenancy\Controllers\TenantBlockedController::class, 'show'])
    ->name('tenant.blocked');

Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationAcceptController::class, 'accept'])->name('invitations.accept');

Route::get('/two-factor-challenge', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:5,1')->name('two-factor.verify');

Route::get('/api/docs', [OpenApiController::class, 'docs'])->name('api.docs');

Route::middleware('signed')->prefix('approvals/email')->name('approvals.email.')->group(function () {
    Route::get('/{approval}', [ApprovalEmailController::class, 'review'])->name('review');
    Route::post('/{approval}/approve', [ApprovalEmailController::class, 'approveSigned'])->name('approve');
    Route::post('/{approval}/reject', [ApprovalEmailController::class, 'rejectSigned'])->name('reject');
});
