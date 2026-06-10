<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\TicketStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TicketStatusRepository
{
    private const PROTECTED_SLUGS = ['open', 'closed'];

    public function all(): Collection
    {
        return TicketStatus::query()->orderBy('sort_order')->get();
    }

    public function find(int $id): TicketStatus
    {
        return TicketStatus::query()->findOrFail($id);
    }

    public function create(array $data): TicketStatus
    {
        $baseSlug = Str::slug($data['name']) ?: 'status';
        $slug = $baseSlug;
        $suffix = 1;

        while (TicketStatus::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $sortOrder = (int) TicketStatus::query()->max('sort_order') + 1;

        return TicketStatus::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?? 'slate',
            'sort_order' => $data['sort_order'] ?? $sortOrder,
            'is_closed' => $data['is_closed'] ?? false,
        ]);
    }

    public function update(TicketStatus $status, array $data): TicketStatus
    {
        $status->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? $status->color,
            'sort_order' => $data['sort_order'] ?? $status->sort_order,
            'is_closed' => $data['is_closed'] ?? $status->is_closed,
        ]);

        return $status->fresh();
    }

    public function delete(TicketStatus $status): void
    {
        $status->delete();
    }

    public function isProtected(TicketStatus $status): bool
    {
        return in_array($status->slug, self::PROTECTED_SLUGS, true);
    }

    public function ticketCount(TicketStatus $status): int
    {
        return $status->tickets()->whereNull('merged_into_ticket_id')->count();
    }
}
