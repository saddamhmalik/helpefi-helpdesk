<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Services\ChangeRecordService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangeController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ChangeRecordService $changes,
        private WorkforceService $workforce,
    ) {
    }

    public function calendar(Request $request): Response
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        $from = $request->query('from');
        $to = $request->query('to');
        $calendar = $this->changes->calendar(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        return Inertia::render('ServiceDesk/ChangeCalendar', [
            ...$calendar,
            'agents' => $this->workforce->agentOptions(),
            'riskOptions' => $this->changes->riskOptions(),
        ]);
    }

    public function update(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();

        $this->changes->update($ticket, $request->validate([
            'risk' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'rollback_plan' => ['nullable', 'string', 'max:5000'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
            'cab_user_ids' => ['nullable', 'array'],
            'cab_user_ids.*' => ['integer', 'exists:users,id'],
            'cab_notes' => ['nullable', 'string', 'max:5000'],
            'implementation_notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('success', 'Change details updated.');
    }
}
