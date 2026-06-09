<?php

namespace App\Domains\Channels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailInbox extends Model
{
    protected $fillable = [
        'brand_id',
        'department_id',
        'team_id',
        'name',
        'address',
        'aliases',
        'inbound_token',
        'is_active',
        'inbound_method',
        'poll_enabled',
        'mailbox_provider',
        'mailbox_protocol',
        'mailbox_host',
        'mailbox_port',
        'mailbox_encryption',
        'mailbox_username',
        'mailbox_password',
        'mailbox_folder',
        'mailbox_processed_ids',
        'last_polled_at',
        'poll_error',
        'oauth_provider',
        'oauth_access_token',
        'oauth_refresh_token',
        'oauth_token_expires_at',
        'oauth_connected_email',
        'oauth_metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'poll_enabled' => 'boolean',
            'aliases' => 'array',
            'mailbox_port' => 'integer',
            'mailbox_password' => 'encrypted',
            'mailbox_processed_ids' => 'array',
            'last_polled_at' => 'datetime',
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_token_expires_at' => 'datetime',
            'oauth_metadata' => 'array',
        ];
    }

    protected $hidden = [
        'inbound_token',
        'mailbox_password',
        'oauth_access_token',
        'oauth_refresh_token',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Brands\Models\Brand::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Workforce\Models\Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Workforce\Models\Team::class);
    }

    public function isOAuthConnected(): bool
    {
        return $this->inbound_method === 'oauth'
            && $this->oauth_provider
            && $this->oauth_refresh_token;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Domains\Tickets\Models\Ticket::class);
    }
}
