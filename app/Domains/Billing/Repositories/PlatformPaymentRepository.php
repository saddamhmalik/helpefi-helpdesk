<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Billing\Models\PlatformPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PlatformPaymentRepository
{
    public function upsertByPaymentId(string $paymentId, array $data): PlatformPayment
    {
        return PlatformPayment::query()->updateOrCreate(
            ['razorpay_payment_id' => $paymentId],
            $data,
        );
    }

    public function forTenant(string $tenantId, int $limit = 50): Collection
    {
        return PlatformPayment::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginate(int $perPage, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = PlatformPayment::query()
            ->with(['tenant.domains'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                        $tenantQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function stats(): array
    {
        $paid = PlatformPayment::query()->where('status', PlatformPayment::STATUS_PAID);
        $monthStart = Carbon::now()->startOfMonth();

        return [
            'total_collected' => (int) $paid->sum('amount'),
            'payment_count' => (int) PlatformPayment::query()->count(),
            'paid_count' => (int) $paid->count(),
            'month_collected' => (int) (clone $paid)->where('paid_at', '>=', $monthStart)->sum('amount'),
            'month_count' => (int) PlatformPayment::query()
                ->where('status', PlatformPayment::STATUS_PAID)
                ->where('paid_at', '>=', $monthStart)
                ->count(),
        ];
    }
}
