<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    public const SOURCE_CONTACT = 'contact';

    public const SOURCE_HOMEPAGE = 'homepage';

    public const SOURCE_CHATBOT = 'chatbot';

    public const SOURCE_REGISTRATION = 'registration';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SPAM = 'spam';

    protected $connection = 'central';

    protected $fillable = [
        'email',
        'name',
        'company',
        'source',
        'intent',
        'status',
        'topic',
        'message',
        'marketing_consent_at',
        'metadata',
        'ip_address',
        'user_agent',
        'pending_registration_id',
        'notes',
        'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'marketing_consent_at' => 'datetime',
            'contacted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
