<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\StripeBillingRepository;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class StripeBackfillService
{
    public function __construct(
        private StripeBillingRepository $stripeBilling,
        private PlatformPaymentService $payments,
        private PlanRepository $plans,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->stripeBilling->isEnabled();
    }

    public function backfill(?string $tenantIdentifier = null, bool $syncPayments = true, bool $syncSubscriptions = true): array
    {
        if (! $this->isEnabled()) {
            throw new InvalidArgumentException('Stripe billing is not configured.');
        }

        $tenants = $this->resolveTenants($tenantIdentifier);
        $stats = [
            'tenants_processed' => 0,
            'tenants_skipped' => 0,
            'payments_synced' => 0,
            'subscriptions_synced' => 0,
        ];

        foreach ($tenants as $tenant) {
            if (! $tenant->stripe_id) {
                $stats['tenants_skipped']++;

                continue;
            }

            $stats['tenants_processed']++;

            if ($syncPayments) {
                $stats['payments_synced'] += $this->syncPayments($tenant);
            }

            if ($syncSubscriptions && $this->syncSubscription($tenant)) {
                $stats['subscriptions_synced']++;
            }
        }

        return $stats;
    }

    private function resolveTenants(?string $identifier): Collection
    {
        $query = Tenant::query()->orderBy('slug');

        if ($identifier) {
            $query->where(function ($builder) use ($identifier) {
                $builder
                    ->where('id', $identifier)
                    ->orWhere('slug', $identifier);
            });
        }

        return $query->get();
    }

    private function syncPayments(Tenant $tenant): int
    {
        $synced = 0;

        foreach ($this->stripeBilling->listInvoicesForCustomer($tenant->stripe_id) as $invoice) {
            if (($invoice->status ?? null) === 'paid') {
                $this->payments->recordFromStripeInvoice($invoice);
                $synced++;
            } elseif (($invoice->status ?? null) === 'open' && (int) ($invoice->attempt_count ?? 0) > 0) {
                $this->payments->recordFailedStripeInvoice($invoice);
                $synced++;
            }
        }

        return $synced;
    }

    private function syncSubscription(Tenant $tenant): bool
    {
        $subscriptions = $this->stripeBilling->listSubscriptionsForCustomer($tenant->stripe_id);
        $localSubscription = Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => null,
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(app(\App\Domains\Tenancy\Services\CentralSettingsService::class)->trialDays()),
            ],
        );

        $stripeSubscription = collect($subscriptions)
            ->sortByDesc(fn (object $subscription) => $subscription->created ?? 0)
            ->first(fn (object $subscription) => in_array($subscription->status ?? '', ['active', 'trialing'], true))
            ?? collect($subscriptions)
                ->sortByDesc(fn (object $subscription) => $subscription->canceled_at ?? $subscription->created ?? 0)
                ->first(fn (object $subscription) => in_array($subscription->status ?? '', ['canceled', 'past_due', 'unpaid'], true));

        if (! $stripeSubscription) {
            return false;
        }

        $this->lifecycle->applyStripeSubscription($localSubscription, $stripeSubscription);

        return true;
    }
}
