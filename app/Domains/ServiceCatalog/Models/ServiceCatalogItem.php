<?php

namespace App\Domains\ServiceCatalog\Models;

use App\Domains\Tickets\Models\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalogItem extends Model
{
    public const TYPE_INCIDENT = 'incident';

    public const TYPE_SERVICE_REQUEST = 'service_request';

    public const TYPE_CHANGE = 'change';

    public const TYPE_PROBLEM = 'problem';

    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'description',
        'ticket_type',
        'ticket_priority_id',
        'fields',
        'sort_order',
        'is_public',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Domains\Tickets\Models\Ticket::class);
    }
}
