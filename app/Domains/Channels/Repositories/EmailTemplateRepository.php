<?php

namespace App\Domains\Channels\Repositories;

use App\Domains\Channels\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Collection;

class EmailTemplateRepository
{
    public function all(): Collection
    {
        return EmailTemplate::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): EmailTemplate
    {
        return EmailTemplate::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): ?EmailTemplate
    {
        return EmailTemplate::query()->where('slug', $slug)->first();
    }

    public function activeBySlug(string $slug): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): EmailTemplate
    {
        return EmailTemplate::query()->create($data);
    }

    public function update(EmailTemplate $template, array $data): EmailTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function firstOrCreate(string $slug, array $defaults): EmailTemplate
    {
        return EmailTemplate::query()->firstOrCreate(['slug' => $slug], $defaults);
    }
}
