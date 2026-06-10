<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Support\Carbon;

class CustomerContextRepository
{
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

    public function openTicketCount(array $contactIds): int
    {
        return Ticket::query()
            ->whereIn('contact_id', $contactIds)
            ->whereHas('status', fn ($query) => $query->where('is_closed', false))
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
