<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Chat\Models\ChatSession;
use App\Domains\Contacts\Models\ContactActivity;
use App\Domains\Contacts\Models\ContactNote;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Support\Str;

class ContactTimelineRepository
{
    public function collect(int $contactId, int $limit = 60): array
    {
        $events = [];

        foreach ($this->activities($contactId) as $event) {
            $events[] = $event;
        }

        foreach ($this->notes($contactId) as $event) {
            $events[] = $event;
        }

        foreach ($this->tickets($contactId) as $event) {
            $events[] = $event;
        }

        foreach ($this->customerMessages($contactId) as $event) {
            $events[] = $event;
        }

        foreach ($this->csatResponses($contactId) as $event) {
            $events[] = $event;
        }

        foreach ($this->chatSessions($contactId) as $event) {
            $events[] = $event;
        }

        usort($events, fn (array $a, array $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        return array_slice($events, 0, $limit);
    }

    private function activities(int $contactId): array
    {
        return ContactActivity::query()
            ->with('user:id,name')
            ->where('contact_id', $contactId)
            ->whereNotIn('type', ['note_added'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (ContactActivity $activity) => [
                'id' => 'activity-'.$activity->id,
                'type' => 'activity',
                'occurred_at' => $activity->created_at?->toIso8601String(),
                'title' => $activity->description,
                'body' => null,
                'actor' => $activity->user?->name ?? 'System',
                'ticket_id' => null,
                'ticket_number' => null,
                'meta' => ['activity_type' => $activity->type],
            ])
            ->all();
    }

    private function notes(int $contactId): array
    {
        return ContactNote::query()
            ->with('user:id,name')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ContactNote $note) => [
                'id' => 'note-'.$note->id,
                'type' => 'note',
                'occurred_at' => $note->created_at?->toIso8601String(),
                'title' => 'Internal note added',
                'body' => Str::limit(strip_tags($note->body), 240),
                'actor' => $note->user?->name ?? 'Agent',
                'ticket_id' => null,
                'ticket_number' => null,
                'meta' => ['note_id' => $note->id],
            ])
            ->all();
    }

    private function tickets(int $contactId): array
    {
        return Ticket::query()
            ->with('status:id,name')
            ->where('contact_id', $contactId)
            ->whereNull('merged_into_ticket_id')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'number', 'subject', 'created_at', 'ticket_status_id'])
            ->map(fn (Ticket $ticket) => [
                'id' => 'ticket-'.$ticket->id,
                'type' => 'ticket_opened',
                'occurred_at' => $ticket->created_at?->toIso8601String(),
                'title' => "Ticket {$ticket->number} opened",
                'body' => $ticket->subject,
                'actor' => 'Customer',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->number,
                'meta' => ['status' => $ticket->status?->name],
            ])
            ->all();
    }

    private function customerMessages(int $contactId): array
    {
        return TicketMessage::query()
            ->with(['ticket:id,number,subject', 'contact:id,name'])
            ->where('contact_id', $contactId)
            ->where('is_internal', false)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (TicketMessage $message) => [
                'id' => 'message-'.$message->id,
                'type' => 'customer_message',
                'occurred_at' => $message->created_at?->toIso8601String(),
                'title' => 'Customer replied on '.$message->ticket?->number,
                'body' => Str::limit(strip_tags($message->body), 240),
                'actor' => $message->contact?->name ?? 'Customer',
                'ticket_id' => $message->ticket_id,
                'ticket_number' => $message->ticket?->number,
                'meta' => ['message_id' => $message->id],
            ])
            ->all();
    }

    private function csatResponses(int $contactId): array
    {
        return CsatResponse::query()
            ->with('ticket:id,number,subject')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (CsatResponse $response) => [
                'id' => 'csat-'.$response->id,
                'type' => 'csat',
                'occurred_at' => $response->created_at?->toIso8601String(),
                'title' => 'CSAT rating: '.$response->rating.'/5',
                'body' => $response->comment ? Str::limit($response->comment, 240) : null,
                'actor' => 'Customer',
                'ticket_id' => $response->ticket_id,
                'ticket_number' => $response->ticket?->number,
                'meta' => [
                    'rating' => $response->rating,
                    'channel' => $response->channel,
                ],
            ])
            ->all();
    }

    private function chatSessions(int $contactId): array
    {
        return ChatSession::query()
            ->with('ticket:id,number')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (ChatSession $session) => [
                'id' => 'chat-'.$session->id,
                'type' => 'chat_session',
                'occurred_at' => $session->created_at?->toIso8601String(),
                'title' => $session->ticket_id
                    ? 'Live chat linked to '.$session->ticket?->number
                    : 'Live chat session started',
                'body' => $session->page_url ? Str::limit($session->page_url, 120) : null,
                'actor' => $session->visitor_name ?? 'Visitor',
                'ticket_id' => $session->ticket_id,
                'ticket_number' => $session->ticket?->number,
                'meta' => [
                    'session_uuid' => $session->uuid,
                    'closed_at' => $session->closed_at?->toIso8601String(),
                ],
            ])
            ->all();
    }
}
