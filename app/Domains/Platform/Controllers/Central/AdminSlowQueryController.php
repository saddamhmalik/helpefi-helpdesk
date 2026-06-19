<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformSlowQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSlowQueryController extends Controller
{
    public function __construct(private PlatformSlowQueryService $slowQueries)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'connection' => ['nullable', 'string', 'max:64'],
            'min_time_ms' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return Inertia::render('Central/Admin/SlowQueries/Index', [
            'slowQueries' => $this->slowQueries->list($filters),
            'filters' => $filters,
            'summary' => $this->slowQueries->summary(7),
            'thresholdMs' => (int) config('database.slow_query.threshold_ms', 500),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $deleted = $this->slowQueries->deleteByIds($data['ids']);

        return back()->with('success', "{$deleted} slow query record(s) deleted.");
    }

    public function destroyFiltered(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'connection' => ['nullable', 'string', 'max:64'],
            'min_time_ms' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $deleted = $this->slowQueries->deleteMatching($filters);

        return back()->with('success', "{$deleted} slow query record(s) deleted.");
    }

    public function show(int $slowQuery): Response
    {
        return Inertia::render('Central/Admin/SlowQueries/Show', [
            'slowQuery' => $this->slowQueries->show($slowQuery),
            'thresholdMs' => (int) config('database.slow_query.threshold_ms', 500),
        ]);
    }
}
