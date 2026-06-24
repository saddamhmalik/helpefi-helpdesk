<?php

namespace App\Domains\Growth\Controllers;

use App\Domains\Growth\Services\GrowthHubService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowthHubController extends Controller
{
    public function __construct(private GrowthHubService $growth)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'tab' => ['nullable', 'string', 'in:overview,health,ai,deflection'],
        ]);

        $deflectionFilters = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ]);

        if ($deflectionFilters === []) {
            $deflectionFilters = $this->growth->defaultDeflectionFilters();
        }

        return Inertia::render('Growth/Index', [
            'activeTab' => $filters['tab'] ?? 'overview',
            'hub' => $this->growth->snapshot($deflectionFilters),
        ]);
    }
}
