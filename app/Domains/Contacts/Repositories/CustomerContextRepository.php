<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketStatusLookup;
use Illuminate\Support\Carbon;

class CustomerContextRepository
{
    public function __construct(private TicketStatusLookup $statusLookup)
    {
    }
    public function contactIdsForScope(Contact $contact): array
    {
        if ($contact->organization_id) {
            return Contact::query()
                ->where('organization_id', $contact->organization_id)
                ->pluck('id')
                ->all();
        }

        return [$contact->id];
    }

    public function metricsSummary(array $contactIds, Carbon $since): array
    {
        $lowSince = now()->subDays(30);

        $ticketStats = Ticket::query()
            ->whereIn('contact_id', $contactIds)
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw($this->statusLookup->sumOpenTicketsSelect('tickets.ticket_status_id', 'open_tickets'))
            ->selectRaw('MAX(tickets.updated_at) as last_ticket_activity_at')
            ->first();

        $slaBreaches = TicketSlaTimer::query()
            ->whereHas('ticket', fn ($query) => $query->whereIn('contact_id', $contactIds))
            ->where(function ($query) use ($since) {
                $query->where(function ($inner) use ($since) {
                    $inner->where('first_response_breached', true)
                        ->where('updated_at', '>=', $since);
                })->orWhere(function ($inner) use ($since) {
                    $inner->where('resolution_breached', true)
                        ->where('updated_at', '>=', $since);
                });
            })
            ->count();

        $csatRow = CsatResponse::query()
            ->whereIn('contact_id', $contactIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as responses')
            ->selectRaw('AVG(rating) as average')
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND rating <= 2 THEN 1 ELSE 0 END) as low_recent_count', [$lowSince])
            ->first();

        $lastCustomerAt = TicketMessage::query()
            ->whereNull('user_id')
            ->whereHas('ticket', fn ($query) => $query->whereIn('contact_id', $contactIds))
            ->max('created_at');

        $lastActivity = $lastCustomerAt ?: $ticketStats?->last_ticket_activity_at;

        return [
            'open_tickets' => (int) ($ticketStats?->open_tickets ?? 0),
            'total_tickets' => (int) ($ticketStats?->total_tickets ?? 0),
            'sla_breaches_90d' => $slaBreaches,
            'csat' => [
                'responses' => (int) ($csatRow?->responses ?? 0),
                'average' => ($csatRow?->responses ?? 0) > 0
                    ? round((float) $csatRow->average, 2)
                    : null,
                'low_recent' => ((int) ($csatRow?->low_recent_count ?? 0)) > 0,
            ],
            'last_contact_at' => $lastActivity ? Carbon::parse($lastActivity) : null,
        ];
    }

    public function openTicketCount(array $contactIds): int
    {
        return Ticket::query()
            ->whereIn('contact_id', $contactIds)
            ->tap(fn ($query) => $this->statusLookup->restrictToOpenStatusRelation($query))
            ->count();
    }

    public function totalTicketCount(array $contactIds): int
    {
        return Ticket::query()
            ->whereIn('contact_id', $contactIds)
            ->count();
    }

    public function slaBreachCount(array $contactIds, Carbon $since): int
    {
        return TicketSlaTimer::query()
            ->whereHas('ticket', fn ($query) => $query->whereIn('contact_id', $contactIds))
            ->where(function ($query) use ($since) {
                $query->where(function ($inner) use ($since) {
                    $inner->where('first_response_breached', true)
                        ->where('updated_at', '>=', $since);
                })->orWhere(function ($inner) use ($since) {
                    $inner->where('resolution_breached', true)
                        ->where('updated_at', '>=', $since);
                });
            })
            ->count();
    }

    public function csatSummary(array $contactIds, Carbon $since): array
    {
        $query = CsatResponse::query()
            ->whereIn('contact_id', $contactIds)
            ->where('created_at', '>=', $since);

        $total = (clone $query)->count();
        $average = $total > 0 ? round((float) (clone $query)->avg('rating'), 2) : null;
        $lowRecent = CsatResponse::query()
            ->whereIn('contact_id', $contactIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('rating', '<=', 2)
            ->exists();

        return [
            'responses' => $total,
            'average' => $average,
            'low_recent' => $lowRecent,
        ];
    }

    public function lastCustomerMessageAt(array $contactIds): ?Carbon
    {
        $timestamp = TicketMessage::query()
            ->whereNull('user_id')
            ->whereHas('ticket', fn ($query) => $query->whereIn('contact_id', $contactIds))
            ->max('created_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    public function lastTicketActivityAt(array $contactIds): ?Carbon
    {
        $timestamp = Ticket::query()
            ->whereIn('contact_id', $contactIds)
            ->max('updated_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
    }
}
