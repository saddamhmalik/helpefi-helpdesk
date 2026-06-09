<?php

namespace App\Domains\Channels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailSetting extends Model
{
    public const DELIVERY_SYNC = 'sync';

    public const DELIVERY_QUEUE = 'queue';

    public const QUEUE_SYNC = 'sync';

    public const QUEUE_DATABASE = 'database';

    public const QUEUE_REDIS = 'redis';

    protected $fillable = [
        'enabled',
        'reply_enabled',
        'delivery_mode',
        'queue_connection',
        'use_inbox_smtp',
        'email_inbox_id',
        'driver',
        'from_address',
        'from_name',
        'reply_to_address',
        'automatic_bcc',
        'use_agent_name_in_from',
        'host',
        'port',
        'encryption',
        'username',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'reply_enabled' => 'boolean',
            'use_inbox_smtp' => 'boolean',
            'use_agent_name_in_from' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    protected $hidden = [
        'password',
    ];

    public function emailInbox(): BelongsTo
    {
        return $this->belongsTo(EmailInbox::class);
    }
}
