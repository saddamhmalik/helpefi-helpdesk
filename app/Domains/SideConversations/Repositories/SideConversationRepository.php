<?php

namespace App\Domains\SideConversations\Repositories;

use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Models\SideConversationMessage;
use Illuminate\Database\Eloquent\Collection;

class SideConversationRepository
{
    public function forTicket(int $ticketId): Collection
    {
        return SideConversation::query()
            ->where('ticket_id', $ticketId)
            ->with([
                'creator:id,name,email',
                'messages.user:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function find(int $id): SideConversation
    {
        return SideConversation::query()
            ->with(['ticket', 'messages.user:id,name,email'])
            ->findOrFail($id);
    }

    public function findForTicket(int $ticketId, int $sideConversationId): SideConversation
    {
        return SideConversation::query()
            ->where('ticket_id', $ticketId)
            ->with(['ticket', 'messages.user:id,name,email'])
            ->findOrFail($sideConversationId);
    }

    public function create(array $data): SideConversation
    {
        return SideConversation::query()->create($data);
    }

    public function update(SideConversation $conversation, array $data): SideConversation
    {
        $conversation->update($data);

        return $conversation->fresh(['creator:id,name,email', 'messages.user:id,name,email']);
    }

    public function addMessage(SideConversation $conversation, array $data): SideConversationMessage
    {
        $message = $conversation->messages()->create($data);
        $conversation->touch();

        return $message->load('user:id,name,email');
    }

    public function findById(int $id): ?SideConversation
    {
        return SideConversation::query()->with('ticket')->find($id);
    }

    public function findByMessageReferences(array $referenceIds): ?SideConversation
    {
        if ($referenceIds === []) {
            return null;
        }

        $message = SideConversationMessage::query()
            ->whereIn('external_id', $referenceIds)
            ->with('sideConversation.ticket')
            ->first();

        return $message?->sideConversation;
    }

    public function findOpenByRecipientAndSubject(string $email, string $normalizedSubject): ?SideConversation
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        return SideConversation::query()
            ->where('status', SideConversation::STATUS_OPEN)
            ->where('recipient_email', $email)
            ->whereRaw('LOWER(subject) = ?', [strtolower($normalizedSubject)])
            ->with('ticket')
            ->orderByDesc('updated_at')
            ->first();
    }

    public function messageExistsByExternalId(string $externalId): bool
    {
        return SideConversationMessage::query()
            ->where('external_id', $externalId)
            ->exists();
    }
}
