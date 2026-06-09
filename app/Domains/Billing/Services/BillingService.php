<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Repositories\UsageRepository;
use App\Domains\Tenancy\Services\CentralSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UsageRepository $usage,
        private CentralSettingsService $centralSettings,
        private StripeBillingService $stripe,
    ) {
    }

    public function snapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->currentPlan($subscription);
        $usage = $this->usageStats();

        return [
            'plan' => $this->displayPlan($subscription, $plan),
            'currency' => $this->centralSettings->currencyMeta(),
            'status' => $subscription->status,
            'on_trial' => $subscription->isOnTrial(),
            'trial_expired' => $subscription->isTrialExpired(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'trial_days_remaining' => $subscription->trialDaysRemaining(),
            'renews_at' => $subscription->renews_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'access_ends_at' => $subscription->access_ends_at?->toIso8601String(),
            'in_grace_period' => $subscription->isInGracePeriod(),
            'grace_days_remaining' => $subscription->graceDaysRemaining(),
            'cancellation_grace_days' => app(SubscriptionLifecycleService::class)->graceDays(),
            'cancellation_pending' => $subscription->cancelled_at !== null && $subscription->isActive(),
            'show_cancellation_banner' => $subscription->cancelled_at !== null
                && $subscription->access_ends_at?->isFuture(),
            'features' => $plan['features'],
            'limits' => $this->formattedLimits($plan['limits']),
            'usage' => $usage,
            'available_plans' => collect($this->plans->all())
                ->map(fn (array $item, string $slug) => [
                    'slug' => $slug,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'limits' => $this->formattedLimits($item['limits']),
                    'features' => $item['features'],
                    'stripe_ready' => ! empty($item['stripe_price_id']),
                ])
                ->values()
                ->all(),
            'stripe_enabled' => $this->stripe->isEnabled(),
            'has_stripe_subscription' => (bool) $subscription->stripe_subscription_id,
            'trial_offer' => $this->trialOffer(),
        ];
    }

    private function trialOffer(): array
    {
        $trialPlan = $this->plans->find(config('billing.trial_plan', 'enterprise'));

        return [
            'days' => $this->centralSettings->trialDays(),
            'plan_name' => $trialPlan['name'],
            'plan_slug' => config('billing.trial_plan', 'enterprise'),
            'limits' => $this->formattedLimits($trialPlan['limits']),
            'features' => $trialPlan['features'],
        ];
    }

    public function canUseFeature(string $feature): bool
    {
        if (! $this->subscriptions->current()->isAccessible()) {
            return false;
        }

        $plan = $this->currentPlan();

        return in_array($feature, $plan['features'], true);
    }

    public function withinLimit(string $key, int $buffer = 0): bool
    {
        if (! $this->subscriptions->current()->isAccessible()) {
            return false;
        }

        $limit = $this->currentPlan()['limits'][$key] ?? null;

        if ($limit === null) {
            return true;
        }

        return $this->currentUsage($key) + $buffer <= $limit;
    }

    public function assertFeature(string $feature): void
    {
        if ($this->canUseFeature($feature)) {
            return;
        }

        $plan = $this->currentPlan();

        throw new AuthorizationException(
            "The {$plan['name']} plan does not include {$feature}. Upgrade to unlock this feature.",
        );
    }

    public function assertLimit(string $key, int $buffer = 0): void
    {
        if ($this->withinLimit($key, $buffer)) {
            return;
        }

        $limit = $this->currentPlan()['limits'][$key];
        $label = $key === 'agents' ? 'team members' : 'monthly tickets';

        throw ValidationException::withMessages([
            $key === 'agents' ? 'email' : 'subject' => "Plan limit reached: maximum {$limit} {$label} on the {$this->currentPlan()['name']} plan.",
        ]);
    }

    public function usesStripeCheckout(): bool
    {
        return $this->stripe->isEnabled();
    }

    public function initiatePlanChange(string $slug, string $customerEmail, string $successUrl, string $cancelUrl): string|Subscription
    {
        $this->plans->find($slug);

        if ($this->stripe->isEnabled()) {
            return $this->stripe->checkoutUrl($slug, $customerEmail, $successUrl, $cancelUrl);
        }

        return $this->changePlan($slug);
    }

    public function billingPortalUrl(string $customerEmail, string $returnUrl): string
    {
        return $this->stripe->portalUrl($customerEmail, $returnUrl);
    }

    public function changePlan(string $slug): Subscription
    {
        $this->plans->find($slug);
        $subscription = $this->subscriptions->current();

        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'Your free trial is still active. Choose a plan after the trial ends.',
            ]);
        }

        if (! $subscription->isTrialExpired() && ! $subscription->isActive()) {
            throw ValidationException::withMessages([
                'plan' => 'Activate a subscription plan before changing plans.',
            ]);
        }

        return $this->subscriptions->update($subscription, [
            'plan' => $slug,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'renews_at' => now()->addMonth(),
        ]);
    }

    private function currentPlan(?Subscription $subscription = null): array
    {
        $subscription ??= $this->subscriptions->current();

        if ($subscription->plan) {
            return $this->plans->find($subscription->plan);
        }

        return $this->plans->find(config('billing.trial_plan', 'enterprise'));
    }

    private function displayPlan(Subscription $subscription, array $plan): array
    {
        if ($subscription->isOnTrial()) {
            return [
                'slug' => null,
                'name' => 'Free trial',
                'price' => 0,
            ];
        }

        if ($subscription->plan) {
            return [
                'slug' => $subscription->plan,
                'name' => $plan['name'],
                'price' => $plan['price'],
            ];
        }

        return [
            'slug' => null,
            'name' => 'No plan selected',
            'price' => 0,
        ];
    }

    private function usageStats(): array
    {
        return [
            'agents' => $this->usage->agentCount(),
            'pending_invites' => $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
        ];
    }

    private function currentUsage(string $key): int
    {
        return match ($key) {
            'agents' => $this->usage->agentCount() + $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
            default => 0,
        };
    }

    private function formattedLimits(array $limits): array
    {
        return collect($limits)
            ->map(fn ($value) => $value === null ? 'unlimited' : $value)
            ->all();
    }
}
