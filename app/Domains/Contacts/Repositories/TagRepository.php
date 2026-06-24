<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TagRepository
{
    public function all(): Collection
    {
        return Tag::query()->orderBy('name')->get();
    }

    public function syncForContact(int $contactId, array $tagIds): void
    {
        $contact = \App\Domains\Contacts\Models\Contact::query()->findOrFail($contactId);
        $contact->tags()->sync($tagIds);
    }

    public function attachToTicket(int $ticketId, int $tagId): void
    {
        $ticket = \App\Domains\Tickets\Models\Ticket::query()->findOrFail($ticketId);
        $ticket->tags()->syncWithoutDetaching([$tagId]);
    }

    public function firstOrCreateByName(string $name): Tag
    {
        $slug = Str::slug($name);

        $tag = Tag::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'color' => 'blue'],
        );

        return $tag;
    }
}
