<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Billing\Services\PlatformPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private PlanRepository $planRepository,
        private PlatformPaymentService $payments,
    ) {
    }

    public function index(): Response
    {
        $tenantId = (string) tenant('id');

        $this->billingService->syncPaymentHistory($tenantId);

        return Inertia::render('Settings/Billing', [
            'billing' => $this->billingService->snapshot(),
            'payments' => $this->payments->historyForTenant($tenantId),
        ]);
    }

    public function updatePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', $this->planRepository->slugs())],
            'interval' => ['nullable', 'in:month,year'],
        ]);

        if ($this->billingService->usesRazorpayCheckout()) {
            throw ValidationException::withMessages([
                'plan' => 'Use the checkout button to change plans with Razorpay.',
            ]);
        }

        $this->billingService->changePlan($data['plan'], $data['interval'] ?? 'month');

        return back()->with('success', 'Plan updated.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', $this->planRepository->slugs())],
            'interval' => ['nullable', 'in:month,year'],
            'redirect' => ['nullable', 'string', 'max:255'],
            'success_redirect' => ['nullable', 'string', 'max:255'],
        ]);

        $interval = $data['interval'] ?? 'month';
        $returnPath = $this->resolveInternalPath(
            $data['redirect'] ?? '/settings/billing?section=plans',
            '/settings/billing?section=plans',
        );

        $defaultSuccessRedirect = str_contains($returnPath, '?')
            ? $returnPath.'&checkout=success'
            : $returnPath.'?checkout=success';
        $successRedirect = $this->resolveInternalPath(
            $data['success_redirect'] ?? $defaultSuccessRedirect,
            $defaultSuccessRedirect,
        );
        $cancelRedirect = str_contains($returnPath, '?')
            ? $returnPath.'&checkout=cancelled'
            : $returnPath.'?checkout=cancelled';

        $result = $this->billingService->initiatePlanChange(
            $data['plan'],
            (string) $request->user()->email,
            (string) $request->user()->name,
            $successRedirect,
            $interval,
            $cancelRedirect,
        );

        if (is_string($result)) {
            return redirect($result);
        }

        if (! is_array($result)) {
            return back()->with('success', 'Plan updated.');
        }

        return redirect($returnPath)
            ->with('razorpay_checkout', $result);
    }

    public function verifyRazorpayCheckout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_subscription_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'redirect' => ['nullable', 'string', 'max:255'],
        ]);

        $this->billingService->verifyRazorpayCheckout(
            $data['razorpay_payment_id'],
            $data['razorpay_subscription_id'],
            $data['razorpay_signature'],
        );

        $redirect = $this->resolveInternalPath(
            $data['redirect'] ?? '/settings/billing?checkout=success&section=usage',
            '/settings/billing?checkout=success&section=usage',
        );

        return redirect($redirect)->with('success', 'Subscription activated successfully.');
    }

    private function resolveInternalPath(string $path, string $fallback): string
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        if (str_contains($path, '://') || str_contains($path, '\\')) {
            return $fallback;
        }

        return $path;
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->billingService->cancelSubscription();

        return back()->with('success', 'Subscription will cancel at the end of the current billing period.');
    }

    public function purchaseAddon(Request $request, string $addon): RedirectResponse
    {
        $onTrial = $this->billingService->snapshot()['on_trial'];

        $result = $this->billingService->purchaseAddon(
            $addon,
            (string) $request->user()->email,
            (string) $request->user()->name,
        );

        if (is_array($result)) {
            return redirect('/settings/billing?section=addons')
                ->with('razorpay_checkout', $result);
        }

        return back()->with('success', $onTrial
            ? 'Add-on enabled for your free trial.'
            : 'Add-on activated.');
    }

    public function cancelAddon(string $addon): RedirectResponse
    {
        $this->billingService->cancelAddon($addon);

        return back()->with('success', 'Add-on cancelled.');
    }
}
