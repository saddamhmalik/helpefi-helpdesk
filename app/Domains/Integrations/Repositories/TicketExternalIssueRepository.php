<?php

namespace App\Domains\Integrations\Repositories;

use App\Domains\Integrations\Models\TicketExternalIssue;
use Illuminate\Database\Eloquent\Collection;

class TicketExternalIssueRepository
{
    public function forTicket(int $ticketId): Collection
    {
        return TicketExternalIssue::query()
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->get();
    }

    public function findByExternal(string $provider, string $externalId): ?TicketExternalIssue
    {
        return TicketExternalIssue::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();
    }

    public function findByKey(string $provider, string $externalKey): ?TicketExternalIssue
    {
        return TicketExternalIssue::query()
            ->where('provider', $provider)
            ->where('external_key', $externalKey)
            ->first();
    }

    public function create(array $data): TicketExternalIssue
    {
        return TicketExternalIssue::query()->create($data);
    }

    public function update(TicketExternalIssue $issue, array $data): TicketExternalIssue
    {
        $issue->update($data);

        return $issue->fresh();
    }

    public function delete(TicketExternalIssue $issue): void
    {
        $issue->delete();
    }
}
