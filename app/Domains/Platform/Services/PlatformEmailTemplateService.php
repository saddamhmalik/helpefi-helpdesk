<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Repositories\PlatformEmailTemplateRepository;
use App\Domains\Platform\Support\PlatformEmailPlaceholders;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformEmailTemplateService
{
    public function __construct(private PlatformEmailTemplateRepository $templates)
    {
    }

    public function list(): array
    {
        return $this->templates->all()
            ->map(fn (PlatformEmailTemplate $template) => $this->present($template))
            ->all();
    }

    public function find(int $id): array
    {
        return $this->present($this->templates->find($id));
    }

    public function create(array $data): array
    {
        $slug = Str::slug($data['slug'] ?? $data['name']);

        if ($slug === '' || $this->templates->slugExists($slug)) {
            throw ValidationException::withMessages([
                'slug' => 'This template slug is already in use.',
            ]);
        }

        if (in_array($slug, $this->reservedSlugs(), true)) {
            throw ValidationException::withMessages([
                'slug' => 'This slug is reserved for system templates.',
            ]);
        }

        $template = $this->templates->create([
            'slug' => $slug,
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_system' => false,
        ]);

        return $this->present($template);
    }

    public function update(int $id, array $data): array
    {
        $template = $this->templates->find($id);

        $payload = [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if (! $template->is_system && isset($data['slug'])) {
            $slug = Str::slug($data['slug']);

            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'Slug is required.']);
            }

            if (in_array($slug, $this->reservedSlugs(), true) && $slug !== $template->slug) {
                throw ValidationException::withMessages(['slug' => 'This slug is reserved for system templates.']);
            }

            if ($this->templates->slugExists($slug, $template->id)) {
                throw ValidationException::withMessages(['slug' => 'This template slug is already in use.']);
            }

            $payload['slug'] = $slug;
        }

        return $this->present($this->templates->update($template, $payload));
    }

    public function delete(int $id): void
    {
        $template = $this->templates->find($id);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'slug' => 'System email templates cannot be deleted.',
            ]);
        }

        $this->templates->delete($template);
    }

    public function render(string $slug, array $variables): ?array
    {
        $template = $this->templates->activeBySlug($slug);

        if (! $template) {
            return null;
        }

        return [
            'subject' => PlatformEmailPlaceholders::render($template->subject, $variables),
            'body_html' => PlatformEmailPlaceholders::render($template->body_html, $variables),
        ];
    }

    private function present(PlatformEmailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'subject' => $template->subject,
            'body_html' => $template->body_html,
            'is_active' => $template->is_active,
            'is_system' => $template->is_system,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    private function reservedSlugs(): array
    {
        return [
            PlatformEmailTemplate::SLUG_REGISTRATION,
            PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME,
        ];
    }
}
