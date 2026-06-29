<?php

namespace App\Domains\Platform\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Platform\Repositories\MarketingSeoMetadataRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingSeoMetadataService
{
    public function __construct(
        private MarketingSeoMetadataRepository $meta,
        private AiCompletionClient $ai,
    ) {
    }

    public function listForAdmin(): array
    {
        return $this->meta->allForAdmin()
            ->map(fn ($row) => $this->presentAdmin($row->toArray()))
            ->values()
            ->all();
    }

    public function findForAdmin(string $pageKey): array
    {
        $row = $this->meta->findByPageKey($pageKey);

        return $this->presentAdmin(($row?->toArray()) ?? ['page_key' => $pageKey]);
    }

    public function updateManual(string $pageKey, array $data): array
    {
        $payload = [
            'manual_seo_title' => $this->cleanText($data['manual_seo_title'] ?? null, 200),
            'manual_meta_description' => $this->cleanText($data['manual_meta_description'] ?? null, 320),
            'manual_keywords' => $this->cleanText($data['manual_keywords'] ?? null, 500),
            'manual_og_description' => $this->cleanText($data['manual_og_description'] ?? null, 320),
            'manual_twitter_description' => $this->cleanText($data['manual_twitter_description'] ?? null, 320),
            'updated_by' => Auth::guard('platform')->id(),
        ];

        $row = $this->meta->upsertByPageKey($pageKey, $payload);

        return $this->presentAdmin($row->toArray());
    }

    public function generateFromContent(string $pageKey, string $content): array
    {
        $pageKey = trim($pageKey);
        $content = trim($content);

        if ($pageKey === '') {
            throw ValidationException::withMessages(['page_key' => 'Page key is required.']);
        }

        if ($content === '') {
            throw ValidationException::withMessages(['content' => 'Content is required.']);
        }

        if (mb_strlen($content) > 20000) {
            $content = mb_substr($content, 0, 20000);
        }

        $generated = $this->ai->available()
            ? $this->generateViaAi($pageKey, $content)
            : $this->generateLocally($pageKey, $content);

        $row = $this->meta->upsertByPageKey($pageKey, [
            'ai_seo_title' => $generated['seo_title'] ?? null,
            'ai_meta_description' => $generated['meta_description'] ?? null,
            'ai_keywords' => $generated['keywords'] ?? null,
            'ai_og_description' => $generated['og_description'] ?? null,
            'ai_twitter_description' => $generated['twitter_description'] ?? null,
            'ai_slug_suggestions' => $generated['slug_suggestions'] ?? null,
            'source_content' => $content,
            'ai_source' => $generated['source'] ?? null,
            'ai_generated_at' => now(),
            'updated_by' => Auth::guard('platform')->id(),
        ]);

        return $this->presentAdmin($row->toArray());
    }

    public function resolveForPageKey(string $pageKey): array
    {
        $row = $this->meta->findByPageKey($pageKey);

        if (! $row) {
            return [];
        }

        $title = $this->firstFilled($row->manual_seo_title, $row->ai_seo_title);
        $description = $this->firstFilled($row->manual_meta_description, $row->ai_meta_description);
        $keywords = $this->firstFilled($row->manual_keywords, $row->ai_keywords);
        $ogDescription = $this->firstFilled($row->manual_og_description, $row->ai_og_description, $description);
        $twitterDescription = $this->firstFilled($row->manual_twitter_description, $row->ai_twitter_description, $description);

        return array_filter([
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'og_description' => $ogDescription,
            'twitter_description' => $twitterDescription,
        ], fn ($value) => is_string($value) && $value !== '');
    }

