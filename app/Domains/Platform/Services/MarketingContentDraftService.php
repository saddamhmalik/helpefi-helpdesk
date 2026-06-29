<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingContentDraft;
use App\Domains\Platform\Repositories\MarketingContentDraftRepository;
use App\Domains\Platform\Support\MarketingContentType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MarketingContentDraftService
{
    public function __construct(
        private MarketingContentDraftRepository $drafts,
        private MarketingContentDuplicateService $duplicates,
    ) {
    }

    public function listForAdmin(): array
    {
        return $this->drafts->allForAdmin()
            ->map(fn (MarketingContentDraft $draft) => $this->presentAdmin($draft))
            ->all();
    }

    public function findForAdmin(int $id): array
    {
        return $this->presentAdmin($this->drafts->find($id));
    }

    public function options(): array
    {
        $corpus = app(MarketingContentCorpusService::class);

        return [
            'content_types' => collect(MarketingContentType::all())
                ->map(fn (string $type) => [
                    'value' => $type,
                    'label' => MarketingContentType::label($type),
                ])
                ->values()
                ->all(),
            'existing_slugs' => collect(MarketingContentType::pageTypes())
                ->mapWithKeys(fn (string $type) => [$type => $corpus->existingSlugs($type)])
                ->all(),
            'internal_link_targets' => $corpus->internalLinkTargets(),
        ];
    }

    public function update(int $id, array $data): array
    {
        $draft = $this->drafts->find($id);

        if ($draft->status === MarketingContentDraft::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['draft' => 'Published drafts are read-only. Create a new draft instead.']);
        }

        $editedContent = $this->decodeJsonField($data['edited_content'] ?? null);
        $seo = $this->decodeJsonField($data['seo'] ?? null);
        $schema = $this->decodeJsonField($data['schema_markup'] ?? null);
        $links = $this->decodeJsonField($data['internal_links'] ?? null);

        $text = $this->duplicates->extractTextFromPayload(
            $editedContent ?? $draft->effectiveContent(),
            (string) ($data['title'] ?? $draft->title)
        );

        $duplicate = $this->duplicates->analyze($text, 'draft:'.$draft->id);

        $payload = [
            'title' => trim((string) ($data['title'] ?? $draft->title)),
            'slug' => $data['slug'] ?? $draft->slug,
            'brief' => $data['brief'] ?? $draft->brief,
            'edited_content' => $editedContent,
            'seo' => $seo ?? $draft->seo,
            'schema_markup' => $schema ?? $draft->schema_markup,
            'internal_links' => $links ?? $draft->internal_links,
            'duplicate_warnings' => $duplicate['warnings'],
            'content_fingerprint' => $duplicate['fingerprint'],
            'status' => MarketingContentDraft::STATUS_READY,
            'updated_by' => Auth::guard('platform')->id(),
        ];

        if ($duplicate['blocked']) {
            throw ValidationException::withMessages([
                'edited_content' => 'Edited content is too similar to existing material. Revise for uniqueness.',
            ]);
        }

        return $this->presentAdmin($this->drafts->update($draft, $payload));
    }

    public function archive(int $id): void
    {
        $draft = $this->drafts->find($id);

        $this->drafts->update($draft, [
            'status' => MarketingContentDraft::STATUS_ARCHIVED,
            'updated_by' => Auth::guard('platform')->id(),
        ]);
    }

    public function delete(int $id): void
    {
        $draft = $this->drafts->find($id);

        if ($draft->status === MarketingContentDraft::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['draft' => 'Published drafts cannot be deleted.']);
        }

        $this->drafts->delete($draft);
    }

    public function validationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:120'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'edited_content' => ['nullable', 'string'],
            'seo' => ['nullable', 'string'],
            'schema_markup' => ['nullable', 'string'],
            'internal_links' => ['nullable', 'string'],
        ];
    }

    public function generationRules(): array
    {
        return [
            'content_type' => ['required', 'string', 'in:'.implode(',', MarketingContentType::all())],
            'title' => ['required', 'string', 'max:200'],
            'brief' => ['required', 'string', 'max:5000'],
            'slug' => ['nullable', 'string', 'max:120'],
            'competitor' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
        ];
    }

    private function decodeJsonField(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function presentAdmin(MarketingContentDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'content_type' => $draft->content_type,
            'content_type_label' => MarketingContentType::label($draft->content_type),
            'slug' => $draft->slug,
            'title' => $draft->title,
            'brief' => $draft->brief,
            'target_page_key' => $draft->target_page_key,
            'status' => $draft->status,
            'generated_content' => $draft->generated_content,
            'edited_content' => $draft->edited_content,
            'effective_content' => $draft->effectiveContent(),
            'seo' => $draft->seo,
            'schema_markup' => $draft->schema_markup,
            'internal_links' => $draft->internal_links,
            'duplicate_warnings' => $draft->duplicate_warnings ?? [],
            'ai_source' => $draft->ai_source,
            'generated_at' => $draft->generated_at?->toIso8601String(),
            'published_at' => $draft->published_at?->toIso8601String(),
            'published_reference' => $draft->published_reference,
            'creator' => $draft->creator ? ['id' => $draft->creator->id, 'name' => $draft->creator->name] : null,
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }
}
