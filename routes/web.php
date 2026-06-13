<?php

use App\Domains\Ai\Controllers\Central\MarketingAiDemoController;
use App\Domains\Billing\Controllers\RazorpayWebhookController;
use App\Domains\Platform\Controllers\Central\AdminAuditLogController;
use App\Domains\Platform\Controllers\Central\AdminBackupController;
use App\Domains\Platform\Controllers\Central\AdminDashboardController;
use App\Domains\Platform\Controllers\Central\AdminEmailTemplateController;
use App\Domains\Platform\Controllers\Central\AdminFeedbackController;
use App\Domains\Platform\Controllers\Central\AdminLoginController;
use App\Domains\Platform\Controllers\Central\AdminNoticeController;
use App\Domains\Platform\Controllers\Central\AdminObservabilityController;
use App\Domains\Platform\Controllers\Central\AdminPendingRegistrationController;
use App\Domains\Platform\Controllers\Central\AdminProfileController;
use App\Domains\Platform\Controllers\Central\AdminRoleController;
use App\Domains\Platform\Controllers\Central\AdminSubscriptionController;
use App\Domains\Platform\Controllers\Central\AdminTenantController;
use App\Domains\Platform\Controllers\Central\AdminUserController;
use App\Domains\Platform\Controllers\Central\PlatformNoticeImageController;
use App\Domains\Tenancy\Controllers\Central\AdminSettingsController;
use App\Domains\Tenancy\Controllers\Central\HomeController;
use App\Domains\Tenancy\Controllers\Central\LoginController;
use App\Domains\Tenancy\Controllers\Central\RegisterController;
use App\Domains\Tenancy\Controllers\Central\RobotsController;
use App\Domains\Tenancy\Controllers\Central\SitemapController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/razorpay/webhook', RazorpayWebhookController::class)->name('razorpay.webhook');

Route::get('/platform-notices/{notice}/image', PlatformNoticeImageController::class)
    ->middleware('signed')
    ->name('central.notices.image');