    public function validationRules(): array
    {
        return [
            'manual_seo_title' => ['nullable', 'string', 'max:200'],
            'manual_meta_description' => ['nullable', 'string', 'max:320'],
            'manual_keywords' => ['nullable', 'string', 'max:500'],
            'manual_og_description' => ['nullable', 'string', 'max:320'],
            'manual_twitter_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    private function generateViaAi(string $pageKey, string $content): array
    {
        $system = 'You are an expert SEO copywriter. Output ONLY valid JSON. No markdown.';
        $user = $this->buildAiPrompt($pageKey, $content);

        $raw = $this->ai->complete($system, $user);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $this->generateLocally($pageKey, $content);
        }

        return [
            'seo_title' => $this->cleanText($decoded['seo_title'] ?? null, 200),
            'meta_description' => $this->cleanText($decoded['meta_description'] ?? null, 320),
            'keywords' => $this->cleanText($decoded['keywords'] ?? null, 500),
            'og_description' => $this->cleanText($decoded['og_description'] ?? null, 320),
            'twitter_description' => $this->cleanText($decoded['twitter_description'] ?? null, 320),
            'slug_suggestions' => $this->cleanSlugSuggestions($decoded['slug_suggestions'] ?? null),
            'source' => (string) config('ai.provider', 'openai'),
        ];
    }

    private function buildAiPrompt(string $pageKey, string $content): string
    {
        return implode("\n", [
            "Page key: {$pageKey}",
            '',
            'Given the page content below, generate SEO metadata.',
            '',
            'Constraints:',
            '- SEO title: <= 60 characters, no quotes',
            '- Meta description: <= 160 characters',
            '- Keywords: comma-separated, <= 10 keywords, no duplicates',
            '- Open Graph description: <= 200 characters, can differ from meta description',
            '- Twitter description: <= 200 characters, can differ from meta description',
            '- Slug suggestions: 5 suggestions, lowercase, hyphen-separated, no stopwords where possible, max 70 chars each',
            '',
            'Return strict JSON with keys:',
            'seo_title, meta_description, keywords, og_description, twitter_description, slug_suggestions',
            '',
            "Page content:\n{$content}",
        ]);
    }

    private function generateLocally(string $pageKey, string $content): array
    {
        $text = $this->cleanText($content, 5000);
        $title = $this->cleanText(Str::headline(str_replace('_', ' ', $pageKey)), 60);
        $description = $this->cleanText($text, 160);
        $slugs = $this->localSlugSuggestions($pageKey, $text);

        return [
            'seo_title' => $title,
            'meta_description' => $description,
            'keywords' => $this->cleanText(implode(', ', array_slice(array_unique(array_filter([
                Str::slug($pageKey, ' '),
                'helpdesk',
                'itsm',
                'customer support',
                'service desk',
            ])), 0, 8)), 500),
            'og_description' => $this->cleanText($description, 200),
            'twitter_description' => $this->cleanText($description, 200),
            'slug_suggestions' => $slugs,
            'source' => 'local',
        ];
    }

    private function localSlugSuggestions(string $pageKey, string $content): array
    {
        $base = Str::slug(str_replace('_', ' ', $pageKey));
        $tokens = collect(preg_split('/\s+/', strtolower(strip_tags($content))) ?: [])
            ->map(fn ($t) => trim(preg_replace('/[^a-z0-9-]/', '', $t) ?? ''))
            ->filter(fn ($t) => $t !== '' && mb_strlen($t) >= 3)
            ->unique()
            ->values()
            ->take(10)
            ->all();

        $candidates = collect([
            $base,
            $base.'-guide',
            $base.'-overview',
            $base.'-best-practices',
            $base.'-for-teams',
            ...array_map(fn ($t) => Str::slug($base.' '.$t), $tokens),
        ])->filter()
            ->map(fn ($s) => mb_substr((string) $s, 0, 70))
            ->unique()
            ->values()
            ->take(7)
            ->all();

        return array_values(array_slice($candidates, 0, 5));
    }

    private function cleanSlugSuggestions(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $slugs = collect($value)
            ->filter(fn ($item) => is_string($item))
            ->map(fn (string $slug) => Str::slug($slug))
            ->filter()
            ->unique()
            ->map(fn (string $slug) => mb_substr($slug, 0, 70))
            ->values()
            ->take(10)
            ->all();

        return $slugs === [] ? null : $slugs;
    }

    private function cleanText(mixed $value, int $maxLength): ?string
    {
        $text = trim(strip_tags((string) ($value ?? '')));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function presentAdmin(array $row): array
    {
        return [
            'page_key' => (string) ($row['page_key'] ?? ''),
            'manual' => [
                'seo_title' => $row['manual_seo_title'] ?? null,
                'meta_description' => $row['manual_meta_description'] ?? null,
                'keywords' => $row['manual_keywords'] ?? null,
                'og_description' => $row['manual_og_description'] ?? null,
                'twitter_description' => $row['manual_twitter_description'] ?? null,
            ],
            'ai' => [
                'seo_title' => $row['ai_seo_title'] ?? null,
                'meta_description' => $row['ai_meta_description'] ?? null,
                'keywords' => $row['ai_keywords'] ?? null,
                'og_description' => $row['ai_og_description'] ?? null,
                'twitter_description' => $row['ai_twitter_description'] ?? null,
                'slug_suggestions' => $row['ai_slug_suggestions'] ?? [],
                'source' => $row['ai_source'] ?? null,
                'generated_at' => $row['ai_generated_at'] ?? null,
            ],
            'source_content' => $row['source_content'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

