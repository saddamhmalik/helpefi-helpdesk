<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Ai\Models\AiDeflectionEvent;
use App\Domains\Ai\Repositories\AiDeflectionRepository;
use App\Domains\Ai\Repositories\AiSettingRepository;
use App\Domains\Ai\Repositories\KnowledgeAiRepository;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Knowledge\Services\PortalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiDeflectionService
{
    public function __construct(
        private AiSettingRepository $settings,
        private AiDeflectionRepository $events,
        private KnowledgeAiRepository $knowledge,
        private AiCompletionClient $client,
        private BillingService $billing,
        private PortalService $portal,
    ) {
    }

    public function settingsSnapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'deflection_enabled' => $setting->deflection_enabled,
            'deflection_portal_enabled' => $setting->deflection_portal_enabled,
            'deflection_widget_enabled' => $setting->deflection_widget_enabled,
        ];
    }

    public function updateSettings(array $data): array
    {
        $this->settings->update($this->settings->current(), [
            'deflection_enabled' => $data['deflection_enabled'] ?? false,
            'deflection_portal_enabled' => $data['deflection_portal_enabled'] ?? true,
            'deflection_widget_enabled' => $data['deflection_widget_enabled'] ?? true,
        ]);

        return $this->settingsSnapshot();
    }

    public function isEnabledForChannel(string $channel): bool
    {
        $setting = $this->settings->current();

        if (! $setting->deflection_enabled) {
            return false;
        }

        return match ($channel) {
            AiDeflectionEvent::CHANNEL_PORTAL => $setting->deflection_portal_enabled,
            AiDeflectionEvent::CHANNEL_WIDGET => $setting->deflection_widget_enabled,
            default => false,
        };
    }

    public function ask(string $query, string $channel, ?string $sessionId = null): array
    {
        $this->assertChannelEnabled($channel);

        $query = trim($query);

        if ($query === '') {
            throw ValidationException::withMessages([
                'query' => 'Please enter a question.',
            ]);
        }

        $sessionId = $sessionId ?: (string) Str::uuid();
        $articles = $this->knowledge->searchPublished($query, 5);
        $mapped = $this->mapArticles($articles);
        $useOpenAi = $this->client->available() && $this->billing->canUseFeature('ai');

        if ($useOpenAi && $mapped !== []) {
            $answer = $this->client->complete(
                'You are a helpful customer support assistant. Answer using ONLY the provided knowledge base articles. If the articles do not answer the question, say you could not find a definitive answer and suggest contacting support. Be concise, friendly, and plain text only.',
                "Customer question: {$query}\n\nKnowledge base articles:\n".$this->buildArticleContext($articles),
            );
            $source = 'openai';
        } else {
            $answer = $this->localAnswer($articles, $query);
            $source = 'local';
        }

        $this->events->record([
            'session_id' => $sessionId,
            'channel' => $channel,
            'event_type' => AiDeflectionEvent::EVENT_QUERY,
            'query' => $query,
            'source' => $source,
        ]);

        $this->events->record([
            'session_id' => $sessionId,
            'channel' => $channel,
            'event_type' => AiDeflectionEvent::EVENT_ANSWER,
            'query' => $query,
            'article_id' => $articles->first()?->id,
            'source' => $source,
        ]);

        return [
            'session_id' => $sessionId,
            'answer' => $answer,
            'articles' => $mapped,
            'source' => $source,
            'can_escalate' => true,
        ];
    }

    public function feedback(string $sessionId, string $channel, bool $helpful, ?int $articleId = null): void
    {
        $this->assertChannelEnabled($channel);

        $this->events->record([
            'session_id' => $sessionId,
            'channel' => $channel,
            'event_type' => $helpful
                ? AiDeflectionEvent::EVENT_HELPFUL
                : AiDeflectionEvent::EVENT_NOT_HELPFUL,
            'article_id' => $articleId,
        ]);
    }

    public function escalate(array $data, string $channel): array
    {
        $this->assertChannelEnabled($channel);

        $validated = validator($data, [
            'session_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ])->validate();

        $ticket = $this->portal->submitTicket([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
        ]);

        $this->events->record([
            'session_id' => $validated['session_id'],
            'channel' => $channel,
            'event_type' => AiDeflectionEvent::EVENT_TICKET_CREATED,
            'query' => $validated['description'],
            'ticket_id' => $ticket->id,
        ]);

        return [
            'ticket_number' => $ticket->number,
            'message' => 'Your request has been submitted. We will follow up by email.',
        ];
    }

    public function summary(array $filters = []): array
    {
        return $this->events->summary($filters);
    }

    public function dashboardSummary(): array
    {
        return $this->events->dashboardSummary();
    }

    private function assertChannelEnabled(string $channel): void
    {
        if (! $this->isEnabledForChannel($channel)) {
            throw new AuthorizationException('AI deflection is not available.');
        }
    }

    private function mapArticles($articles): array
    {
        return $articles->map(fn ($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'url' => '/portal/articles/'.$article->slug,
        ])->values()->all();
    }

    private function buildArticleContext($articles): string
    {
        return $articles->take(3)->map(function ($article) {
            $body = strip_tags((string) ($article->body ?? ''));
            $body = mb_strlen($body) > 800 ? mb_substr($body, 0, 800).'…' : $body;

            return "Title: {$article->title}\nExcerpt: {$article->excerpt}\nBody: {$body}";
        })->implode("\n\n---\n\n");
    }

    private function localAnswer($articles, string $query): string
    {
        if ($articles->isEmpty()) {
            return 'I could not find a matching article for "'.$query.'". You can submit a support request and our team will help you.';
        }

        $top = $articles->first();
        $snippet = trim((string) ($top->excerpt ?: strip_tags((string) ($top->body ?? ''))));

        if (mb_strlen($snippet) > 320) {
            $snippet = mb_substr($snippet, 0, 320).'…';
        }

        return "Here's what I found about \"{$top->title}\": {$snippet}\n\nWas this helpful, or would you like to contact support?";
    }
}
