<?php

namespace App\Domains\Tickets\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Channels\Models\Channel;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    protected $fillable = [
        'channel_id',
        'email_inbox_id',
        'brand_id',
        'number',
        'type',
        'service_catalog_item_id',
        'subject',
        'description',
        'custom_fields',
        'contact_id',
        'assigned_to',
        'department_id',
        'team_id',
        'ticket_status_id',
        'ticket_priority_id',
        'closed_at',
        'snoozed_until',
        'merged_into_ticket_id',
        'csat_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'csat_email_sent_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function emailInbox(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Channels\Models\EmailInbox::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Brands\Models\Brand::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function ccs(): HasMany
    {
        return $this->hasMany(TicketCc::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_watchers');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'merged_into_ticket_id');
    }

    public function mergedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'merged_into_ticket_id');
    }

    public function slaTimer(): HasOne
    {
        return $this->hasOne(TicketSlaTimer::class);
    }

    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Assets\Models\Asset::class)->withTimestamps();
    }

    public function csatResponse(): HasOne
    {
        return $this->hasOne(\App\Domains\Csat\Models\CsatResponse::class);
    }

    public function sideConversations(): HasMany
    {
        return $this->hasMany(\App\Domains\SideConversations\Models\SideConversation::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(\App\Domains\TimeTracking\Models\TicketTimeEntry::class);
    }

    public function externalIssues(): HasMany
    {
        return $this->hasMany(\App\Domains\Integrations\Models\TicketExternalIssue::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Contacts\Models\Tag::class, 'ticket_tag');
    }

    public function scopeVisibleInQueue($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('snoozed_until')
                ->orWhere('snoozed_until', '<=', now());
        });
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }
}
