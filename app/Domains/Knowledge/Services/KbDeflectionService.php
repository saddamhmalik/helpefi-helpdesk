<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Brands\Support\BrandContext;
use App\Domains\Knowledge\Models\KbDeflectionEvent;
use App\Domains\Knowledge\Repositories\KbDeflectionRepository;
use App\Domains\Knowledge\Repositories\KnowledgeSearchRepository;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KbDeflectionService
{
    public function __construct(
        private KbDeflectionRepository $events,
        private KnowledgeSearchRepository $search,
        private HelpdeskSettingRepository $settings,
        private BrandContext $brandContext,
    ) {
    }

    public function isEnabled(): bool
    {
        $brand = $this->brandContext->hasBrand() ? $this->brandContext->brand() : null;

        if ($brand && $brand->kb_deflection_enabled !== null) {
            return (bool) $brand->kb_deflection_enabled;
        }

        return (bool) $this->settings->current()->kb_deflection_enabled;
    }

    public function suggest(string $subject, ?string $description, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $query = $this->buildQuery($subject, $description);
        $brandId = $this->brandContext->hasBrand() ? $this->brandContext->id() : null;

        if (! $this->isEnabled() || mb_strlen($query) < 3) {
            return [
                'session_id' => $sessionId,
                'enabled' => $this->isEnabled(),
                'articles' => [],
            ];
        }

        $articles = $this->search->searchPortalPublished($query, 5, $brandId);
        $mapped = $this->mapArticles($articles);

        if ($mapped !== []) {
            $this->events->record([
                'session_id' => $sessionId,
                'event_type' => KbDeflectionEvent::EVENT_SUGGESTIONS_SHOWN,
                'query' => $query,
            ]);
        }

        return [
            'session_id' => $sessionId,
            'enabled' => true,
            'articles' => $mapped,
        ];
    }

    public function recordArticleClick(string $sessionId, int $articleId, ?string $query = null): void
    {
        $this->assertSession($sessionId);

        $this->events->record([
            'session_id' => $sessionId,
            'event_type' => KbDeflectionEvent::EVENT_ARTICLE_CLICKED,
            'article_id' => $articleId,
            'query' => $query,
        ]);
    }

    public function recordDeflected(string $sessionId, ?int $articleId = null, ?string $query = null): void
    {
        $this->assertSession($sessionId);

        $this->events->record([
            'session_id' => $sessionId,
            'event_type' => KbDeflectionEvent::EVENT_DEFLECTED,
            'article_id' => $articleId,
            'query' => $query,
        ]);
    }

    public function recordContinued(string $sessionId, ?string $query = null): void
    {
        $this->assertSession($sessionId);

        $this->events->record([
            'session_id' => $sessionId,
            'event_type' => KbDeflectionEvent::EVENT_CONTINUED,
            'query' => $query,
        ]);
    }

    public function recordTicketCreated(string $sessionId, int $ticketId, ?string $query = null): void
    {
        if ($sessionId === '') {
            return;
        }

        $this->events->record([
            'session_id' => $sessionId,
            'event_type' => KbDeflectionEvent::EVENT_TICKET_CREATED,
            'ticket_id' => $ticketId,
            'query' => $query,
        ]);
    }

    public function summary(array $filters = []): array
    {
        return $this->events->summary($filters);
    }

    public function dashboardSummary(): array
    {
        return $this->events->dashboardSummary();
    }

    private function buildQuery(string $subject, ?string $description): string
    {
        $parts = array_filter([
            trim($subject),
            trim(strip_tags((string) $description)),
        ]);

        return trim(implode(' ', $parts));
    }

    private function mapArticles($articles): array
    {
        return $articles->map(fn ($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'url' => '/portal/'.$this->brandContext->brand()->slug.'/articles/'.$article->slug,
        ])->values()->all();
    }

    private function assertSession(string $sessionId): void
    {
        if (! Str::isUuid($sessionId)) {
            throw ValidationException::withMessages([
                'session_id' => 'Invalid session.',
            ]);
        }
    }
}
