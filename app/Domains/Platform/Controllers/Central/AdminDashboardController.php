<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformDashboardService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(private PlatformDashboardService $dashboard)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Dashboard', [
            'dashboard' => $this->dashboard->snapshot(auth('platform')->user()),
        ]);
    }
}
