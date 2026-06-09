<?php

namespace App\Domains\Performance\Controllers;

use App\Domains\Performance\Services\PerformanceService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(private PerformanceService $performance)
    {
    }

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Settings/Performance', [
            'agent' => $user->only(['id', 'name', 'email', 'performance_score']),
            'summary' => $this->performance->summary($user->id),
            'events' => $this->performance->history($user->id),
            'pointMap' => PerformanceService::pointMap(),
        ]);
    }
}
