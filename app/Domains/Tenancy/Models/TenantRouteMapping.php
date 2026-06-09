<?php

namespace App\Domains\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRouteMapping extends Model
{
    public const TYPE_WIDGET_KEY = 'widget_key';

    public const TYPE_INBOUND_TOKEN = 'inbound_token';

    public const TYPE_INBOUND_EMAIL = 'inbound_email';

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'type',
        'lookup_key',
    ];
}
