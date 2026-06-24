<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\Asset;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Assets\Models\AssetAssignmentLog;
use App\Domains\Assets\Repositories\AssetAssignmentLogRepository;
use App\Domains\Assets\Repositories\AssetRepository;
use App\Domains\Assets\Repositories\AssetTypeRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AssetService
{
    public function __construct(
        private AssetRepository $assets,
        private AssetTypeRepository $types,
        private AssetAssignmentLogRepository $assignmentLogs,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
    ) {
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->assets->paginate($filters, $perPage);
    }

    public function show(int $id): Asset
    {
        return $this->assets->find($id);
    }

    public function forContact(int $contactId): Collection
    {
        return $this->assets->forContact($contactId);
    }

    public function options(?string $search = null): Collection
    {
        return $this->assets->options($search);
    }

    public function stats(): array
    {
        return $this->assets->stats();
    }

    public function meta(): array
    {
        return [
            'types' => $this->types->all(),
            'statuses' => [
                ['value' => Asset::STATUS_IN_USE, 'label' => 'In use'],
                ['value' => Asset::STATUS_IN_STOCK, 'label' => 'In stock'],
                ['value' => Asset::STATUS_MAINTENANCE, 'label' => 'Maintenance'],
                ['value' => Asset::STATUS_RETIRED, 'label' => 'Retired'],
            ],
        ];
    }

    public function create(array $data): Asset
    {
        $this->entitlements->assertFeature('assets');
        $this->assertValidAsset($data);

        $asset = $this->assets->create($this->normalizedAsset($data));

        if ($asset->contact_id || $asset->organization_id) {
            $this->assignmentLogs->record($asset, AssetAssignmentLog::ACTION_ASSIGNED, Auth::id());
        }

        $this->audit->record('asset.created', $asset, [
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
        ]);

        return $asset;
    }

    public function update(int $id, array $data): Asset
    {
        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new InvalidArgumentException('Asset cannot be its own parent.');
        }

        $this->assertValidAsset($data);

        $asset = $this->assets->find($id);
        $before = $asset->only(array_keys($this->normalizedAsset($data)));
        $previousContactId = $asset->contact_id;
        $previousOrganizationId = $asset->organization_id;
        $asset = $this->assets->update($asset, $this->normalizedAsset($data));

        $this->recordAssignmentChanges($asset, $previousContactId, $previousOrganizationId);

        $this->audit->recordChanges('asset.updated', $asset, $before, $asset->only(array_keys($before)), [
            'asset_tag' => $asset->asset_tag,
        ]);

        return $asset;
    }

    public function delete(int $id): void
    {
        $asset = $this->assets->find($id);
        $this->assets->delete($asset);

        $this->audit->record('asset.deleted', $asset, [
            'asset_tag' => $asset->asset_tag,
        ]);
    }

    public function attachToTicket(int $assetId, int $ticketId): Asset
    {
        $asset = $this->assets->find($assetId);
        $this->assets->attachTicket($asset, $ticketId);

        return $this->assets->find($assetId);
    }

    public function detachFromTicket(int $assetId, int $ticketId): Asset
    {
        $asset = $this->assets->find($assetId);
        $this->assets->detachTicket($asset, $ticketId);

        return $this->assets->find($assetId);
    }

    private function normalizedAsset(array $data): array
    {
        foreach ([
            'contact_id',
            'organization_id',
            'parent_id',
            'serial_number',
            'location',
            'notes',
            'ip_address',
            'mac_address',
            'hostname',
            'manufacturer',
            'model',
            'vendor',
            'purchase_cost',
            'discovery_source',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function assertValidAsset(array $data): void
    {
        $validStatuses = [
            Asset::STATUS_IN_USE,
            Asset::STATUS_IN_STOCK,
            Asset::STATUS_MAINTENANCE,
            Asset::STATUS_RETIRED,
        ];

        if (isset($data['status']) && ! in_array($data['status'], $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid asset status.');
        }
    }

    private function recordAssignmentChanges(Asset $asset, ?int $previousContactId, ?int $previousOrganizationId): void
    {
        $userId = Auth::id();

        if ($previousContactId !== $asset->contact_id) {
            $action = $asset->contact_id
                ? AssetAssignmentLog::ACTION_ASSIGNED
                : AssetAssignmentLog::ACTION_UNASSIGNED;
            $this->assignmentLogs->record($asset, $action, $userId);
        } elseif ($previousOrganizationId !== $asset->organization_id) {
            $this->assignmentLogs->record($asset, AssetAssignmentLog::ACTION_ORGANIZATION_CHANGED, $userId);
        }
    }
}
