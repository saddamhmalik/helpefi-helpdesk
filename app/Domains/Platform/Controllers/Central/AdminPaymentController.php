<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Billing\Services\PlatformPaymentService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentController extends Controller
{
    public function __construct(
        private PlatformPaymentService $payments,
        private CentralSettingsService $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');

        return Inertia::render('Central/Admin/Payments/Index', [
            'payments' => $this->payments->list(
                (int) $request->integer('per_page', 20),
                $search !== '' ? $search : null,
                $status,
            ),
            'stats' => $this->payments->stats(),
            'currency' => $this->settings->currencyMeta(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'stripe_enabled' => (bool) config('stripe.enabled'),
        ]);
    }
}