Route::get('/robots.txt', RobotsController::class)->name('central.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('central.sitemap');
Route::get('/', [HomeController::class, 'index'])->name('central.home');

Route::post('/api/marketing/ai-demo', [MarketingAiDemoController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('central.marketing.ai-demo');

Route::get('/dashboard', function () {
    if (Auth::guard('platform')->check()) {
        return redirect()->route('central.admin.dashboard');
    }

    return redirect()->route('central.admin.login');
})->name('central.dashboard');

Route::get('/admin', function () {
    if (Auth::guard('platform')->check()) {
        return redirect()->route('central.admin.dashboard');
    }

    return redirect()->route('central.admin.login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('central.login');
    Route::post('/login', [LoginController::class, 'redirect'])->name('central.login.redirect');
    Route::get('/register', [RegisterController::class, 'create'])->name('central.register');
    Route::post('/register', [RegisterController::class, 'store'])->name('central.register.store');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->name('central.register.resend');
    Route::get('/register/verify/{token}', [RegisterController::class, 'verify'])->name('central.register.verify');
});

Route::prefix('admin')->name('central.admin.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('central.admin')->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::redirect('/', '/admin/dashboard');

        Route::middleware('platform.permission:profile.manage')->group(function () {
            Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile');
            Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
            Route::put('/profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');
        });

        Route::middleware('platform.permission:tenants.view')->group(function () {
            Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
            Route::get('/pending-registrations', [AdminPendingRegistrationController::class, 'index'])->name('pending-registrations.index');
        });

        Route::middleware('platform.permission:tenants.manage')->group(function () {
            Route::put('/tenants/{tenant}', [AdminTenantController::class, 'update'])->name('tenants.update');
            Route::delete('/tenants/{tenant}', [AdminTenantController::class, 'destroy'])->name('tenants.destroy');
            Route::delete('/pending-registrations/{registration}', [AdminPendingRegistrationController::class, 'destroy'])->name('pending-registrations.destroy');
            Route::post('/pending-registrations/purge-expired', [AdminPendingRegistrationController::class, 'purgeExpired'])->name('pending-registrations.purge-expired');
        });

        Route::middleware('platform.permission:payments.view')->group(function () {
            Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        });

        Route::middleware('platform.permission:subscriptions.view')->group(function () {
            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        });

        Route::middleware('platform.permission:settings.view')->group(function () {
            Route::get('/settings', [AdminSettingsController::class, 'general'])->name('settings');
            Route::get('/settings/billing', [AdminSettingsController::class, 'billing'])->name('settings.billing');
            Route::get('/settings/plans', [AdminSettingsController::class, 'plans'])->name('settings.plans');
            Route::get('/settings/addons', [AdminSettingsController::class, 'addons'])->name('settings.addons');
            Route::get('/settings/branding', [AdminSettingsController::class, 'branding'])->name('settings.branding');
        });

        Route::middleware('platform.permission:settings.manage')->group(function () {
            Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/purge-expired-tenants', [AdminSettingsController::class, 'purgeExpiredTenants'])->name('settings.purge-expired-tenants');
        });

        Route::middleware('platform.permission:emails.view')->group(function () {
            Route::get('/emails', [AdminEmailTemplateController::class, 'index'])->name('emails.index');
        });

        Route::middleware('platform.permission:emails.manage')->group(function () {
            Route::get('/emails/create', [AdminEmailTemplateController::class, 'create'])->name('emails.create');
            Route::post('/emails', [AdminEmailTemplateController::class, 'store'])->name('emails.store');
            Route::get('/emails/{template}/edit', [AdminEmailTemplateController::class, 'edit'])->name('emails.edit');
            Route::put('/emails/{template}', [AdminEmailTemplateController::class, 'update'])->name('emails.update');
            Route::delete('/emails/{template}', [AdminEmailTemplateController::class, 'destroy'])->name('emails.destroy');
        });

        Route::middleware('platform.permission:notices.view')->group(function () {
            Route::get('/notices', [AdminNoticeController::class, 'index'])->name('notices.index');
        });

        Route::middleware('platform.permission:notices.manage')->group(function () {
            Route::get('/notices/create', [AdminNoticeController::class, 'create'])->name('notices.create');
            Route::post('/notices', [AdminNoticeController::class, 'store'])->name('notices.store');
            Route::get('/notices/{notice}/edit', [AdminNoticeController::class, 'edit'])->name('notices.edit');
            Route::put('/notices/{notice}', [AdminNoticeController::class, 'update'])->name('notices.update');
            Route::post('/notices/{notice}/publish', [AdminNoticeController::class, 'publish'])->name('notices.publish');
            Route::post('/notices/{notice}/deactivate', [AdminNoticeController::class, 'deactivate'])->name('notices.deactivate');
            Route::delete('/notices/{notice}', [AdminNoticeController::class, 'destroy'])->name('notices.destroy');
            Route::get('/notices/{notice}/image', [AdminNoticeController::class, 'image'])->name('notices.image');
        });

        Route::middleware('platform.permission:users.view')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        });

        Route::middleware('platform.permission:users.manage')->group(function () {
            Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        });

        Route::middleware('platform.permission:roles.view')->group(function () {
            Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
        });

        Route::middleware('platform.permission:roles.manage')->group(function () {
            Route::post('/roles', [AdminRoleController::class, 'store'])->name('roles.store');
            Route::put('/roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::middleware('platform.permission:feedback.view')->group(function () {
            Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
            Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
        });

        Route::middleware('platform.permission:feedback.manage')->group(function () {
            Route::put('/feedback/{feedback}/status', [AdminFeedbackController::class, 'updateStatus'])->name('feedback.status');
        });

        Route::middleware('platform.permission:audit.view')->group(function () {
            Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');
        });

        Route::middleware('platform.permission:observability.view')->group(function () {
            Route::get('/observability', AdminObservabilityController::class)->name('observability.index');
        });

        Route::middleware('platform.permission:backups.view')->group(function () {
            Route::get('/backups', [AdminBackupController::class, 'index'])->name('backups.index');
        });

        Route::middleware('platform.permission:backups.manage')->group(function () {
            Route::post('/backups', [AdminBackupController::class, 'store'])->name('backups.store');
            Route::put('/backups/schedule', [AdminBackupController::class, 'updateSchedule'])->name('backups.schedule.update');
            Route::get('/backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
            Route::delete('/backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');
        });
    });
});
