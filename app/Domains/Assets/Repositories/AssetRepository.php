<?php

namespace App\Domains\Assets\Repositories;

use App\Domains\Assets\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AssetRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Asset::query()
            ->with(['type:id,name,slug', 'contact:id,name,email', 'organization:id,name', 'parent:id,asset_tag,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['asset_type_id'] ?? null, fn ($query, $typeId) => $query->where('asset_type_id', $typeId))
            ->when($filters['contact_id'] ?? null, fn ($query, $contactId) => $query->where('contact_id', $contactId))
            ->when($filters['organization_id'] ?? null, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(filter_var($filters['unassigned'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($query) => $query->whereNull('contact_id'))
            ->when(filter_var($filters['warranty_expiring'] ?? false, FILTER_VALIDATE_BOOLEAN), function ($query) {
                $query->whereNotNull('warranty_expires_at')
                    ->whereBetween('warranty_expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            })
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Asset
    {
        return Asset::query()
            ->with([
                'type',
                'contact:id,name,email',
                'organization:id,name',
                'parent:id,asset_tag,name',
                'children.type:id,name,slug',
                'tickets:id,number,subject,ticket_status_id',
                'tickets.status:id,name,slug',
                'assignmentLogs.contact:id,name,email',
                'assignmentLogs.organization:id,name',
                'assignmentLogs.changedBy:id,name',
            ])
            ->findOrFail($id);
    }

    public function stats(): array
    {
        $warrantyExpiring = Asset::query()
            ->whereNotNull('warranty_expires_at')
            ->whereBetween('warranty_expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        $warrantyExpired = Asset::query()
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '<', now()->toDateString())
            ->count();

        return [
            'total' => Asset::query()->count(),
            'in_use' => Asset::query()->where('status', Asset::STATUS_IN_USE)->count(),
            'in_stock' => Asset::query()->where('status', Asset::STATUS_IN_STOCK)->count(),
            'maintenance' => Asset::query()->where('status', Asset::STATUS_MAINTENANCE)->count(),
            'unassigned' => Asset::query()->whereNull('contact_id')->where('status', '!=', Asset::STATUS_RETIRED)->count(),
            'warranty_expiring' => $warrantyExpiring,
            'warranty_expired' => $warrantyExpired,
        ];
    }

    public function exportRows(array $filters, callable $writer): void
    {
        Asset::query()
            ->with(['type:id,name,slug', 'contact:id,name,email', 'organization:id,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['asset_type_id'] ?? null, fn ($query, $typeId) => $query->where('asset_type_id', $typeId))
            ->when($filters['organization_id'] ?? null, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(filter_var($filters['unassigned'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($query) => $query->whereNull('contact_id'))
            ->when(filter_var($filters['warranty_expiring'] ?? false, FILTER_VALIDATE_BOOLEAN), function ($query) {
                $query->whereNotNull('warranty_expires_at')
                    ->whereBetween('warranty_expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            })
            ->orderBy('asset_tag')
            ->chunk(200, function ($assets) use ($writer) {
                foreach ($assets as $asset) {
                    $writer($asset);
                }
            });
    }

    public function forContact(int $contactId): Collection
    {
        return Asset::query()
            ->with(['type:id,name,slug'])
            ->where('contact_id', $contactId)
            ->orderBy('name')
            ->get();
    }

    public function options(?string $search = null, int $limit = 25): Collection
    {
        return Asset::query()
            ->with('type:id,name,slug')
            ->when($search, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%");
                });
            })
            ->whereNotIn('status', [Asset::STATUS_RETIRED])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'asset_tag', 'name', 'asset_type_id', 'status']);
    }

    public function create(array $data): Asset
    {
        $data['asset_tag'] = $data['asset_tag'] ?? $this->nextTag();

        return Asset::query()->create($data);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $this->find($asset->id);
    }

    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    public function attachTicket(Asset $asset, int $ticketId): void
    {
        $asset->tickets()->syncWithoutDetaching([$ticketId]);
    }

    public function detachTicket(Asset $asset, int $ticketId): void
    {
        $asset->tickets()->detach($ticketId);
    }

    private function nextTag(): string
    {
        $latest = Asset::query()->orderByDesc('id')->value('asset_tag');
        $number = 1;

        if ($latest && preg_match('/AST-(\d+)/', $latest, $matches)) {
            $number = (int) $matches[1] + 1;
        }

        return 'AST-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
