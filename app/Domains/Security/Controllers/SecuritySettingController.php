<?php

namespace App\Domains\Security\Controllers;

use App\Domains\Security\Services\AuditLogService;
use App\Domains\Security\Services\RetentionService;
use App\Domains\Security\Services\SecuritySettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecuritySettingController extends Controller
{
    public function __construct(
        private SecuritySettingService $security,
        private AuditLogService $audit,
        private RetentionService $retention,
        private \App\Domains\Security\Services\SsoService $sso,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Security', [
            'observability' => $this->security->observability(),
            'sso' => $this->sso->snapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mfa_required_for_agents' => ['required', 'boolean'],
            'audit_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'closed_ticket_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
        ]);

        $this->security->update($data);

        return back()->with('success', 'Security settings saved.');
    }

    public function purge(): RedirectResponse
    {
        $results = $this->retention->purge();

        return back()->with('success', sprintf(
            'Retention purge complete: %d audit logs, %d tickets, %d messages removed.',
            $results['audit_logs'],
            $results['tickets'],
            $results['messages'],
        ));
    }
}
