<?php

namespace App\Domains\Chat\Repositories;

use App\Domains\Chat\Models\ChatSession;
use Illuminate\Support\Carbon;

class ChatSessionRepository
{
    public function findByUuid(string $uuid): ?ChatSession
    {
        return ChatSession::query()->where('uuid', $uuid)->first();
    }

    public function findByToken(string $token): ?ChatSession
    {
        return ChatSession::query()->where('token', $token)->first();
    }

    public function findOpenForContact(int $contactId, int $channelId): ?ChatSession
    {
        return ChatSession::query()
            ->where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->whereNull('closed_at')
            ->where('updated_at', '>=', Carbon::now()->subDay())
            ->orderByDesc('updated_at')
            ->first();
    }

    public function create(array $data): ChatSession
    {
        return ChatSession::query()->create($data);
    }

    public function touch(ChatSession $session, array $data = []): ChatSession
    {
        $session->update(array_merge($data, ['last_seen_at' => now()]));

        return $session->fresh();
    }

    public function close(ChatSession $session): ChatSession
    {
        $session->update(['closed_at' => now()]);

        return $session->fresh();
    }
}
