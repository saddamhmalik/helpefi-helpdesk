<?php

namespace App\Domains\Growth\Services;

use App\Domains\Billing\Repositories\UsageRepository;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Support\Carbon;

class WorkspaceEngagementService
{
    public function __construct(private UsageRepository $usage)
    {
    }

    public function snapshot(): array
    {
        $since = Carbon::now()->subDays(30);

        return [
            'tickets_total' => Ticket::query()->count(),
            'tickets_30d' => Ticket::query()->where('created_at', '>=', $since)->count(),
            'agent_replies_30d' => TicketMessage::query()
                ->where('created_at', '>=', $since)
                ->whereNotNull('user_id')
                ->where('is_internal', false)
                ->count(),
            'team_members' => $this->usage->agentCount(),
            'published_articles' => KnowledgeArticle::query()->where('is_published', true)->count(),
            'first_ticket_at' => Ticket::query()->oldest('created_at')->value('created_at')?->toIso8601String(),
            'first_reply_at' => TicketMessage::query()
                ->whereNotNull('user_id')
                ->where('is_internal', false)
                ->oldest('created_at')
                ->value('created_at')?->toIso8601String(),
        ];
    }
}
