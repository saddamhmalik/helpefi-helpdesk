<?php

namespace App\Domains\Platform\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Platform\Models\MarketingContentDraft;
use App\Domains\Platform\Repositories\MarketingContentDraftRepository;
use App\Domains\Platform\Support\MarketingContentType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingContentGenerationService
{
    public function __construct(
        private MarketingContentDraftRepository $drafts,
        private MarketingContentPromptService $prompts,
        private MarketingContentDuplicateService $duplicates,
        private AiCompletionClient $ai,
    ) {
    }

    public function generate(array $input): MarketingContentDraft
    {
        $contentType = (string) ($input['content_type'] ?? '');
        $title = trim((string) ($input['title'] ?? ''));
        $brief = trim((string) ($input['brief'] ?? ''));
        $slug = $this->cleanSlug($input['slug'] ?? null);
        $context = array_filter([
            'competitor' => $this->cleanText($input['competitor'] ?? null, 120),
            'industry' => $this->cleanText($input['industry'] ?? null, 120),
        ]);

        if (! in_array($contentType, MarketingContentType::all(), true)) {
            throw ValidationException::withMessages(['content_type' => 'Invalid content type.']);
        }

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        if ($brief === '') {
            throw ValidationException::withMessages(['brief' => 'Brief is required for quality generation.']);
        }

        if ($slug === null && in_array($contentType, MarketingContentType::pageTypes(), true)) {
            $slug = Str::slug($title);
        }

        $generated = $this->ai->available()
            ? $this->generateViaAi($contentType, $title, $brief, $slug, $context)
            : $this->generateLocally($contentType, $title, $brief, $slug, $context);

        $contentPayload = $generated['content'] ?? [];
        $text = $this->duplicates->extractTextFromPayload($contentPayload, $title);
        $duplicate = $this->duplicates->analyze($text);

        if ($duplicate['blocked']) {
            throw ValidationException::withMessages([
                'brief' => 'Generated content is too similar to existing material. Revise the brief and try again.',
            ]);
        }

        $userId = Auth::guard('platform')->id();

        return $this->drafts->create([
            'content_type' => $contentType,
            'slug' => $slug,
            'title' => $title,
            'brief' => $brief,
            'target_page_key' => $this->resolvePageKey($contentType, $slug),
            'status' => MarketingContentDraft::STATUS_DRAFT,
            'generated_content' => $contentPayload,
            'seo' => $generated['seo'] ?? null,
            'schema_markup' => $generated['schema_markup'] ?? null,
            'internal_links' => $generated['internal_links'] ?? null,
            'duplicate_warnings' => $duplicate['warnings'],
            'content_fingerprint' => $duplicate['fingerprint'],
            'ai_source' => $generated['source'] ?? 'local',
            'generated_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function regenerate(MarketingContentDraft $draft): MarketingContentDraft
    {
        if ($draft->status === MarketingContentDraft::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['draft' => 'Published drafts cannot be regenerated.']);
        }

        $generated = $this->ai->available()
            ? $this->generateViaAi(
                $draft->content_type,
                $draft->title,
                (string) $draft->brief,
                $draft->slug,
                []
            )
            : $this->generateLocally(
                $draft->content_type,
                $draft->title,
                (string) $draft->brief,
                $draft->slug,
                []
            );

        $contentPayload = $generated['content'] ?? [];
        $text = $this->duplicates->extractTextFromPayload($contentPayload, $draft->title);
        $duplicate = $this->duplicates->analyze($text, 'draft:'.$draft->id);

        if ($duplicate['blocked']) {
            throw ValidationException::withMessages([
                'draft' => 'Regenerated content is too similar to existing material.',
            ]);
        }

        return $this->drafts->update($draft, [
            'generated_content' => $contentPayload,
            'edited_content' => null,
            'seo' => $generated['seo'] ?? null,
            'schema_markup' => $generated['schema_markup'] ?? null,
            'internal_links' => $generated['internal_links'] ?? null,
            'duplicate_warnings' => $duplicate['warnings'],
            'content_fingerprint' => $duplicate['fingerprint'],
            'ai_source' => $generated['source'] ?? 'local',
            'generated_at' => now(),
            'status' => MarketingContentDraft::STATUS_DRAFT,
            'updated_by' => Auth::guard('platform')->id(),
        ]);
    }

    private function generateViaAi(string $contentType, string $title, string $brief, ?string $slug, array $context): array
    {
        $raw = $this->ai->complete(
            $this->prompts->systemPrompt(),
            $this->prompts->userPrompt($contentType, $title, $brief, $slug, $context)
        );

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $this->generateLocally($contentType, $title, $brief, $slug, $context);
        }

        return [
            'content' => is_array($decoded['content'] ?? null) ? $decoded['content'] : [],
            'seo' => is_array($decoded['seo'] ?? null) ? $this->cleanSeo($decoded['seo']) : null,
            'schema_markup' => is_array($decoded['schema_markup'] ?? null) ? $decoded['schema_markup'] : null,
            'internal_links' => is_array($decoded['internal_links'] ?? null) ? $decoded['internal_links'] : null,
            'source' => (string) config('ai.provider', 'openai'),
        ];
    }

    private function generateLocally(string $contentType, string $title, string $brief, ?string $slug, array $context): array
    {
        $brand = (string) config('marketing_seo.organization.name', 'Helpefi');
        $navLabel = Str::headline($slug ?? $title);

        $content = match ($contentType) {
            MarketingContentType::COMPARISON => [
                'nav_label' => $navLabel,
                'competitor_name' => $context['competitor'] ?? $navLabel,
                'badge' => "{$brand} vs ".($context['competitor'] ?? $navLabel),
                'hero_title' => $title,
                'hero_highlight' => 'A practical alternative for support teams.',
                'hero_subtitle' => $brief,
                'reasons' => [
                    ['title' => 'Unified workspace', 'body' => 'Email, chat, portal, and SLA in one inbox without stacking add-ons.'],
                    ['title' => 'Transparent pricing', 'body' => 'Modular plans with optional ITSM and AI — no surprise per-agent surcharges.'],
                    ['title' => 'Faster rollout', 'body' => 'Import macros and connect channels in minutes with guided onboarding.'],
                ],
                'rows' => [
                    ['feature' => 'Shared inbox', 'us' => true, 'them' => true],
                    ['feature' => 'Knowledge base', 'us' => true, 'them' => true],
                    ['feature' => 'ITSM module', 'us' => 'Add-on', 'them' => 'Separate product'],
                    ['feature' => 'AI Copilot', 'us' => 'Included tier', 'them' => 'Paid add-on'],
                ],
                'faq' => [
                    ['q' => 'Can we migrate from '.$navLabel.'?', 'a' => 'Yes. Import tickets, users, and macros with guided migration tooling.'],
                    ['q' => 'Is there a free trial?', 'a' => 'Start a {days}-day trial with no credit card required.'],
                ],
                'cta_title' => 'Compare on your own terms',
                'cta_body' => 'Run a side-by-side trial with your real queue and macros.',
            ],
            MarketingContentType::VERTICAL => [
                'nav_label' => $navLabel,
                'badge' => 'Helpdesk for '.($context['industry'] ?? 'your industry'),
                'hero_title' => $title,
                'hero_highlight' => 'Support workflows built for your team.',
                'hero_subtitle' => $brief,
                'pains' => [
                    ['title' => 'Fragmented channels', 'body' => 'Email, chat, and portal tickets land in separate tools.'],
                    ['title' => 'Slow handoffs', 'body' => 'Agents lack context when issues cross teams.'],
                    ['title' => 'Reporting gaps', 'body' => 'Leadership cannot see SLA risk until queues backlog.'],
                ],
                'features' => [
                    ['title' => 'Industry-ready routing', 'body' => 'Route by team, skill, or priority with SLA policies.'],
                    ['title' => 'Self-service deflection', 'body' => 'Publish KB articles that reduce repeat tickets.'],
                    ['title' => 'Operational visibility', 'body' => 'Dashboards for volume, CSAT, and first-response time.'],
                ],
                'faq' => [
                    ['q' => 'How fast can we launch?', 'a' => 'Most teams connect email and portal within a day.'],
                ],
                'cta_title' => 'Start supporting '.$navLabel.' customers',
                'cta_body' => 'Try {brand} free for {days} days.',
            ],
            MarketingContentType::BLOG_OUTLINE => [
                'title' => $title,
                'slug' => $slug ?? Str::slug($title),
                'excerpt' => mb_substr($brief, 0, 200),
                'outline' => [
                    ['heading' => 'Introduction', 'bullets' => ['Problem framing', 'Who this guide is for']],
                    ['heading' => 'Evaluation criteria', 'bullets' => ['Workflow fit', 'Security and compliance', 'Total cost']],
                    ['heading' => 'Implementation checklist', 'bullets' => ['Pilot scope', 'Migration plan', 'Success metrics']],
                    ['heading' => 'Conclusion', 'bullets' => ['Next steps', 'Trial CTA']],
                ],
                'suggested_categories' => ['guides'],
                'suggested_tags' => ['helpdesk', 'customer-support'],
                'reading_minutes_estimate' => 8,
            ],
            default => [
                'nav_label' => $navLabel,
                'badge' => Str::headline($contentType).' software',
                'hero_title' => $title,
                'hero_highlight' => 'Built for modern support teams.',
                'hero_subtitle' => $brief,
                'features' => [
                    ['title' => 'Team-ready workflows', 'body' => 'Assign, collaborate, and resolve with full conversation history.'],
                    ['title' => 'Self-service deflection', 'body' => 'Knowledge base and AI suggestions reduce repeat tickets.'],
                    ['title' => 'Operational visibility', 'body' => 'SLA tracking and dashboards for queue health.'],
                ],
                'faq' => [
                    ['q' => 'Does {brand} include a free trial?', 'a' => 'Yes — {days} days, no credit card required.'],
                ],
                'cta_title' => 'See it in your queue',
                'cta_body' => 'Start a free trial and connect your first inbox.',
            ],
        };

        return [
            'content' => $content,
            'seo' => [
                'seo_title' => mb_substr($title.' · {brand}', 0, 60),
                'meta_description' => mb_substr($brief, 0, 160),
                'keywords' => 'helpdesk, customer support, itsm, '.$brand,
            ],
            'schema_markup' => [
                '@context' => 'https://schema.org',
                '@type' => $contentType === MarketingContentType::BLOG_OUTLINE ? 'Article' : 'WebPage',
                'name' => $title,
                'description' => mb_substr($brief, 0, 300),
            ],
            'internal_links' => array_slice(app(MarketingContentCorpusService::class)->internalLinkTargets(), 0, 4),
            'source' => 'local',
        ];
    }

    private function cleanSeo(array $seo): array
    {
        return array_filter([
            'seo_title' => $this->cleanText($seo['seo_title'] ?? null, 60),
            'meta_description' => $this->cleanText($seo['meta_description'] ?? null, 160),
            'keywords' => $this->cleanText($seo['keywords'] ?? null, 500),
        ]);
    }

    private function resolvePageKey(string $contentType, ?string $slug): ?string
    {
        $prefix = MarketingContentType::seoKeyPrefix($contentType);

        if (! $prefix || ! $slug) {
            return null;
        }

        return $prefix.str_replace('-', '_', $slug);
    }

    private function cleanSlug(mixed $value): ?string
    {
        $slug = Str::slug((string) ($value ?? ''));

        return $slug !== '' ? $slug : null;
    }

    private function cleanText(mixed $value, int $max): ?string
    {
        $text = trim(strip_tags((string) ($value ?? '')));

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }
}
