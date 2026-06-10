<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Ai\Repositories\AiSettingRepository;
use App\Domains\Ai\Repositories\TicketAiRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Support\Str;

class AiTriageService
{
    public function __construct(
        private AiSettingRepository $settings,
        private TicketAiRepository $tickets,
        private TicketRepository $ticketRepository,
        private AiCompletionClient $client,
    ) {
    }

    public function isEnabled(): bool
    {
        $setting = $this->settings->current();

        return $setting->enabled && $setting->triage_enabled;
    }

    public function triage(int $ticketId): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $ticket = $this->tickets->forTicket($ticketId);
        $prioritySlug = $this->resolvePrioritySlug($ticket);

        if (! $prioritySlug) {
            return null;
        }

        $priority = TicketPriority::query()->where('slug', $prioritySlug)->first();

        if (! $priority || $priority->id === $ticket->ticket_priority_id) {
            return null;
        }

        $this->ticketRepository->update($ticket, ['ticket_priority_id' => $priority->id]);

        $note = "AI triage set priority to {$priority->name}.";

        $this->ticketRepository->addMessage($ticket, [
            'body' => $note,
            'is_internal' => true,
        ]);

        return [
            'priority_id' => $priority->id,
            'priority_slug' => $priority->slug,
            'source' => $this->client->available() ? 'openai' : 'local',
        ];
    }

    private function resolvePrioritySlug(Ticket $ticket): ?string
    {
        $text = Str::lower(trim($ticket->subject.' '.strip_tags($ticket->description ?? '')));

        if ($this->client->available()) {
            $response = Str::lower(trim($this->client->complete(
                'Classify helpdesk ticket priority. Reply with exactly one word: low, normal, high, or urgent. No other text.',
                "Subject: {$ticket->subject}\nDescription: ".strip_tags($ticket->description ?? ''),
            )));

            if (in_array($response, ['low', 'normal', 'high', 'urgent'], true)) {
                return $response;
            }
        }

        if (Str::contains($text, ['urgent', 'emergency', 'down', 'outage', 'critical', 'asap'])) {
            return 'urgent';
        }

        if (Str::contains($text, ['billing', 'invoice', 'payment', 'refund', 'charge'])) {
            return 'high';
        }

        if (Str::contains($text, ['question', 'how do', 'feature request', 'feedback'])) {
            return 'low';
        }

        return null;
    }
}
