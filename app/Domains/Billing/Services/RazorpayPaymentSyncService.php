<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\Error;

class RazorpayPaymentSyncService
{
    public function __construct(
        private RazorpayApiClient $api,
        private PlatformPaymentService $payments,
    ) {
    }

    public function syncTenantPaymentHistory(string $tenantId): void
    {
        if (! $this->api->isEnabled()) {
            return;
        }

        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        if (! $subscription) {
            return;
        }

        $subscriptionEntities = $this->subscriptionEntitiesForTenant($subscription, $tenantId);
        $syncedPaymentIds = [];

        foreach ($subscriptionEntities as $subscriptionId => $subscriptionEntity) {
            $syncedPaymentIds = array_merge(
                $syncedPaymentIds,
                $this->syncPaymentsFromSubscriptionInvoices($subscriptionId, $subscriptionEntity, $tenantId),
            );
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant?->razorpay_customer_id) {
            $this->syncPaymentsFromCustomer(
                $tenant->razorpay_customer_id,
                $tenantId,
                $subscriptionEntities,
                $syncedPaymentIds,
            );
        }
    }

    private function subscriptionEntitiesForTenant(?Subscription $subscription, string $tenantId): array
    {
        if (! $subscription) {
            return [];
        }

        $subscriptionIds = array_values(array_filter(array_unique(array_merge(
            $subscription->razorpay_subscription_id ? [(string) $subscription->razorpay_subscription_id] : [],
            array_map('strval', array_values($subscription->razorpay_addon_items ?? [])),
        ))));

        $entities = [];

        foreach ($subscriptionIds as $subscriptionId) {
            $entity = $this->fetchSubscriptionEntityForSync($subscriptionId, $tenantId);

            if ($entity !== null) {
                $entities[$subscriptionId] = $entity;
            }
        }

        return $entities;
    }

    private function fetchSubscriptionEntityForSync(string $subscriptionId, string $tenantId): ?array
    {
        try {
            $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed during payment sync', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (empty($entity['notes']['tenant_id'])) {
            $entity['notes']['tenant_id'] = $tenantId;
        }

        return $entity;
    }

    private function syncPaymentsFromSubscriptionInvoices(string $subscriptionId, array $subscriptionEntity, string $tenantId): array
    {
        try {
            $response = $this->api->client()->invoice->all([
                'subscription_id' => $subscriptionId,
                'count' => 100,
            ])->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay invoice list failed during payment sync', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $syncedPaymentIds = [];

        foreach ($response['items'] ?? [] as $invoice) {
            if (! is_array($invoice)) {
                continue;
            }

            $paymentId = $this->paymentIdFromInvoice($invoice);

            if ($paymentId === null || in_array($paymentId, $syncedPaymentIds, true)) {
                continue;
            }

            if ($this->recordSyncedPayment($paymentId, $subscriptionEntity, $tenantId)) {
                $syncedPaymentIds[] = $paymentId;
            }
        }

        return $syncedPaymentIds;
    }

    private function syncPaymentsFromCustomer(
        string $customerId,
        string $tenantId,
        array $subscriptionEntities,
        array $syncedPaymentIds,
    ): void {
        try {
            $response = $this->api->client()->invoice->all([
                'customer_id' => $customerId,
                'count' => 100,
            ])->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay customer invoice list failed during payment sync', [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $this->recordPaymentsFromInvoices(
            $response['items'] ?? [],
            $subscriptionEntities,
            $tenantId,
            $syncedPaymentIds,
        );
    }

    private function recordPaymentsFromInvoices(
        array $invoices,
        array $subscriptionEntities,
        string $tenantId,
        array $syncedPaymentIds,
    ): void {
        foreach ($invoices as $invoice) {
            if (! is_array($invoice)) {
                continue;
            }

            $paymentId = $this->paymentIdFromInvoice($invoice);

            if ($paymentId === null || in_array($paymentId, $syncedPaymentIds, true)) {
                continue;
            }

            $subscriptionId = (string) ($invoice['subscription_id'] ?? '');
            $subscriptionEntity = $subscriptionId !== ''
                ? ($subscriptionEntities[$subscriptionId] ?? $this->fetchSubscriptionEntityForSync($subscriptionId, $tenantId))
                : ['notes' => ['tenant_id' => $tenantId]];

            if ($subscriptionEntity === null) {
                $subscriptionEntity = ['notes' => ['tenant_id' => $tenantId]];
            }

            $this->recordSyncedPayment($paymentId, $subscriptionEntity, $tenantId);
        }
    }

    private function paymentIdFromInvoice(array $invoice): ?string
    {
        if (($invoice['status'] ?? '') !== 'paid') {
            return null;
        }

        $paymentId = (string) ($invoice['payment_id'] ?? '');

        return $paymentId !== '' ? $paymentId : null;
    }

    private function recordSyncedPayment(string $paymentId, array $subscriptionEntity, string $tenantId): bool
    {
        $payment = $this->api->fetchPaymentArray($paymentId);

        if ($payment === null) {
            Log::warning('Razorpay payment fetch failed during payment sync', [
                'payment_id' => $paymentId,
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
            return false;
        }

        $this->payments->recordFromRazorpayPayment($payment, $subscriptionEntity);

        return true;
    }
}
