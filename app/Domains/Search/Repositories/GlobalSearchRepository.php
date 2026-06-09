<?php

namespace App\Domains\Search\Repositories;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Collection;

class GlobalSearchRepository
{
    public function tickets(string $query, int $limit): Collection
    {
        $like = "%{$query}%";

        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->where(function ($builder) use ($like) {
                $builder->where('subject', 'like', $like)
                    ->orWhere('number', 'like', $like);
            })
            ->with([
                'status:id,name,color',
                'contact:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'number', 'subject', 'ticket_status_id', 'contact_id']);
    }

    public function contacts(string $query, int $limit): Collection
    {
        $like = "%{$query}%";

        return Contact::query()
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);
    }

    public function organizations(string $query, int $limit): Collection
    {
        $like = "%{$query}%";

        return Organization::query()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);
    }
}
