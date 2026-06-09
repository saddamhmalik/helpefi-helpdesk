<?php

namespace App\Domains\SideConversations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SideConversationMessage extends Model
{
    protected $fillable = [
        'side_conversation_id',
        'user_id',
        'body',
        'is_inbound',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'is_inbound' => 'boolean',
        ];
    }

    public function sideConversation(): BelongsTo
    {
        return $this->belongsTo(SideConversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
