<?php

namespace App\Domains\Billing\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPayment extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'stripe_invoice_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'plan',
        'customer_email',
        'customer_name',
        'description',
        'invoice_number',
        'invoice_url',
        'invoice_pdf',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
