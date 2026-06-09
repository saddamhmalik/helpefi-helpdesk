<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContactRepository
{
    public function paginate(int $perPage = 15, ?string $search = null, ?string $access = null): LengthAwarePaginator
    {
        return Contact::query()
            ->with(['organization:id,name', 'tags:id,name,color', 'portalUser:id,contact_id,name,email,created_at'])
            ->withCount('tickets')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($access === 'portal', fn ($q) => $q->whereHas('portalUser'))
            ->when($access === 'guest', fn ($q) => $q->whereDoesntHave('portalUser'))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function exportRows(?string $search = null, ?string $access = null, callable $callback): void
    {
        $this->exportQuery($search, $access)
            ->chunkById(500, function ($contacts) use ($callback) {
                foreach ($contacts as $contact) {
                    $callback($contact);
                }
            });
    }

    private function exportQuery(?string $search, ?string $access)
    {
        return Contact::query()
            ->with(['organization:id,name', 'tags:id,name', 'portalUser:id,contact_id'])
            ->withCount('tickets')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($access === 'portal', fn ($q) => $q->whereHas('portalUser'))
            ->when($access === 'guest', fn ($q) => $q->whereDoesntHave('portalUser'))
            ->orderBy('id');
    }

    public function stats(): array
    {
        $total = Contact::query()->count();
        $portal = Contact::query()->whereHas('portalUser')->count();

        return [
            'total' => $total,
            'portal' => $portal,
            'guest' => $total - $portal,
        ];
    }

    public function allForSelect(): Collection
    {
        return Contact::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function searchForRequester(string $query, int $limit = 8): Collection
    {
        $term = trim($query);

        if ($term === '') {
            return collect();
        }

        $like = '%'.$term.'%';

        return Contact::query()
            ->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);
    }

    public function find(int $id): Contact
    {
        return Contact::query()
            ->with([
                'organization.domains',
                'tags',
                'notes.user:id,name',
                'activities.user:id,name',
                'tickets.status',
                'tickets.priority',
                'assets.type:id,name,slug',
                'portalUser:id,contact_id,name,email,created_at',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Contact
    {
        return Contact::query()->create($data);
    }

    public function findOrCreateByEmail(string $email, string $name): Contact
    {
        return Contact::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name],
        );
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact->fresh();
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }
}
