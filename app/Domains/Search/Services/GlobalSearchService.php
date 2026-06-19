<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Repositories\GlobalSearchRepository;

class GlobalSearchService
{
    public function __construct(private GlobalSearchRepository $repository)
    {
    }

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [
                'query' => $query,
                'groups' => [],
            ];
        }

        $groups = [];
        $remaining = $limit;

        $tickets = $this->repository->tickets($query, $remaining);

        if ($tickets->isNotEmpty()) {
            $groups[] = [
                'type' => 'tickets',
                'label' => 'Tickets',
                'items' => $tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'title' => $ticket->number,
                    'subtitle' => $ticket->subject,
                    'meta' => $ticket->status?->name,
                    'href' => "/tickets/{$ticket->id}",
                ])->values()->all(),
            ];
            $remaining = max(0, $limit - $tickets->count());
        }

        if ($remaining === 0) {
            return [
                'query' => $query,
                'groups' => $groups,
            ];
        }

        $contacts = $this->repository->contacts($query, $remaining);

        if ($contacts->isNotEmpty()) {
            $groups[] = [
                'type' => 'contacts',
                'label' => 'Customers',
                'items' => $contacts->map(fn ($contact) => [
                    'id' => $contact->id,
                    'title' => $contact->name,
                    'subtitle' => $contact->email,
                    'href' => "/contacts/{$contact->id}",
                ])->values()->all(),
            ];
            $remaining = max(0, $remaining - $contacts->count());
        }

        if ($remaining === 0) {
            return [
                'query' => $query,
                'groups' => $groups,
            ];
        }

        $organizations = $this->repository->organizations($query, $remaining);

        if ($organizations->isNotEmpty()) {
            $groups[] = [
                'type' => 'organizations',
                'label' => 'Organizations',
                'items' => $organizations->map(fn ($organization) => [
                    'id' => $organization->id,
                    'title' => $organization->name,
                    'href' => "/organizations/{$organization->id}",
                ])->values()->all(),
            ];
        }

        return [
            'query' => $query,
            'groups' => $groups,
        ];
    }
}
