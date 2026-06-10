<?php

namespace App\Domains\ServiceDesk\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'ticket_id',
        'service_catalog_item_id',
        'subject',
        'status',
        'current_step',
        'requested_by_user_id',
        'requester_contact_id',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class, 'service_catalog_item_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requesterContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'requester_contact_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class)->orderBy('step_order');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
