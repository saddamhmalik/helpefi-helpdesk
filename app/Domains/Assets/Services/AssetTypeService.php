<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\AssetType;
use App\Domains\Assets\Repositories\AssetTypeRepository;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssetTypeService
{
    public function __construct(
        private AssetTypeRepository $types,
        private BillingService $billing,
        private AuditRecorder $audit,
    ) {
    }

    public function list(): Collection
    {
        return $this->types->allWithAssetCounts();
    }

    public function create(string $name): AssetType
    {
        $this->billing->assertFeature('assets');

        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Asset type name is required.');
        }

        $slug = $this->uniqueSlug(Str::slug($name));

        $type = $this->types->create($name, $slug);

        $this->audit->record('asset_type.created', $type, [
            'name' => $type->name,
        ]);

        return $type;
    }

    public function update(int $id, string $name): AssetType
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Asset type name is required.');
        }

        $type = $this->types->find($id);
        $before = ['name' => $type->name];
        $type = $this->types->update($type, [
            'name' => $name,
            'slug' => $this->uniqueSlug(Str::slug($name), $type->id),
        ]);

        $this->audit->recordChanges('asset_type.updated', $type, $before, ['name' => $type->name]);

        return $type;
    }

    public function delete(int $id): void
    {
        $type = $this->types->find($id);

        if ($this->types->assetCount($type) > 0) {
            throw new InvalidArgumentException('Remove or reassign assets before deleting this type.');
        }

        $this->types->delete($type);

        $this->audit->record('asset_type.deleted', $type, [
            'name' => $type->name,
        ]);
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug !== '' ? $slug : 'type';
        $candidate = $base;
        $suffix = 1;

        while ($this->types->slugExists($candidate, $ignoreId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
