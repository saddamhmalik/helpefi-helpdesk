<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingSeoMetadata;
use Illuminate\Database\Eloquent\Collection;

class MarketingSeoMetadataRepository
{
    public function allForAdmin(): Collection
    {
        return MarketingSeoMetadata::query()
            ->orderBy('page_key')
            ->get();
    }

    public function findByPageKey(string $pageKey): ?MarketingSeoMetadata
    {
        return MarketingSeoMetadata::query()->where('page_key', $pageKey)->first();
    }

    public function upsertByPageKey(string $pageKey, array $data): MarketingSeoMetadata
    {
        $existing = $this->findByPageKey($pageKey);

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return MarketingSeoMetadata::query()->create(array_merge($data, [
            'page_key' => $pageKey,
        ]));
    }
}

