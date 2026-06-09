<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionRequiredController extends Controller
{
    public function __construct(
        private BillingService $billing,
        private SubscriptionRepository $subscriptions,
    ) {
    }

    public function show(): Response|RedirectResponse
    {
        $subscription = $this->subscriptions->current();

        if ($subscription->isAccessible()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Subscription/Required', [
            'billing' => $this->billing->snapshot(),
        ]);
    }
}
