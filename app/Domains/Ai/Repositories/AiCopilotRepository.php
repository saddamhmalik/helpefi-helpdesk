<?php

namespace App\Domains\Ai\Repositories;

use App\Domains\Ai\Models\AiCopilotMessage;
use Illuminate\Support\Collection;

class AiCopilotRepository
{
    public function history(int $ticketId, int $userId, int $limit = 20): Collection
    {
        return AiCopilotMessage::query()
            ->where('ticket_id', $ticketId)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'role', 'content', 'created_at']);
    }

    public function store(int $ticketId, int $userId, string $role, string $content): AiCopilotMessage
    {
        return AiCopilotMessage::query()->create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'role' => $role,
            'content' => $content,
        ]);
    }

    public function clear(int $ticketId, int $userId): void
    {
        AiCopilotMessage::query()
            ->where('ticket_id', $ticketId)
            ->where('user_id', $userId)
            ->delete();
    }
}
