<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Repositories\OrganizationRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrganizationService
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AuditRecorder $audit,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->organizations->paginate($perPage);
    }

    public function options(): Collection
    {
        return $this->organizations->allForSelect();
    }

    public function show(int $id): Organization
    {
        return $this->organizations->find($id);
    }

    public function create(array $data, array $domains = []): Organization
    {
        $organization = $this->organizations->create($data, $domains);

        $this->audit->record('organization.created', $organization, [
            'name' => $organization->name,
        ]);


        return $organization;
    }

    public function update(int $id, array $data, array $domains = []): Organization
    {
        $organization = $this->organizations->find($id);
        $before = $organization->only(array_keys($data));
        $organization = $this->organizations->update($organization, $data, $domains);

        $this->audit->recordChanges('organization.updated', $organization, $before, $organization->only(array_keys($data)), [
            'name' => $organization->name,
        ]);


        return $organization;
    }

    public function delete(int $id): void
    {
        $organization = $this->organizations->find($id);
        $this->organizations->delete($organization);

        $this->audit->record('organization.deleted', $organization, [
            'name' => $organization->name,
        ]);

    }
}
