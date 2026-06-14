<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformTestimonial;
use App\Domains\Platform\Repositories\PlatformTestimonialRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;

class PlatformTestimonialService
{
    public function __construct(
        private PlatformTestimonialRepository $testimonials,
        private CentralSettingRepository $settings,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function marketingEnabled(): bool
    {
        return (bool) ($this->settings->current()->testimonials_enabled ?? true);
    }

    public function forMarketing(): array
    {
        if (! $this->marketingEnabled()) {
            return [];
        }

        return $this->testimonials->enabledForMarketing()
            ->map(fn (PlatformTestimonial $testimonial) => $this->presentPublic($testimonial))
            ->all();
    }

    public function listForAdmin(): array
    {
        return $this->testimonials->allForAdmin()
            ->map(fn (PlatformTestimonial $testimonial) => $this->presentAdmin($testimonial))
            ->all();
    }

    public function findForAdmin(int $id): array
    {
        return $this->presentAdmin($this->testimonials->find($id));
    }

    public function create(array $data): array
    {
        $payload = $this->buildPayload($data);
        $payload['sort_order'] = $payload['sort_order'] ?? $this->testimonials->nextSortOrder();

        $testimonial = $this->testimonials->create($payload);

        $this->audit->record('platform.testimonial.created', $testimonial);

        CentralMarketingPresenter::forgetCache();

        return $this->presentAdmin($testimonial);
    }

    public function update(int $id, array $data): array
    {
        $testimonial = $this->testimonials->find($id);
        $before = $this->presentAdmin($testimonial);
        $testimonial = $this->testimonials->update($testimonial, $this->buildPayload($data, $testimonial));

        $this->audit->recordChanges('platform.testimonial.updated', $testimonial, $before, $this->presentAdmin($testimonial));

        CentralMarketingPresenter::forgetCache();

        return $this->presentAdmin($testimonial);
    }

    public function delete(int $id): void
    {
        $testimonial = $this->testimonials->find($id);

        $this->testimonials->delete($testimonial);

        $this->audit->record('platform.testimonial.deleted', null, [
            'id' => $testimonial->id,
            'name' => $testimonial->name,
        ]);

        CentralMarketingPresenter::forgetCache();
    }

    public function setMarketingEnabled(bool $enabled): bool
    {
        $setting = $this->settings->current();

        $this->settings->update($setting, [
            'testimonials_enabled' => $enabled,
        ]);

        $this->audit->record('platform.testimonials.visibility_updated', $setting, [
            'testimonials_enabled' => $enabled,
        ]);

        CentralMarketingPresenter::forgetCache();

        return $enabled;
    }

    public function validationRules(?PlatformTestimonial $existing = null): array
    {
        return [
            'quote' => ['required', 'string', 'max:2000'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'company_type' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    private function buildPayload(array $data, ?PlatformTestimonial $existing = null): array
    {
        return [
            'quote' => $this->sanitizeText($data['quote'], 2000),
            'name' => $this->sanitizeText($data['name'], 120),
            'role' => $this->sanitizeText($data['role'], 120),
            'company_type' => $this->sanitizeText($data['company_type'], 120),
            'sort_order' => array_key_exists('sort_order', $data)
                ? max(0, (int) $data['sort_order'])
                : ($existing?->sort_order ?? 0),
            'is_enabled' => array_key_exists('is_enabled', $data)
                ? (bool) $data['is_enabled']
                : ($existing?->is_enabled ?? true),
        ];
    }

    private function sanitizeText(mixed $value, int $maxLength): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, $maxLength);
    }

    private function presentPublic(PlatformTestimonial $testimonial): array
    {
        return [
            'id' => $testimonial->id,
            'quote' => $testimonial->quote,
            'name' => $testimonial->name,
            'role' => $testimonial->role,
            'company_type' => $testimonial->company_type,
        ];
    }

    private function presentAdmin(PlatformTestimonial $testimonial): array
    {
        return [
            ...$this->presentPublic($testimonial),
            'sort_order' => $testimonial->sort_order,
            'is_enabled' => $testimonial->is_enabled,
            'updated_at' => $testimonial->updated_at?->toIso8601String(),
        ];
    }
}
