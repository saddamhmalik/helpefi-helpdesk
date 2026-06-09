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

    public function findBySlug(string $slug): ?AssetType
    {
        return AssetType::query()->where('slug', $slug)->first();
    }
}
