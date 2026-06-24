<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Support\RazorpaySubscriptionCheckout;
use App\Domains\Billing\Support\RazorpaySubscriptionSupport;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\Error;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayCheckoutService
{
    public function __construct(
        private RazorpayApiClient $api,
        private RazorpayCustomerService $customers,
        private RazorpayCheckoutSessionBuilder $checkoutSessions,
        private RazorpayAddonBillingService $addons,
        private PlanRepository $plans,
        private SubscriptionRepository $subscriptions,
        private PlatformPaymentService $payments,
        private SubscriptionLifecycleService $lifecycle,
        private CentralSettingsService $centralSettings,
    ) {
    }

    public function prepareCheckoutSession(
        string $planSlug,
        string $customerEmail,
        string $customerName,
        string $successRedirect,
        string $interval = 'month',
        string $cancelRedirect = '/settings/billing?checkout=cancelled&section=plans',
        ?string $currency = null,
    ): array|string|Subscription {
        $this->api->assertEnabled();

        $plan = $this->plans->find($planSlug);

        $subscription = $this->subscriptions->current();
        $this->assertCanChangePlan($subscription);

        $currency = $this->resolveCheckoutCurrency($subscription, $currency);
        $india = $this->isIndiaCurrency($currency);
        $planId = PlanCatalogDefinition::razorpayPlanIdForInterval($plan, $interval, $india);

        if (! $planId) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not configured for '.$currency.' billing yet.',
            ]);
        }

        $tenant = Tenant::query()->findOrFail(tenant('id'));

        try {
            $this->customers->ensureCustomer($tenant, $customerEmail);
        } catch (Error $exception) {
            Log::warning('Razorpay customer creation failed during checkout', [
                'tenant_id' => $tenant->id,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        if ($subscription->razorpay_subscription_id) {
            $existing = $this->resolveExistingCheckoutSession(
                $subscription,
                $planSlug,
                $planId,
                $interval,
                $successRedirect,
                $cancelRedirect,
                $plan,
                $customerEmail,
                $customerName,
                $currency,
                $india,
            );

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            $razorpaySubscription = $this->createRazorpayCheckoutSubscription(
                $tenant,
                $planId,
                $planSlug,
                $interval,
                $currency,
            );
        } catch (Error $exception) {
            Log::warning('Razorpay subscription creation failed during checkout', [
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        return $this->beginCheckoutSession(
            $subscription,
            $razorpaySubscription,
            $planSlug,
            $planId,
            $interval,
            $plan,
            $customerEmail,
            $customerName,
            $successRedirect,
            $cancelRedirect,
            $currency,
        );
    }

    public function verifySubscriptionPayment(
        string $paymentId,
        string $subscriptionId,
        string $signature,
    ): Subscription {
        $this->api->assertEnabled();

        try {
            $this->api->client()->utility->verifyPaymentSignature([
                'razorpay_payment_id' => $paymentId,
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_signature' => $signature,
            ]);
        } catch (SignatureVerificationError $exception) {
            Log::warning('Razorpay payment signature verification failed', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment verification failed. Please try again or contact support.',
            ]);
        }

        $subscription = $this->subscriptions->current();
        $addonKey = $this->addons->resolveAddonKeyForSubscriptionId($subscription, $subscriptionId);

        if ($addonKey === null && $subscription->razorpay_subscription_id !== $subscriptionId) {
            try {
                $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();

                if ($this->addons->isAddonSubscription($entity)) {
                    $addonKey = (string) ($entity['notes']['addon_key'] ?? '');
                }
            } catch (Error) {
                $addonKey = null;
            }
        }

        if ($addonKey !== null && $addonKey !== '') {
            $result = $this->addons->confirmAddonSubscriptionPayment($subscription, $subscriptionId, $addonKey);
        } elseif (is_array($pendingPlanCheckout = session('razorpay_plan_checkout'))
            && ($pendingPlanCheckout['subscription_id'] ?? null) === $subscriptionId) {
            $result = $this->confirmPlanChangeCheckout($subscription, $subscriptionId, $pendingPlanCheckout);
        } else {
            if (! $subscription->razorpay_subscription_id) {
                throw ValidationException::withMessages([
                    'razorpay' => 'No pending checkout session was found for this workspace. Please start checkout again.',
                ]);
            }

            if ($subscription->razorpay_subscription_id !== $subscriptionId) {
                throw ValidationException::withMessages([
                    'razorpay' => 'This checkout session does not match your workspace subscription.',
                ]);
            }

            try {
                $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();
            } catch (Error $exception) {
                Log::warning('Razorpay subscription fetch failed after payment verification', [
                    'subscription_id' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
                ]);
            }

            $result = $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                RazorpaySubscriptionSupport::normalizePayload($entity),
            );
        }

        $this->recordVerifiedPayment($paymentId, $subscriptionId);

        return $result;
    }

    public function cancelSubscription(): Subscription
    {
        $this->api->assertEnabled();

        $subscription = $this->subscriptions->current();

        if (! $subscription->razorpay_subscription_id) {
            throw ValidationException::withMessages([
                'subscription' => 'No active Razorpay subscription found.',
            ]);
        }

        $subscriptionId = $subscription->razorpay_subscription_id;

        try {
            $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed before cancellation', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Unable to cancel subscription. Please try again or contact support.',
            ]);
        }

        $status = (string) ($entity['status'] ?? '');

        if (in_array($status, ['cancelled', 'completed', 'expired'], true)) {
            return $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                RazorpaySubscriptionSupport::normalizePayload($entity),
            );
        }

        if ((bool) ($entity['cancel_at_cycle_end'] ?? false) && in_array($status, ['active', 'authenticated'], true)) {
            return $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                RazorpaySubscriptionSupport::normalizePayload($entity),
            );
        }

        $cancelAtCycleEnd = in_array($status, ['active', 'authenticated'], true);

        try {
            $this->api->fetchedSubscription($subscriptionId)->cancel([
                'cancel_at_cycle_end' => $cancelAtCycleEnd,
            ]);
        } catch (Error $exception) {
            Log::warning('Razorpay subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'status' => $status,
                'cancel_at_cycle_end' => $cancelAtCycleEnd,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Unable to cancel subscription. Please try again or contact support.',
            ]);
        }

        try {
            $razorpaySubscription = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed after cancellation', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Subscription cancellation was requested but could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription,
            RazorpaySubscriptionSupport::normalizePayload($razorpaySubscription),
        );
    }

    private function resolveCheckoutCurrency(Subscription $subscription, ?string $requested): string
    {
        if ($subscription->isActive() && $subscription->currency) {
            return strtoupper($subscription->currency);
        }

        $requested = $requested ? strtoupper($requested) : $this->centralSettings->currency();

        return $this->isIndiaCurrency($requested)
            ? $this->centralSettings->indiaCurrency()
            : $this->centralSettings->currency();
    }

    private function isIndiaCurrency(string $currency): bool
    {
        return $this->centralSettings->indiaPricingEnabled()
            && strtoupper($currency) === $this->centralSettings->indiaCurrency();
    }

    private function assertCanChangePlan(Subscription $subscription): void
    {
        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'Your free trial is still active. Choose a plan after the trial ends.',
            ]);
        }

        if (! $subscription->isTrialExpired() && ! $subscription->isActive() && $subscription->status !== Subscription::STATUS_PAST_DUE) {
            throw ValidationException::withMessages([
                'plan' => 'Activate a subscription plan before changing plans.',
            ]);
        }
    }

    private function resolveExistingCheckoutSession(
        Subscription $subscription,
        string $planSlug,
        string $planId,
        string $interval,
        string $successRedirect,
        string $cancelRedirect,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $currency = '',
        bool $india = false,
    ): array|string|Subscription|null {
        try {
            $entity = $this->api->fetchedSubscription($subscription->razorpay_subscription_id)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed during checkout', [
                'subscription_id' => $subscription->razorpay_subscription_id,
                'message' => $exception->getMessage(),
            ]);

            $this->clearRazorpaySubscriptionReference($subscription);

            return null;
        }

        $status = (string) ($entity['status'] ?? '');

        if (in_array($status, ['active', 'authenticated'], true)) {
            try {
                $subscription = $this->lifecycle->applyRazorpaySubscription(
                    $subscription,
                    RazorpaySubscriptionSupport::normalizePayload($entity),
                );
            } catch (\Throwable $exception) {
                Log::warning('Razorpay subscription sync failed during checkout', [
                    'subscription_id' => $subscription->razorpay_subscription_id,
                    'message' => $exception->getMessage(),
                ]);

                $this->clearRazorpaySubscriptionReference($subscription);

                return null;
            }

            if ($subscription->isActive()) {
                if ($this->matchesSelectedPlan($subscription, $planSlug, $interval)) {
                    return $subscription;
                }

                if ($this->planChangeRequiresCheckout($subscription, $interval)) {
                    return null;
                }

                return $this->changeActiveRazorpayPlan(
                    $subscription,
                    $planSlug,
                    $planId,
                    $interval,
                    $entity,
                    $currency,
                    $india,
                );
            }
        }

        if ($status === 'created' && RazorpaySubscriptionCheckout::canAuthenticateViaStandardCheckout($entity)) {
            $pendingInterval = (string) ($entity['notes']['billing_interval'] ?? 'month');

            if (($entity['plan_id'] ?? null) !== $planId || $pendingInterval !== $interval) {
                $this->cancelRazorpaySubscriptionImmediately($subscription->razorpay_subscription_id);
                $this->clearRazorpaySubscriptionReference($subscription);

                return null;
            }

            return $this->checkoutSessions->buildPlanSession(
                $entity,
                $plan,
                $customerEmail,
                $customerName,
                $successRedirect,
                $cancelRedirect,
            );
        }

        if (RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription($entity)) {
            $this->cancelRazorpaySubscriptionImmediately($subscription->razorpay_subscription_id);
        }

        $this->clearRazorpaySubscriptionReference($subscription);

        return null;
    }

    private function persistPendingCheckoutSubscription(
        Subscription $subscription,
        array $razorpaySubscription,
        string $planSlug,
        string $planId,
        string $interval,
        string $currency = '',
    ): void {
        $this->subscriptions->update($subscription, array_filter([
            'razorpay_subscription_id' => (string) ($razorpaySubscription['id'] ?? ''),
            'razorpay_plan_id' => $planId,
            'currency' => $currency !== '' ? strtoupper($currency) : null,
        ], static fn ($value) => $value !== null));
    }

    private function matchesSelectedPlan(Subscription $subscription, string $planSlug, string $interval): bool
    {
        return $subscription->plan === $planSlug
            && ($subscription->billing_interval ?? 'month') === $interval;
    }

    private function changeActiveRazorpayPlan(
        Subscription $subscription,
        string $planSlug,
        string $planId,
        string $interval,
        array $currentEntity,
        string $currency = '',
        bool $india = false,
    ): Subscription {
        $subscriptionId = (string) $subscription->razorpay_subscription_id;
        $currentPlan = $this->plans->find($subscription->plan ?? $planSlug);
        $nextPlan = $this->plans->find($planSlug);
        $currentInterval = $subscription->billing_interval ?? 'month';
        $currentPrice = PlanCatalogDefinition::priceForInterval($currentPlan, $currentInterval, $india);
        $nextPrice = PlanCatalogDefinition::priceForInterval($nextPlan, $interval, $india);
        $scheduleChangeAt = $nextPrice >= $currentPrice ? 'now' : 'cycle_end';

        $payload = [
            'plan_id' => $planId,
            'schedule_change_at' => $scheduleChangeAt,
            'customer_notify' => true,
        ];

        if (isset($currentEntity['remaining_count'])) {
            $payload['remaining_count'] = (int) $currentEntity['remaining_count'];
        }

        try {
            $this->api->fetchedSubscription($subscriptionId)->update($payload);
        } catch (Error $exception) {
            if ($scheduleChangeAt === 'now') {
                try {
                    $payload['schedule_change_at'] = 'cycle_end';
                    $this->api->fetchedSubscription($subscriptionId)->update($payload);
                } catch (Error $retryException) {
                    Log::warning('Razorpay subscription plan update failed during checkout', [
                        'subscription_id' => $subscriptionId,
                        'plan_id' => $planId,
                        'plan_slug' => $planSlug,
                        'interval' => $interval,
                        'message' => $retryException->getMessage(),
                        'initial_message' => $exception->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'plan' => 'Unable to change plan right now. Please try again or contact support.',
                    ]);
                }
            } else {
                Log::warning('Razorpay subscription plan update failed during checkout', [
                    'subscription_id' => $subscriptionId,
                    'plan_id' => $planId,
                    'plan_slug' => $planSlug,
                    'interval' => $interval,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'plan' => 'Unable to change plan right now. Please try again or contact support.',
                ]);
            }
        }

        $updatedEntity = $this->api->fetchedSubscription($subscriptionId)->toArray();

        $this->subscriptions->update($subscription, array_filter([
            'plan' => $planSlug,
            'billing_interval' => $interval,
            'razorpay_plan_id' => $planId,
            'currency' => $currency !== '' ? strtoupper($currency) : null,
        ], static fn ($value) => $value !== null));

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription->fresh(),
            RazorpaySubscriptionSupport::normalizePayload($updatedEntity),
        );
    }

    private function planChangeRequiresCheckout(Subscription $subscription, string $interval): bool
    {
        if (! $subscription->isActive() || ! $subscription->razorpay_subscription_id) {
            return true;
        }

        return ($subscription->billing_interval ?? 'month') !== $interval;
    }

    private function createRazorpayCheckoutSubscription(
        Tenant $tenant,
        string $planId,
        string $planSlug,
        string $interval,
        string $currency = '',
    ): array {
        return $this->api->client()->subscription->create([
            'plan_id' => $planId,
            'customer_id' => $tenant->razorpay_customer_id,
            'customer_notify' => true,
            'total_count' => $interval === 'year' ? 10 : 120,
            'expire_by' => now()->addDays(3)->getTimestamp(),
            'notes' => [
                'tenant_id' => $tenant->id,
                'plan' => $planSlug,
                'billing_interval' => $interval,
                'currency' => $currency,
            ],
        ])->toArray();
    }

    private function beginCheckoutSession(
        Subscription $subscription,
        array $razorpaySubscription,
        string $planSlug,
        string $planId,
        string $interval,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $successRedirect,
        string $cancelRedirect,
        string $currency = '',
    ): array {
        $previousSubscriptionId = $subscription->isActive() && $subscription->razorpay_subscription_id
            ? (string) $subscription->razorpay_subscription_id
            : null;

        if ($previousSubscriptionId) {
            session([
                'razorpay_plan_checkout' => [
                    'subscription_id' => (string) ($razorpaySubscription['id'] ?? ''),
                    'previous_subscription_id' => $previousSubscriptionId,
                    'plan' => $planSlug,
                    'plan_id' => $planId,
                    'interval' => $interval,
                    'currency' => $currency,
                ],
            ]);
        } else {
            $this->persistPendingCheckoutSubscription($subscription, $razorpaySubscription, $planSlug, $planId, $interval, $currency);
        }

        return $this->checkoutSessions->buildPlanSession(
            $razorpaySubscription,
            $plan,
            $customerEmail,
            $customerName,
            $successRedirect,
            $cancelRedirect,
        );
    }

    private function confirmPlanChangeCheckout(
        Subscription $subscription,
        string $subscriptionId,
        array $pendingPlanCheckout,
    ): Subscription {
        try {
            $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed after plan change checkout', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        $status = (string) ($entity['status'] ?? '');

        if (! in_array($status, ['active', 'authenticated'], true)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        $previousId = (string) ($pendingPlanCheckout['previous_subscription_id'] ?? '');

        if ($previousId !== '') {
            try {
                $this->api->fetchedSubscription($previousId)->cancel([
                    'cancel_at_cycle_end' => true,
                ]);
            } catch (Error $exception) {
                Log::warning('Razorpay previous subscription cancellation failed after plan change checkout', [
                    'subscription_id' => $previousId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        session()->forget('razorpay_plan_checkout');

        $this->subscriptions->update($subscription, array_filter([
            'plan' => (string) ($pendingPlanCheckout['plan'] ?? $subscription->plan),
            'billing_interval' => (string) ($pendingPlanCheckout['interval'] ?? $subscription->billing_interval ?? 'month'),
            'razorpay_subscription_id' => $subscriptionId,
            'razorpay_plan_id' => (string) ($pendingPlanCheckout['plan_id'] ?? $entity['plan_id'] ?? ''),
            'currency' => ($pendingPlanCheckout['currency'] ?? '') !== '' ? strtoupper((string) $pendingPlanCheckout['currency']) : null,
        ], static fn ($value) => $value !== null && $value !== ''));

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription->fresh(),
            RazorpaySubscriptionSupport::normalizePayload($entity),
        );
    }

    private function cancelRazorpaySubscriptionImmediately(string $subscriptionId): void
    {
        try {
            $this->api->fetchedSubscription($subscriptionId)->cancel([
                'cancel_at_cycle_end' => false,
            ]);
        } catch (Error $exception) {
            Log::warning('Razorpay subscription cancellation failed during checkout reset', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function clearRazorpaySubscriptionReference(Subscription $subscription): void
    {
        if (! $subscription->razorpay_subscription_id) {
            return;
        }

        $this->subscriptions->update($subscription, [
            'razorpay_subscription_id' => null,
            'razorpay_plan_id' => null,
        ]);
    }

    private function recordVerifiedPayment(string $paymentId, string $subscriptionId): void
    {
        try {
            $payment = $this->api->client()->payment->fetch($paymentId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay payment fetch failed after checkout verification', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
            return;
        }

        try {
            $subscriptionEntity = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            $subscriptionEntity = [
                'id' => $subscriptionId,
                'notes' => ['tenant_id' => (string) tenant('id')],
            ];
        }

        if (empty($subscriptionEntity['notes']['tenant_id'])) {
            $subscriptionEntity['notes']['tenant_id'] = (string) tenant('id');
        }

        $this->payments->recordFromRazorpayPayment($payment, $subscriptionEntity);
    }
}
