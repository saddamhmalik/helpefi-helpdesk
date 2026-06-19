<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Chat\Models\ChatSession;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\ContactActivity;
use App\Domains\Contacts\Models\ContactNote;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactTimelineRepository
{
    public function collect(int $contactId, int $limit = 60): array
    {
        $rows = DB::query()
            ->fromSub($this->timelineUnion($contactId), 'timeline_events')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        return $this->hydrateEvents($contactId, $rows, $limit);
    }

    private function timelineUnion(int $contactId)
    {
        $activity = ContactActivity::query()
            ->where('contact_id', $contactId)
            ->whereNotIn('type', ['note_added'])
            ->selectRaw("'activity' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(30);

        $notes = ContactNote::query()
            ->where('contact_id', $contactId)
            ->selectRaw("'note' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(20);

        $tickets = Ticket::query()
            ->where('contact_id', $contactId)
            ->whereNull('merged_into_ticket_id')
            ->selectRaw("'ticket' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(30);

        $messages = TicketMessage::query()
            ->where('contact_id', $contactId)
            ->where('is_internal', false)
            ->selectRaw("'message' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(25);

        $csat = CsatResponse::query()
            ->where('contact_id', $contactId)
            ->selectRaw("'csat' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(20);

        $chats = ChatSession::query()
            ->where('contact_id', $contactId)
            ->selectRaw("'chat' as source_type, id as source_id, created_at as occurred_at")
            ->orderByDesc('created_at')
            ->limit(15);

        return $activity
            ->unionAll($notes)
            ->unionAll($tickets)
            ->unionAll($messages)
            ->unionAll($csat)
            ->unionAll($chats);
    }

    private function hydrateEvents(int $contactId, Collection $rows, int $limit): array
    {
        $grouped = $rows->groupBy('source_type');
        $events = [];

        $activityIds = $grouped->get('activity')?->pluck('source_id')->all() ?? [];
        $noteIds = $grouped->get('note')?->pluck('source_id')->all() ?? [];
        $ticketIds = $grouped->get('ticket')?->pluck('source_id')->all() ?? [];
        $messageIds = $grouped->get('message')?->pluck('source_id')->all() ?? [];
        $csatIds = $grouped->get('csat')?->pluck('source_id')->all() ?? [];
        $chatIds = $grouped->get('chat')?->pluck('source_id')->all() ?? [];

        $activities = $activityIds === []
            ? collect()
            : ContactActivity::query()
                ->where('contact_id', $contactId)
                ->whereIn('id', $activityIds)
                ->get(['id', 'type', 'description', 'created_at', 'user_id'])
                ->keyBy('id');

        $notes = $noteIds === []
            ? collect()
            : ContactNote::query()
                ->where('contact_id', $contactId)
                ->whereIn('id', $noteIds)
                ->get(['id', 'body', 'created_at', 'user_id'])
                ->keyBy('id');

        $messages = $messageIds === []
            ? collect()
            : TicketMessage::query()
                ->where('contact_id', $contactId)
                ->whereIn('id', $messageIds)
                ->get(['id', 'body', 'created_at', 'ticket_id', 'contact_id'])
                ->keyBy('id');

        $csatResponses = $csatIds === []
            ? collect()
            : CsatResponse::query()
                ->where('contact_id', $contactId)
                ->whereIn('id', $csatIds)
                ->get(['id', 'rating', 'comment', 'created_at', 'ticket_id', 'channel'])
                ->keyBy('id');

        $chatSessions = $chatIds === []
            ? collect()
            : ChatSession::query()
                ->where('contact_id', $contactId)
                ->whereIn('id', $chatIds)
                ->get(['id', 'uuid', 'created_at', 'ticket_id', 'page_url', 'visitor_name', 'closed_at'])
                ->keyBy('id');

        $relatedTicketIds = collect($ticketIds)
            ->merge($messages->pluck('ticket_id'))
            ->merge($csatResponses->pluck('ticket_id'))
            ->merge($chatSessions->pluck('ticket_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $tickets = $relatedTicketIds === []
            ? collect()
            : Ticket::query()
                ->with('status:id,name')
                ->whereIn('id', $relatedTicketIds)
                ->get(['id', 'number', 'subject', 'created_at', 'ticket_status_id'])
                ->keyBy('id');

        $userIds = $activities->pluck('user_id')
            ->merge($notes->pluck('user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $users = $userIds === []
            ? collect()
            : User::query()
                ->whereIn('id', $userIds)
                ->get(['id', 'name'])
                ->keyBy('id');

        $contactName = $messages->isEmpty()
            ? null
            : Contact::query()->whereKey($contactId)->value('name');

        foreach ($grouped->get('activity', collect()) as $row) {
            $activity = $activities->get($row->source_id);
            if (! $activity) {
                continue;
            }

            $events[] = [
                'id' => 'activity-'.$activity->id,
                'type' => 'activity',
                'occurred_at' => $activity->created_at?->toIso8601String(),
                'title' => $activity->description,
                'body' => null,
                'actor' => $users->get($activity->user_id)?->name ?? 'System',
                'ticket_id' => null,
                'ticket_number' => null,
                'meta' => ['activity_type' => $activity->type],
            ];
        }

        foreach ($grouped->get('note', collect()) as $row) {
            $note = $notes->get($row->source_id);
            if (! $note) {
                continue;
            }

            $events[] = [
                'id' => 'note-'.$note->id,
                'type' => 'note',
                'occurred_at' => $note->created_at?->toIso8601String(),
                'title' => 'Internal note added',
                'body' => Str::limit(strip_tags($note->body), 240),
                'actor' => $users->get($note->user_id)?->name ?? 'Agent',
                'ticket_id' => null,
                'ticket_number' => null,
                'meta' => ['note_id' => $note->id],
            ];
        }

        foreach ($grouped->get('ticket', collect()) as $row) {
            $ticket = $tickets->get($row->source_id);
            if (! $ticket) {
                continue;
            }

            $events[] = [
                'id' => 'ticket-'.$ticket->id,
                'type' => 'ticket_opened',
                'occurred_at' => $ticket->created_at?->toIso8601String(),
                'title' => "Ticket {$ticket->number} opened",
                'body' => $ticket->subject,
                'actor' => 'Customer',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->number,
                'meta' => ['status' => $ticket->status?->name],
            ];
        }

        foreach ($grouped->get('message', collect()) as $row) {
            $message = $messages->get($row->source_id);
            if (! $message) {
                continue;
            }

            $ticket = $tickets->get($message->ticket_id);

            $events[] = [
                'id' => 'message-'.$message->id,
                'type' => 'customer_message',
                'occurred_at' => $message->created_at?->toIso8601String(),
                'title' => 'Customer replied on '.$ticket?->number,
                'body' => Str::limit(strip_tags($message->body), 240),
                'actor' => $contactName ?? 'Customer',
                'ticket_id' => $message->ticket_id,
                'ticket_number' => $ticket?->number,
                'meta' => ['message_id' => $message->id],
            ];
        }

        foreach ($grouped->get('csat', collect()) as $row) {
            $response = $csatResponses->get($row->source_id);
            if (! $response) {
                continue;
            }

            $ticket = $tickets->get($response->ticket_id);

            $events[] = [
                'id' => 'csat-'.$response->id,
                'type' => 'csat',
                'occurred_at' => $response->created_at?->toIso8601String(),
                'title' => 'CSAT rating: '.$response->rating.'/5',
                'body' => $response->comment ? Str::limit($response->comment, 240) : null,
                'actor' => 'Customer',
                'ticket_id' => $response->ticket_id,
                'ticket_number' => $ticket?->number,
                'meta' => [
                    'rating' => $response->rating,
                    'channel' => $response->channel,
                ],
            ];
        }

        foreach ($grouped->get('chat', collect()) as $row) {
            $session = $chatSessions->get($row->source_id);
            if (! $session) {
                continue;
            }

            $ticket = $tickets->get($session->ticket_id);

            $events[] = [
                'id' => 'chat-'.$session->id,
                'type' => 'chat_session',
                'occurred_at' => $session->created_at?->toIso8601String(),
                'title' => $session->ticket_id
                    ? 'Live chat linked to '.$ticket?->number
                    : 'Live chat session started',
                'body' => $session->page_url ? Str::limit($session->page_url, 120) : null,
                'actor' => $session->visitor_name ?? 'Visitor',
                'ticket_id' => $session->ticket_id,
                'ticket_number' => $ticket?->number,
                'meta' => [
                    'session_uuid' => $session->uuid,
                    'closed_at' => $session->closed_at?->toIso8601String(),
                ],
            ];
        }

        usort($events, fn (array $a, array $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        return array_slice($events, 0, $limit);
    }
}
