<?php

namespace App\Domains\Security\Controllers\Api;

use App\Domains\Security\Services\AuditLogService;
use App\Domains\Security\Services\RetentionService;
use App\Domains\Security\Services\SecuritySettingService;
use App\Domains\Security\Services\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        private SecuritySettingService $security,
        private AuditLogService $audit,
        private TwoFactorService $twoFactor,
        private RetentionService $retention,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->security->observability());
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'mfa_required_for_agents' => ['required', 'boolean'],
            'audit_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'closed_ticket_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
        ]);

        return response()->json($this->security->update($data));
    }

    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasRole('admin') || $request->user()?->can('audit.view'),
            403,
        );

        return response()->json(
            $this->audit->list($request->only(['event', 'search'])),
        );
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        return response()->json($this->twoFactor->status($request->user()));
    }

    public function purgeRetention(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->retention->purge());
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
