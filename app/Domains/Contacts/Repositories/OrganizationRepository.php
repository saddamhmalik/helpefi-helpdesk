<?php

namespace App\Domains\Contacts\Repositories;

use App\Domains\Contacts\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrganizationRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Organization::query()
            ->withCount('contacts')
            ->with('domains')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allForSelect(): Collection
    {
        return Organization::query()->orderBy('name')->get(['id', 'name']);
    }

    public function find(int $id): Organization
    {
        return Organization::query()
            ->with(['domains', 'contacts' => fn ($q) => $q->withCount('tickets')])
            ->findOrFail($id);
    }

    public function create(array $data, array $domains = []): Organization
    {
        $organization = Organization::query()->create($data);
        $this->syncDomains($organization, $domains);

        return $organization->load('domains');
    }

    public function update(Organization $organization, array $data, array $domains = []): Organization
    {
        $organization->update($data);
        $this->syncDomains($organization, $domains);

        return $organization->fresh(['domains']);
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }

    private function syncDomains(Organization $organization, array $domains): void
    {
        $organization->domains()->delete();

        foreach (array_unique(array_filter($domains)) as $domain) {
            $organization->domains()->create([
                'domain' => strtolower(trim($domain)),
            ]);
        }
    }
}
