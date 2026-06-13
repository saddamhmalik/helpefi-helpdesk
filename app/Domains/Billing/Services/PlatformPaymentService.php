<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\PlatformPayment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\PlatformPaymentRepository;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PlatformPaymentService
{
    public function __construct(
        private PlatformPaymentRepository $payments,
        private PlanRepository $plans,
    ) {}

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

    public function historyForTenant(string $tenantId): array
    {
        $catalog = $this->plans->all();

        return $this->payments->forTenant($tenantId)
            ->map(fn (PlatformPayment $payment) => $this->presentForTenant($payment, $catalog))
            ->all();
    }

    public function recordFromRazorpayPayment(array $payment, ?array $subscription = null): void
    {
        $paymentId = $payment['id'] ?? null;

        if (! $paymentId) {
            return;
        }

        $tenantId = $this->resolveTenantId($payment, $subscription);
        $plan = $this->resolvePlan($tenantId, $payment, $subscription);
        $paidAt = isset($payment['created_at'])
            ? Carbon::createFromTimestamp($payment['created_at'])
            : now();

        $this->payments->upsertByPaymentId($paymentId, [
            'tenant_id' => $tenantId,
            'razorpay_customer_id' => $payment['customer_id'] ?? null,
            'razorpay_subscription_id' => $subscription['id'] ?? ($payment['subscription_id'] ?? null),
            'razorpay_order_id' => $payment['order_id'] ?? null,
            'amount' => (int) ($payment['amount'] ?? 0),
            'currency' => strtoupper((string) ($payment['currency'] ?? config('billing.currency', 'INR'))),
            'status' => PlatformPayment::STATUS_PAID,
            'plan' => $plan,
            'customer_email' => $payment['email'] ?? null,
            'customer_name' => $payment['notes']['customer_name'] ?? null,
            'description' => $payment['description'] ?? $this->paymentDescription($payment, $subscription),
            'invoice_number' => $payment['invoice_id'] ?? null,
            'invoice_url' => null,
            'invoice_pdf' => null,
            'paid_at' => $paidAt,
        ]);
    }

    public function recordFailedRazorpayPayment(array $payment, ?array $subscription = null): void
    {
        $paymentId = $payment['id'] ?? null;

        if (! $paymentId) {
            return;
        }

        $tenantId = $this->resolveTenantId($payment, $subscription);

        $this->payments->upsertByPaymentId($paymentId, [
            'tenant_id' => $tenantId,
            'razorpay_customer_id' => $payment['customer_id'] ?? null,
            'razorpay_subscription_id' => $subscription['id'] ?? ($payment['subscription_id'] ?? null),
            'razorpay_order_id' => $payment['order_id'] ?? null,
            'amount' => (int) ($payment['amount'] ?? 0),
            'currency' => strtoupper((string) ($payment['currency'] ?? config('billing.currency', 'INR'))),
            'status' => PlatformPayment::STATUS_FAILED,
            'plan' => $this->resolvePlan($tenantId, $payment, $subscription),
            'customer_email' => $payment['email'] ?? null,
            'customer_name' => $payment['notes']['customer_name'] ?? null,
            'description' => $payment['description'] ?? $this->paymentDescription($payment, $subscription),
            'invoice_number' => $payment['invoice_id'] ?? null,
            'invoice_url' => null,
            'invoice_pdf' => null,
            'paid_at' => null,
        ]);
    }

    private function presentForTenant(PlatformPayment $payment, array $catalog): array
    {
        $plan = $payment->plan ? ($catalog[$payment->plan] ?? null) : null;

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'plan' => $payment->plan,
            'plan_name' => $plan['name'] ?? ($payment->plan ? ucfirst($payment->plan) : null),
            'description' => $payment->description,
            'invoice_number' => $payment->invoice_number,
            'invoice_url' => $payment->invoice_url,
            'invoice_pdf' => $payment->invoice_pdf,
            'razorpay_payment_id' => $payment->razorpay_payment_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    private function present(PlatformPayment $payment): array
    {
        $tenant = $payment->tenant;
        $domainService = app(TenantDomainService::class);
        $domain = $tenant ? $domainService->primaryHost($tenant) : null;
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
            'razorpay_payment_id' => $payment->razorpay_payment_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $domain,
                'url' => $tenant ? $domainService->primaryUrl($tenant) : null,
            ] : null,
        ];
    }

    private function resolveTenantId(array $payment, ?array $subscription): ?string
    {
        $notes = $subscription['notes'] ?? $payment['notes'] ?? [];
        $tenantId = $notes['tenant_id'] ?? null;

        if ($tenantId) {
            return $tenantId;
        }

        $customerId = $payment['customer_id'] ?? null;

        if ($customerId) {
            $tenantId = Tenant::query()->where('razorpay_customer_id', $customerId)->value('id');

            if ($tenantId) {
                return $tenantId;
            }
        }

        $subscriptionId = $subscription['id'] ?? ($payment['subscription_id'] ?? null);

        if ($subscriptionId) {
            $tenantId = Subscription::query()
                ->where('razorpay_subscription_id', $subscriptionId)
                ->value('tenant_id');

            if ($tenantId) {
                return $tenantId;
            }
        }

        return null;
    }

    private function resolvePlan(?string $tenantId, array $payment, ?array $subscription): ?string
    {
        if ($tenantId) {
            $plan = Subscription::query()->where('tenant_id', $tenantId)->value('plan');

            if ($plan) {
                return $plan;
            }
        }

        $notes = $subscription['notes'] ?? $payment['notes'] ?? [];
        $plan = $notes['plan'] ?? null;

        if ($plan) {
            return $plan;
        }

        return null;
    }

    private function paymentDescription(array $payment, ?array $subscription): ?string
    {
        if (! empty($payment['description'])) {
            return (string) $payment['description'];
        }

        $notes = $subscription['notes'] ?? [];

        if (! empty($notes['plan'])) {
            return ucfirst((string) $notes['plan']).' subscription';
        }

        return null;
    }
}
