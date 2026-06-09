<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Repositories\UsageRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UsageRepository $usage,
    ) {
    }

    public function snapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->plans->find($subscription->plan);
        $usage = $this->usageStats();

        return [
            'plan' => [
                'slug' => $subscription->plan,
                'name' => $plan['name'],
                'price' => $plan['price'],
            ],
            'status' => $subscription->status,
            'renews_at' => $subscription->renews_at?->toIso8601String(),
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
                ])
                ->values()
                ->all(),
        ];
    }

    public function canUseFeature(string $feature): bool
    {
        if (! $this->subscriptions->current()->isActive()) {
            return false;
        }

        $plan = $this->currentPlan();

        return in_array($feature, $plan['features'], true);
    }

    public function withinLimit(string $key, int $buffer = 0): bool
    {
        if (! $this->subscriptions->current()->isActive()) {
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

    public function changePlan(string $slug): Subscription
    {
        $this->plans->find($slug);
        $subscription = $this->subscriptions->current();

        return $this->subscriptions->update($subscription, [
            'plan' => $slug,
            'status' => Subscription::STATUS_ACTIVE,
            'renews_at' => now()->addMonth(),
        ]);
    }

    private function currentPlan(): array
    {
        return $this->plans->find($this->subscriptions->current()->plan);
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
