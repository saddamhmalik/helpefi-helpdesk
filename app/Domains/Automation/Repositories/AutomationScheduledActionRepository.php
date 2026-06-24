<?php

namespace App\Domains\Automation\Repositories;

use App\Domains\Automation\Models\AutomationScheduledAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AutomationScheduledActionRepository
{
    public function schedule(
        int $ticketId,
        ?int $ruleId,
        array $actions,
        Carbon $runAt,
        array $context = [],
    ): AutomationScheduledAction {
        return AutomationScheduledAction::query()->create([
            'ticket_id' => $ticketId,
            'automation_rule_id' => $ruleId,
            'actions' => $actions,
            'context' => $context,
            'run_at' => $runAt,
        ]);
    }

    public function due(): Collection
    {
        return AutomationScheduledAction::query()
            ->whereNull('processed_at')
            ->where('run_at', '<=', now())
            ->orderBy('run_at')
            ->get();
    }

    public function claimDue(int $id): ?AutomationScheduledAction
    {
        $claimed = AutomationScheduledAction::query()
            ->where('id', $id)
            ->whereNull('processed_at')
            ->where('run_at', '<=', now())
            ->update(['processed_at' => now()]);

        if (! $claimed) {
            return null;
        }

        return AutomationScheduledAction::query()->find($id);
    }

    public function markProcessed(AutomationScheduledAction $scheduled): void
    {
        if ($scheduled->processed_at === null) {
            $scheduled->update(['processed_at' => now()]);
        }
    }
}
