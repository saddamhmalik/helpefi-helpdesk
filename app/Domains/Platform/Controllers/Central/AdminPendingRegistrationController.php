<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformPendingRegistrationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPendingRegistrationController extends Controller
{
    public function __construct(
        private PlatformPendingRegistrationService $pendingRegistrations,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');

        return Inertia::render('Central/Admin/PendingRegistrations/Index', [
            'registrations' => $this->pendingRegistrations->list(
                (int) $request->integer('per_page', 20),
                $search !== '' ? $search : null,
                $status,
            ),
            'stats' => $this->pendingRegistrations->stats(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function destroy(int $registration): RedirectResponse
    {
        $this->pendingRegistrations->delete($registration);

        return back()->with('success', 'Pending registration removed and workspace URL released.');
    }

    public function purgeExpired(): RedirectResponse
    {
        $removed = $this->pendingRegistrations->purgeExpired();

        return back()->with(
            'success',
            $removed > 0
                ? "Purged {$removed} expired pending registration(s)."
                : 'No expired pending registrations to purge.',
        );
    }
}
