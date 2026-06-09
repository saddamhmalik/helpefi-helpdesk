<?php

namespace App\Console\Commands;

use App\Domains\Billing\Services\StripeBackfillService;
use Illuminate\Console\Command;

class BackfillStripeBillingCommand extends Command
{
    protected $signature = 'billing:backfill-stripe
                            {tenant? : Limit sync to a tenant slug or id}
                            {--payments : Sync invoice payments only}
                            {--subscriptions : Sync subscription state only}';

    protected $description = 'Backfill Stripe payments and subscription state from the Stripe API';

    public function handle(StripeBackfillService $backfill): int
    {
        if (! $backfill->isEnabled()) {
            $this->error('Stripe billing is not configured. Set STRIPE_ENABLED and STRIPE_SECRET.');

            return self::FAILURE;
        }

        $paymentsOnly = (bool) $this->option('payments');
        $subscriptionsOnly = (bool) $this->option('subscriptions');

        $syncPayments = $paymentsOnly || ! $subscriptionsOnly;
        $syncSubscriptions = $subscriptionsOnly || ! $paymentsOnly;

        $stats = $backfill->backfill(
            $this->argument('tenant'),
            $syncPayments,
            $syncSubscriptions,
        );

        $this->info("Processed {$stats['tenants_processed']} workspace(s).");

        if ($stats['tenants_skipped'] > 0) {
            $this->warn("Skipped {$stats['tenants_skipped']} workspace(s) without a Stripe customer.");
        }

        if ($syncPayments) {
            $this->line("Payments synced: {$stats['payments_synced']}");
        }

        if ($syncSubscriptions) {
            $this->line("Subscriptions synced: {$stats['subscriptions_synced']}");
        }

        return self::SUCCESS;
    }
}
