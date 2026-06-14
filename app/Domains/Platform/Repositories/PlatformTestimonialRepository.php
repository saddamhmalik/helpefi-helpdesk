<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformTestimonial;
use Illuminate\Database\Eloquent\Collection;

class PlatformTestimonialRepository
{
    public function allForAdmin(): Collection
    {
        return PlatformTestimonial::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function enabledForMarketing(): Collection
    {
        return PlatformTestimonial::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): PlatformTestimonial
    {
        return PlatformTestimonial::query()->findOrFail($id);
    }

    public function create(array $data): PlatformTestimonial
    {
        return PlatformTestimonial::query()->create($data);
    }

    public function update(PlatformTestimonial $testimonial, array $data): PlatformTestimonial
    {
        $testimonial->update($data);

        return $testimonial->fresh();
    }

    public function delete(PlatformTestimonial $testimonial): void
    {
        $testimonial->delete();
    }

    public function nextSortOrder(): int
    {
        return ((int) PlatformTestimonial::query()->max('sort_order')) + 1;
    }
}
