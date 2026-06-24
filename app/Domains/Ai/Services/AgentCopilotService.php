<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Ai\Repositories\AiCopilotRepository;
use App\Domains\Ai\Repositories\AiSettingRepository;
use App\Domains\Ai\Repositories\KnowledgeAiRepository;
use App\Domains\Ai\Repositories\TicketAiRepository;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AgentCopilotService
{
    public function __construct(
        private AiSettingRepository $settings,
        private AiCopilotRepository $copilot,
        private TicketAiRepository $tickets,
        private KnowledgeAiRepository $knowledge,
        private AiCompletionClient $client,
        private FeatureEntitlementChecker $entitlements,
    ) {
    }

    public function history(int $ticketId, int $userId): array
    {
        $this->assertEnabled();
        $this->assertAgent($userId);
        $this->tickets->forTicket($ticketId);

        return [
            'messages' => $this->copilot->history($ticketId, $userId)
                ->map(fn ($message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'provider' => config('ai.provider'),
        ];
    }

    public function chat(int $ticketId, int $userId, string $message): array
    {
        $this->assertEnabled();

        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Enter a message.',
            ]);
        }

        $ticket = $this->tickets->forTicket($ticketId);
        $this->assertAgent($userId);
        $history = $this->copilot->history($ticketId, $userId, 18);
        $articles = $this->knowledge->searchPublished(trim($ticket->subject.' '.$message), 5);
        $messages = $this->buildProviderMessages($ticket, $history, $message, $articles);

        if ($this->client->available()) {
            $reply = $this->client->chat($messages);
            $source = (string) config('ai.provider', 'openai');
        } else {
            $reply = $this->localReply($ticket, $message, $articles);
            $source = 'local';
        }

        $this->copilot->store($ticketId, $userId, 'user', $message);
        $stored = $this->copilot->store($ticketId, $userId, 'assistant', $reply);

        return [
            'message' => [
                'id' => $stored->id,
                'role' => 'assistant',
                'content' => $reply,
                'created_at' => $stored->created_at?->toIso8601String(),
            ],
            'articles' => $this->mapArticles($articles),
            'source' => $source,
        ];
    }

    public function clear(int $ticketId, int $userId): void
    {
        $this->assertEnabled();
        $this->assertAgent($userId);
        $this->tickets->forTicket($ticketId);
        $this->copilot->clear($ticketId, $userId);
    }

    public function askWorkspace(int $userId, string $message): array
    {
        $this->assertEnabled();
        $this->assertAgent($userId);

        $message = trim($message);

        $articles = $this->knowledge->searchPublished($message, 5);
        $messages = [
            ['role' => 'system', 'content' => $this->workspaceSystemPrompt($articles)],
            ['role' => 'user', 'content' => $message],
        ];

        if ($this->client->available()) {
            $reply = $this->client->chat($messages);
            $source = (string) config('ai.provider', 'openai');
        } else {
            $reply = $this->localWorkspaceReply($message, $articles);
            $source = 'local';
        }

        return [
            'answer' => $reply,
            'articles' => $this->mapArticles($articles),
            'source' => $source,
        ];
    }

    private function buildProviderMessages(Ticket $ticket, Collection $history, string $message, Collection $articles): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($ticket, $articles)],
        ];

        foreach ($history as $item) {
            $messages[] = [
                'role' => $item->role,
                'content' => $item->content,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function systemPrompt(Ticket $ticket, Collection $articles): string
    {
        $lines = [
            'You are an internal helpdesk copilot assisting a support agent.',
            'Use only the ticket context and knowledge base excerpts provided.',
            'Do not invent policies, refunds, or timelines.',
            'Keep answers concise and actionable.',
            'When drafting a customer reply, output only the reply body without subject lines.',
            'This conversation is internal — never tell the agent to send anything automatically.',
            '',
            "Ticket: {$ticket->number}",
            "Subject: {$ticket->subject}",
            'Status: '.($ticket->status?->name ?? 'Unknown'),
            'Priority: '.($ticket->priority?->name ?? 'Unknown'),
            'Customer: '.($ticket->contact?->name ?? 'Unknown').' <'.($ticket->contact?->email ?? 'unknown').'>',
        ];

        if ($ticket->description) {
            $lines[] = 'Description: '.$ticket->description;
        }

        $lines[] = '';
        $lines[] = 'Conversation:';
        $lines[] = $this->formatMessages($ticket);

        if ($articles->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Knowledge base excerpts:';
            foreach ($articles as $article) {
                $excerpt = trim(strip_tags((string) ($article->excerpt ?: mb_substr((string) $article->body, 0, 400))));
                $lines[] = "- {$article->title}: {$excerpt}";
            }
        }

        return implode("\n", $lines);
    }

    private function workspaceSystemPrompt(Collection $articles): string
    {
        $lines = [
            'You are an internal helpdesk copilot assisting a support agent in their workspace.',
            'Answer questions about helpdesk workflows, settings, tickets, SLAs, channels, and knowledge base usage.',
            'Keep answers concise, practical, and actionable.',
            'If you are unsure, say what the agent should check in settings or documentation.',
            'Do not invent policies, pricing, or features not supported by the excerpts provided.',
        ];

        if ($articles->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Knowledge base excerpts:';

            foreach ($articles as $article) {
                $excerpt = trim(strip_tags((string) ($article->excerpt ?: mb_substr((string) $article->body, 0, 400))));
                $lines[] = "- {$article->title}: {$excerpt}";
            }
        }

        return implode("\n", $lines);
    }

    private function formatMessages(Ticket $ticket): string
    {
        if ($ticket->messages->isEmpty()) {
            return '(no messages yet)';
        }

        return $ticket->messages
            ->map(function ($message) {
                $author = $message->user?->name ?? $message->contact?->name ?? 'System';
                $visibility = $message->is_internal ? ' [internal]' : '';

                return "{$author}{$visibility}: {$message->body}";
            })
            ->implode("\n");
    }

    private function mapArticles(Collection $articles): array
    {
        return $articles
            ->filter(fn ($article) => $article->is_published)
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'url' => '/knowledge/'.$article->id,
            ])->values()->all();
    }

    private function localReply(Ticket $ticket, string $message, Collection $articles): string
    {
        $articleHint = $articles->first()?->title;
        $subject = $ticket->subject;

        if (str_contains(strtolower($message), 'reply') || str_contains(strtolower($message), 'draft')) {
            $contact = $ticket->contact?->name ?? 'there';

            return "Hi {$contact},\n\nThank you for contacting us about \"{$subject}\". I'm reviewing the details now and will follow up shortly.\n\nBest regards";
        }

        $summary = "- Ticket {$ticket->number}: {$subject}\n- Status: {$ticket->status?->name}\n- Priority: {$ticket->priority?->name}";

        if ($articleHint) {
            $summary .= "\n- Relevant KB article: {$articleHint}";
        }

        return $summary."\n\n(AI provider is not configured. Set GROQ_API_KEY or OPENAI_API_KEY to enable full copilot responses.)";
    }

    private function localWorkspaceReply(string $message, Collection $articles): string
    {
        $articleHint = $articles->first()?->title;

        if ($articleHint) {
            return "Based on your knowledge base, \"{$articleHint}\" may help.\n\n(AI provider is not configured. Set GROQ_API_KEY or OPENAI_API_KEY for full copilot responses.)";
        }

        return 'I can help with ticket workflows, settings, and knowledge base questions once an AI provider is configured. Set GROQ_API_KEY or OPENAI_API_KEY in your environment.';
    }

    private function assertEnabled(): void
    {
        $this->entitlements->assertFeature('ai');

        if (! $this->settings->current()->enabled) {
            throw new AuthorizationException('AI assistance is disabled.');
        }
    }

    private function assertAgent(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->hasRole('customer')) {
            throw new AuthorizationException('Only agents can use AI Copilot.');
        }

        if (! $user->hasAnyRole(['admin', 'agent']) && ! $user->can('access.agent')) {
            throw new AuthorizationException('Only agents can use AI Copilot.');
        }
    }
}
