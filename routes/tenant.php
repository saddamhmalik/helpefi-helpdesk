<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
    'tenant.not_blocked',
    'tenant.custom_domain_redirect',
])->group(function () {
    require __DIR__.'/tenant/public.php';
    require __DIR__.'/tenant/guest.php';
    require __DIR__.'/tenant/portal.php';
    require __DIR__.'/tenant/auth.php';
});
