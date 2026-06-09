<?php

namespace App\Domains\Brands\Services;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Repositories\BrandRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BrandService
{
    public function __construct(
        private BrandRepository $brands,
        private AuditRecorder $audit,
    ) {
    }

    public function list(): Collection
    {
        return $this->brands->all();
    }

    public function listForSettings(): array
    {
        return $this->brands->all()->map(fn (Brand $brand) => $this->toArray($brand))->all();
    }

    public function default(): Brand
    {
        return $this->brands->default();
    }

    public function defaultSlug(): string
    {
        return $this->default()->slug;
    }

    public function create(array $data, ?int $userId = null): Brand
    {
        $validated = $this->validate($data);
        $brand = $this->brands->create($validated);

        $this->audit->record('brand.created', $brand, ['name' => $brand->name, 'slug' => $brand->slug], $userId);

        return $brand;
    }

    public function update(int $id, array $data, ?int $userId = null): Brand
    {
        $brand = $this->brands->find($id);
        $validated = $this->validate($data, $brand);
        $brand = $this->brands->update($brand, $validated);

        $this->audit->record('brand.updated', $brand, ['name' => $brand->name, 'slug' => $brand->slug], $userId);

        return $brand;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        $brand = $this->brands->find($id);

        if ($brand->is_default) {
            throw new InvalidArgumentException('The default brand cannot be deleted.');
        }

        if ($brand->tickets()->exists()) {
            throw new InvalidArgumentException('Cannot delete a brand that has tickets.');
        }

        $this->audit->record('brand.deleted', $brand, ['name' => $brand->name], $userId);
        $this->brands->delete($brand);
    }

    public function ticketNumberPrefix(Brand $brand): ?string
    {
        $prefix = trim((string) ($brand->ticket_number_prefix ?? ''));

        return $prefix !== '' ? strtoupper(str_ends_with($prefix, '-') ? $prefix : $prefix.'-') : null;
    }

    private function validate(array $data, ?Brand $brand = null): array
    {
        $validated = [
            'name' => trim($data['name'] ?? ''),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'portal_title' => $this->nullableString($data['portal_title'] ?? null),
            'primary_color' => $this->nullableString($data['primary_color'] ?? null),
            'accent_color' => $this->nullableString($data['accent_color'] ?? null),
            'ticket_number_prefix' => $this->nullableString($data['ticket_number_prefix'] ?? null),
            'ticket_fields' => $this->normalizeFieldDefinitions($data['ticket_fields'] ?? []),
            'default_ticket_priority_id' => $data['default_ticket_priority_id'] ?? null,
            'kb_deflection_enabled' => array_key_exists('kb_deflection_enabled', $data)
                ? (bool) $data['kb_deflection_enabled']
                : null,
        ];

        if ($validated['name'] === '') {
            throw new InvalidArgumentException('Brand name is required.');
        }

        if (! empty($data['slug'])) {
            $slug = Str::slug($data['slug']);

            if ($slug === '') {
                throw new InvalidArgumentException('Brand slug is invalid.');
            }

            $exists = Brand::query()
                ->where('slug', $slug)
                ->when($brand, fn ($q) => $q->where('id', '!=', $brand->id))
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('Brand slug is already in use.');
            }

            $validated['slug'] = $slug;
        }

        return $validated;
    }

    private function normalizeFieldDefinitions(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field) => ! empty($field['name']) && ! empty($field['label']))
            ->map(function ($field) {
                return [
                    'name' => Str::slug($field['name'], '_'),
                    'label' => $field['label'],
                    'type' => in_array($field['type'] ?? 'text', ['text', 'textarea', 'select', 'number', 'email'], true)
                        ? $field['type']
                        : 'text',
                    'required' => (bool) ($field['required'] ?? false),
                    'options' => array_values(array_filter($field['options'] ?? [], fn ($option) => $option !== null && $option !== '')),
                ];
            })
            ->unique('name')
            ->values()
            ->all();
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toArray(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'is_default' => $brand->is_default,
            'is_active' => $brand->is_active,
            'portal_title' => $brand->portal_title,
            'primary_color' => $brand->primary_color,
            'accent_color' => $brand->accent_color,
            'ticket_number_prefix' => $brand->ticket_number_prefix,
            'ticket_fields' => $brand->ticket_fields ?? [],
            'default_ticket_priority_id' => $brand->default_ticket_priority_id,
            'kb_deflection_enabled' => $brand->kb_deflection_enabled,
            'portal_url' => url('/portal/'.$brand->slug),
            'collections_count' => $brand->collections()->count(),
            'inboxes_count' => $brand->inboxes()->count(),
        ];
    }
}
