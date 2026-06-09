<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\PlatformPayment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\PlatformPaymentRepository;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PlatformPaymentService
{
    public function __construct(
        private PlatformPaymentRepository $payments,
        private PlanRepository $plans,
    ) {
    }

    public function list(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->payments
            ->paginate($perPage, $search, $status === 'all' ? null : $status)
            ->through(fn (PlatformPayment $payment) => $this->present($payment));
    }

    public function stats(): array
    {
        return $this->payments->stats();
    }

    public function recordFromStripeInvoice(object $invoice): void
    {
        $invoiceId = $invoice->id ?? null;

        if (! $invoiceId) {
            return;
        }

        $tenantId = $this->resolveTenantId($invoice);
        $plan = $this->resolvePlan($tenantId, $invoice);
        $paidAt = isset($invoice->status_transitions->paid_at)
            ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
            : (isset($invoice->created) ? Carbon::createFromTimestamp($invoice->created) : now());

        $this->payments->upsertByInvoiceId($invoiceId, [
            'tenant_id' => $tenantId,
            'stripe_customer_id' => is_string($invoice->customer ?? null) ? $invoice->customer : null,
            'stripe_subscription_id' => is_string($invoice->subscription ?? null) ? $invoice->subscription : null,
            'stripe_payment_intent_id' => is_string($invoice->payment_intent ?? null) ? $invoice->payment_intent : null,
            'amount' => (int) ($invoice->amount_paid ?? 0),
            'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
            'status' => PlatformPayment::STATUS_PAID,
            'plan' => $plan,
            'customer_email' => $invoice->customer_email ?? null,
            'customer_name' => $invoice->customer_name ?? null,
            'description' => $this->invoiceDescription($invoice),
            'invoice_number' => $invoice->number ?? null,
            'invoice_url' => $invoice->hosted_invoice_url ?? null,
            'invoice_pdf' => $invoice->invoice_pdf ?? null,
            'paid_at' => $paidAt,
        ]);
    }

    public function recordFailedStripeInvoice(object $invoice): void
    {
        $invoiceId = $invoice->id ?? null;

        if (! $invoiceId) {
            return;
        }

        $tenantId = $this->resolveTenantId($invoice);

        $this->payments->upsertByInvoiceId($invoiceId, [
            'tenant_id' => $tenantId,
            'stripe_customer_id' => is_string($invoice->customer ?? null) ? $invoice->customer : null,
            'stripe_subscription_id' => is_string($invoice->subscription ?? null) ? $invoice->subscription : null,
            'stripe_payment_intent_id' => is_string($invoice->payment_intent ?? null) ? $invoice->payment_intent : null,
            'amount' => (int) ($invoice->amount_due ?? 0),
            'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
            'status' => PlatformPayment::STATUS_FAILED,
            'plan' => $this->resolvePlan($tenantId, $invoice),
            'customer_email' => $invoice->customer_email ?? null,
            'customer_name' => $invoice->customer_name ?? null,
            'description' => $this->invoiceDescription($invoice),
            'invoice_number' => $invoice->number ?? null,
            'invoice_url' => $invoice->hosted_invoice_url ?? null,
            'invoice_pdf' => $invoice->invoice_pdf ?? null,
            'paid_at' => null,
        ]);
    }

    private function present(PlatformPayment $payment): array
    {
        $tenant = $payment->tenant;
        $domain = $tenant?->domains->first()?->domain;
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $plan = $payment->plan ? ($this->plans->all()[$payment->plan] ?? null) : null;

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'plan' => $payment->plan,
            'plan_name' => $plan['name'] ?? ($payment->plan ? ucfirst($payment->plan) : null),
            'customer_email' => $payment->customer_email,
            'customer_name' => $payment->customer_name,
            'description' => $payment->description,
            'invoice_number' => $payment->invoice_number,
            'invoice_url' => $payment->invoice_url,
            'invoice_pdf' => $payment->invoice_pdf,
            'stripe_invoice_id' => $payment->stripe_invoice_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $domain,
                'url' => $domain ? "{$scheme}://{$domain}" : null,
            ] : null,
        ];
    }

    private function resolveTenantId(object $invoice): ?string
    {
        $customerId = is_string($invoice->customer ?? null) ? $invoice->customer : null;

        if ($customerId) {
            $tenantId = Tenant::query()->where('stripe_id', $customerId)->value('id');

            if ($tenantId) {
                return $tenantId;
            }
        }

        $subscriptionId = is_string($invoice->subscription ?? null) ? $invoice->subscription : null;

        if ($subscriptionId) {
            $tenantId = Subscription::query()
                ->where('stripe_subscription_id', $subscriptionId)
                ->value('tenant_id');

            if ($tenantId) {
                return $tenantId;
            }
        }

        return null;
    }

    private function resolvePlan(?string $tenantId, object $invoice): ?string
    {
        if ($tenantId) {
            $plan = Subscription::query()->where('tenant_id', $tenantId)->value('plan');

            if ($plan) {
                return $plan;
            }
        }

        $lines = $invoice->lines->data ?? [];

        foreach ($lines as $line) {
            $plan = $line->metadata->plan ?? $line->price->metadata->plan ?? null;

            if ($plan) {
                return $plan;
            }
        }

        return null;
    }

    private function invoiceDescription(object $invoice): ?string
    {
        if (! empty($invoice->description)) {
            return (string) $invoice->description;
        }

        $lines = $invoice->lines->data ?? [];
        $firstLine = $lines[0] ?? null;

        if ($firstLine && ! empty($firstLine->description)) {
            return (string) $firstLine->description;
        }

        return null;
    }
}
