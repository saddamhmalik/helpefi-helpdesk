<?php

namespace App\Domains\Brands\Repositories;

use App\Domains\Brands\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class BrandRepository
{
    public function all(): Collection
    {
        return Brand::query()->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function active(): Collection
    {
        return Brand::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function find(int $id): Brand
    {
        return Brand::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): Brand
    {
        return Brand::query()->where('slug', $slug)->firstOrFail();
    }

    public function default(): Brand
    {
        return Brand::query()->where('is_default', true)->first()
            ?? Brand::query()->orderBy('id')->firstOrFail();
    }

    public function create(array $data): Brand
    {
        $data['slug'] = $this->uniqueSlug($data['name']);

        return Brand::query()->create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        if (isset($data['name']) && $data['name'] !== $brand->name && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $brand->id);
        }

        $brand->update($data);

        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            Brand::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
