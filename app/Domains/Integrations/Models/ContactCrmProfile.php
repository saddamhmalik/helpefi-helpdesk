<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCrmProfile extends Model
{
    protected $fillable = [
        'contact_id',
        'provider',
        'external_id',
        'profile',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
