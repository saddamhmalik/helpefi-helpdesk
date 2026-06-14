<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiCompletionClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CentralMarketingAiService
{
    private const DEMO_ARTICLES = [
        [
            'title' => 'AI deflection on portal and live chat',
            'keywords' => ['deflect', 'deflection', 'self-service', 'portal', 'chat', 'widget', 'customer'],
            'body' => 'helpefi answers customer questions from your knowledge base before they submit a ticket. AI deflection runs on the branded portal and embeddable live chat widget, surfacing relevant articles and concise answers. Teams typically see up to 40% fewer repeat questions.',
        ],
        [
            'title' => 'AI reply drafts and thread summaries',
            'keywords' => ['draft', 'reply', 'summarize', 'summary', 'agent', 'assist', 'suggest'],
            'body' => 'Agents get one-click AI reply drafts grounded in the ticket conversation, plus thread summaries and suggested knowledge base articles. Drafts appear in the ticket composer and workspace so agents review before sending.',
        ],
        [
            'title' => 'Agent Copilot for ticket context',
            'keywords' => ['copilot', 'assistant', 'context', 'help', 'ticket', 'workspace'],
            'body' => 'Agent Copilot is a side panel on every ticket. Ask it to summarize the thread, draft a customer reply, suggest next steps, or find KB articles — all with full ticket context and your published help content.',
        ],
        [
            'title' => 'Semantic knowledge base search',
            'keywords' => ['semantic', 'search', 'knowledge', 'kb', 'article', 'vector', 'embedding'],
            'body' => 'helpefi uses vector embeddings for semantic KB search. Customers and agents find the right article even when wording differs. Search powers portal deflection, agent KB assist, and Copilot article suggestions.',
        ],
        [
            'title' => 'AI triage on new tickets',
            'keywords' => ['triage', 'priority', 'route', 'routing', 'auto', 'classify', 'tag'],
            'body' => 'When AI triage is enabled, new tickets are analyzed on creation to suggest priority, tags, and routing hints. Agents stay in control — suggestions appear in the ticket sidebar for quick acceptance or override.',
        ],
        [
            'title' => 'Free trial and Pro AI',
            'keywords' => ['trial', 'pricing', 'plan', 'pro', 'enterprise', 'free', 'cost', 'include'],
            'body' => 'Start a free trial with full platform access — no credit card required. AI assist, deflection, Copilot, semantic search, and triage are included on Pro plans. Service Desk ITSM is a separate paid add-on on any paid plan. Upgrade anytime from workspace billing settings.',
        ],
    ];

    public function __construct(private AiCompletionClient $client)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('ai.marketing_demo_enabled', true);
    }

    public function ask(string $query): array
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'query' => 'AI demo is unavailable.',
            ]);
        }

        $query = trim($query);

        if ($query === '') {
            throw ValidationException::withMessages([
                'query' => 'Please enter a question.',
            ]);
        }

        if (mb_strlen($query) > 500) {
            throw ValidationException::withMessages([
                'query' => 'Question must be 500 characters or fewer.',
            ]);
        }

        $articles = $this->matchArticles($query);
        $mapped = $this->mapArticles($articles);

        if ($this->client->available() && $mapped !== []) {
            $answer = $this->client->complete(
                'You are a helpful product assistant for helpefi, a helpdesk and ITSM platform. Answer using ONLY the provided knowledge base articles about helpefi features. Be concise, friendly, and plain text. If articles do not answer the question, briefly explain helpefi has AI deflection, reply drafts, Agent Copilot, semantic KB search, and AI triage — then suggest starting a free trial.',
                "Visitor question: {$query}\n\nKnowledge base articles:\n".$this->buildArticleContext($articles),
            );
            $source = (string) config('ai.provider', 'openai');
        } else {
            $answer = $this->localAnswer($articles, $query);
            $source = 'local';
        }

        return [
            'answer' => $answer,
            'articles' => $mapped,
            'source' => $source,
        ];
    }

    private function matchArticles(string $query): Collection
    {
        $normalized = Str::lower($query);
        $tokens = collect(preg_split('/\s+/', $normalized) ?: [])
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->values();

        $scored = collect(self::DEMO_ARTICLES)
            ->map(function (array $article) use ($normalized, $tokens) {
                $score = 0;

                foreach ($article['keywords'] as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        $score += 3;
                    }
                }

                foreach ($tokens as $token) {
                    if (str_contains(Str::lower($article['title']), $token) || str_contains(Str::lower($article['body']), $token)) {
                        $score += 1;
                    }
                }

                return ['article' => $article, 'score' => $score];
            })
            ->sortByDesc('score')
            ->values();

        $top = $scored->first();

        if ($top === null || $top['score'] === 0) {
            return collect([self::DEMO_ARTICLES[0], self::DEMO_ARTICLES[1]]);
        }

        return $scored
            ->filter(fn (array $row) => $row['score'] > 0)
            ->take(3)
            ->pluck('article');
    }

    private function mapArticles(Collection $articles): array
    {
        return $articles->map(fn (array $article) => [
            'title' => $article['title'],
            'excerpt' => mb_strlen($article['body']) > 160
                ? mb_substr($article['body'], 0, 160).'…'
                : $article['body'],
        ])->values()->all();
    }

    private function buildArticleContext(Collection $articles): string
    {
        return $articles->map(function (array $article) {
            return "### {$article['title']}\n{$article['body']}";
        })->implode("\n\n");
    }

    private function localAnswer(Collection $articles, string $query): string
    {
        if ($articles->isEmpty()) {
            return 'helpefi includes AI deflection, reply drafts, Agent Copilot, semantic KB search, and AI triage. Start a free trial to see it on your own tickets.';
        }

        $top = $articles->first();
        $snippet = $top['body'];

        if (mb_strlen($snippet) > 320) {
            $snippet = mb_substr($snippet, 0, 320).'…';
        }

        return "Here's what I found about \"{$top['title']}\": {$snippet}\n\nWant to try it on your team? Start a free trial — no credit card required.";
    }
}
