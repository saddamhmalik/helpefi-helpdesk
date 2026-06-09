<?php

namespace App\Domains\Brands\Support;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Repositories\BrandRepository;

class BrandContext
{
    private ?Brand $brand = null;

    public function set(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function hasBrand(): bool
    {
        return $this->brand !== null;
    }

    public function brand(): Brand
    {
        return $this->brand ?? app(BrandRepository::class)->default();
    }

    public function id(): int
    {
        return $this->brand()->id;
    }

    public function toPortalArray(): array
    {
        $brand = $this->brand();

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'portal_title' => $brand->portal_title ?: $brand->name,
            'primary_color' => $brand->primary_color,
            'accent_color' => $brand->accent_color,
        ];
    }
}
