<?php

namespace App\Domains\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TenantBlockedController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Tenant/Blocked');
    }
}
