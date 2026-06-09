<?php

namespace App\Domains\Assets\Repositories;

use App\Domains\Assets\Models\AssetType;
use Illuminate\Database\Eloquent\Collection;

class AssetTypeRepository
{
    public function all(): Collection
    {
        return AssetType::query()->orderBy('name')->get();
    }

    public function allWithAssetCounts(): Collection
    {
        return AssetType::query()
            ->withCount('assets')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): AssetType
    {
        return AssetType::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): ?AssetType
    {
        return AssetType::query()->where('slug', $slug)->first();
    }

    public function findByName(string $name): ?AssetType
    {
        return AssetType::query()->where('name', $name)->first();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return AssetType::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }

    public function assetCount(AssetType $type): int
    {
        return $type->assets()->count();
    }

    public function create(string $name, string $slug): AssetType
    {
        return AssetType::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    public function update(AssetType $type, array $data): AssetType
    {
        $type->update($data);

        return $type->fresh();
    }

    public function delete(AssetType $type): void
    {
        $type->delete();
    }
}
