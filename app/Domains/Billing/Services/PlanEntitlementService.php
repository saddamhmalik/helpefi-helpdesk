<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Repositories\UsageRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class PlanEntitlementService implements FeatureEntitlementChecker
{
    private array $featureAccess = [];

    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UsageRepository $usage,
        private RazorpayBillingService $razorpay,
    ) {
    }

    public function canUseFeature(string $feature): bool
    {
        if (array_key_exists($feature, $this->featureAccess)) {
            return $this->featureAccess[$feature];
        }

        $subscription = $this->subscriptions->current();

        if (! $subscription->isAccessible()) {
            return $this->featureAccess[$feature] = false;
        }

        if (in_array($feature, PlanCatalogDefinition::baselineFeatures(), true)) {
            return $this->featureAccess[$feature] = true;
        }

        $plan = $this->currentPlan($subscription);

        if (in_array($feature, $plan['features'], true)) {
            return $this->featureAccess[$feature] = true;
        }

        return $this->featureAccess[$feature] = $this->hasActiveAddonFeature($subscription, $feature);
    }

    public function hasAddon(string $addonKey): bool
    {
        $subscription = $this->subscriptions->current();

        return in_array($addonKey, $subscription->active_addons ?? [], true);
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

    public function currentPlan(?Subscription $subscription = null): array
    {
        $subscription ??= $this->subscriptions->current();

        if ($subscription->plan) {
            return $this->plans->find($subscription->plan);
        }

        return $this->plans->find(config('billing.trial_plan', 'enterprise'));
    }

    public function effectiveFeatures(Subscription $subscription, array $plan): array
    {
        $features = array_merge(PlanCatalogDefinition::baselineFeatures(), $plan['features'] ?? []);

        foreach ($subscription->active_addons ?? [] as $addonKey) {
            $feature = AddonCatalogDefinition::featureForAddon($addonKey);

            if ($feature) {
                $features[] = $feature;
            }
        }

        return array_values(array_unique($features));
    }

    public function usageStats(): array
    {
        return [
            'agents' => $this->usage->agentCount(),
            'pending_invites' => $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
        ];
    }

    public function formattedLimits(array $limits): array
    {
        return collect($limits)
            ->map(fn ($value) => $value === null ? 'unlimited' : $value)
            ->all();
    }

    private function currentUsage(string $key): int
    {
        return match ($key) {
            'agents' => $this->usage->agentCount() + $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
            default => 0,
        };
    }

    private function hasActiveAddonFeature(Subscription $subscription, string $feature): bool
    {
        foreach ($subscription->active_addons ?? [] as $addonKey) {
            if (AddonCatalogDefinition::featureForAddon($addonKey) !== $feature) {
                continue;
            }

            return $subscription->isOnTrial() || $subscription->isActive();
        }

        return false;
    }
}
