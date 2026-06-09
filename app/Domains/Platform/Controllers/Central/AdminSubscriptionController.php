<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformSubscriptionService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private PlatformSubscriptionService $subscriptions,
        private CentralSettingsService $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');

        return Inertia::render('Central/Admin/Subscriptions/Index', [
            'subscriptions' => $this->subscriptions->list(
                (int) $request->integer('per_page', 20),
                $search !== '' ? $search : null,
                $status,
            ),
            'stats' => $this->subscriptions->stats(),
            'currency' => $this->settings->currencyMeta(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'stripe_enabled' => (bool) config('stripe.enabled'),
        ]);
    }
}
