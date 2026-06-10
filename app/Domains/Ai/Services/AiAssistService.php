<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Ai\Repositories\AiSettingRepository;
use App\Domains\Ai\Repositories\KnowledgeAiRepository;
use App\Domains\Ai\Repositories\TicketAiRepository;
use App\Domains\Brands\Services\BrandService;
use App\Models\User;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;

class AiAssistService
{
    public function __construct(
        private AiSettingRepository $settings,
        private TicketAiRepository $tickets,
        private KnowledgeAiRepository $knowledge,
        private AiCompletionClient $client,
        private BillingService $billing,
        private BrandService $brands,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->current()->enabled;
    }

    public function status(): array
    {
        $setting = $this->settings->current();

        return [
            'enabled' => $setting->enabled,
            'model' => $setting->model ?: config('ai.model'),
            'triage_enabled' => $setting->triage_enabled,
            'provider_configured' => $this->client->available(),
            'mode' => $this->client->available() ? 'openai' : 'local',
        ];
    }

    public function updateSettings(array $data): array
    {
        $setting = $this->settings->update($this->settings->current(), [
            'enabled' => $data['enabled'] ?? false,
            'model' => $data['model'] ?? null,
            'triage_enabled' => $data['triage_enabled'] ?? false,
        ]);

        if ($setting->model) {
            config(['ai.model' => $setting->model]);
        }

        return $this->status();
    }

    public function suggestReply(int $ticketId, int $userId): array
    {
        $this->assertEnabled();

        $ticket = $this->tickets->forTicket($ticketId);
        $agent = User::query()->findOrFail($userId);

        if ($this->client->available()) {
            return [
                'reply' => $this->client->complete(
                    'You are a professional helpdesk agent. Write concise, empathetic customer replies. Do not invent facts. Output only the reply body.',
                    $this->buildSuggestReplyPrompt($ticket, $agent->name),
                ),
                'source' => 'openai',
            ];
        }

        return [
            'reply' => $this->localSuggestReply($ticket, $agent->name),
            'source' => 'local',
        ];
    }

    public function summarize(int $ticketId): array
    {
        $this->assertEnabled();

        $ticket = $this->tickets->forTicket($ticketId);

        if ($this->client->available()) {
            return [
                'summary' => $this->client->complete(
                    'You summarize helpdesk tickets for agents. Use short bullet points. Mention status, customer issue, and next step.',
                    $this->buildConversationPrompt($ticket),
                ),
                'source' => 'openai',
            ];
        }

        return [
            'summary' => $this->localSummarize($ticket),
            'source' => 'local',
        ];
    }

    public function kbAssist(int $ticketId): array
    {
        $this->assertEnabled();

        $ticket = $this->tickets->forTicket($ticketId);
        $query = trim($ticket->subject.' '.$this->lastCustomerMessage($ticket));
        $articles = $this->knowledge->searchPublished($query);

        $mapped = $articles->map(fn ($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'url' => route('portal.article', [
                'brand' => $this->brands->defaultSlug(),
                'articleSlug' => $article->slug,
            ]),
        ])->values()->all();

        if ($this->client->available() && $mapped) {
            $picked = $this->client->complete(
                'Pick the most relevant knowledge base article titles for this ticket. Return a comma-separated list of titles only.',
                "Ticket subject: {$ticket->subject}\nCustomer message: ".$this->lastCustomerMessage($ticket)."\nArticles: ".collect($mapped)->pluck('title')->implode(', '),
            );

            $titles = collect(explode(',', $picked))
                ->map(fn ($title) => trim($title))
                ->filter()
                ->values();

            if ($titles->isNotEmpty()) {
                $mapped = collect($mapped)
                    ->sortByDesc(fn ($article) => $titles->contains($article['title']) ? 1 : 0)
                    ->values()
                    ->all();
            }

            return [
                'articles' => $mapped,
                'source' => 'openai',
            ];
        }

        return [
            'articles' => $mapped,
            'source' => 'local',
        ];
    }

    private function assertEnabled(): void
    {
        $this->billing->assertFeature('ai');

        if (! $this->isEnabled()) {
            throw new AuthorizationException('AI assistance is disabled.');
        }
    }

    private function buildSuggestReplyPrompt(Ticket $ticket, string $agentName): string
    {
        return "Agent name: {$agentName}\nTicket: {$ticket->number}\nSubject: {$ticket->subject}\nStatus: {$ticket->status?->name}\nPriority: {$ticket->priority?->name}\n\nConversation:\n".$this->formatMessages($ticket)."\n\nWrite a reply to the customer.";
    }

    private function buildConversationPrompt(Ticket $ticket): string
    {
        $lines = [
            "Ticket: {$ticket->number}",
            "Subject: {$ticket->subject}",
            "Status: {$ticket->status?->name}",
            "Priority: {$ticket->priority?->name}",
        ];

        if ($ticket->description) {
            $lines[] = "Description: {$ticket->description}";
        }

        $lines[] = 'Conversation:';
        $lines[] = $this->formatMessages($ticket);

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

    private function lastCustomerMessage(Ticket $ticket): string
    {
        $message = $ticket->messages
            ->filter(fn ($item) => ! $item->is_internal && $item->contact_id)
            ->last();

        if ($message) {
            return $message->body;
        }

        return $ticket->description ?? '';
    }

    private function localSuggestReply(Ticket $ticket, string $agentName): string
    {
        $contactName = $ticket->contact?->name ?? 'there';
        $issue = $this->lastCustomerMessage($ticket) ?: $ticket->subject;
        $snippet = mb_strlen($issue) > 180 ? mb_substr($issue, 0, 180).'…' : $issue;

        return "Hi {$contactName},\n\nThank you for contacting us about \"{$ticket->subject}\". I understand you're reaching out regarding: {$snippet}\n\nI'm looking into this now and will follow up shortly with an update.\n\nBest regards,\n{$agentName}";
    }

    private function localSummarize(Ticket $ticket): string
    {
        $customerMessages = $ticket->messages->where('is_internal', false)->count();
        $internalNotes = $ticket->messages->where('is_internal', true)->count();
        $lastMessage = $ticket->messages->last()?->body;
        $preview = $lastMessage
            ? (mb_strlen($lastMessage) > 120 ? mb_substr($lastMessage, 0, 120).'…' : $lastMessage)
            : 'No messages yet.';

        return "- Ticket {$ticket->number}: {$ticket->subject}\n- Status: {$ticket->status?->name}, priority: {$ticket->priority?->name}\n- Customer messages: {$customerMessages}, internal notes: {$internalNotes}\n- Latest activity: {$preview}";
    }
}
