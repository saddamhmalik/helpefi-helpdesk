<?php

namespace App\Domains\Sla\Repositories;

use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\SlaTarget;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class SlaRepository
{
    public function defaultPolicy(): ?SlaPolicy
    {
        return SlaPolicy::query()
            ->with(['businessHours', 'targets.priority', 'team:id,name'])
            ->where('is_default', true)
            ->first();
    }

    public function policyForTicket(Ticket $ticket): ?SlaPolicy
    {
        $ticket->loadMissing(['contact.organization', 'team']);

        if ($ticket->team_id) {
            $teamPolicy = SlaPolicy::query()
                ->with(['businessHours', 'targets.priority', 'team:id,name'])
                ->where('team_id', $ticket->team_id)
                ->first();

            if ($teamPolicy) {
                return $teamPolicy;
            }
        }

        $tier = $ticket->contact?->organization?->customer_tier;

        if ($tier) {
            $tierPolicy = SlaPolicy::query()
                ->with(['businessHours', 'targets.priority', 'team:id,name'])
                ->where('customer_tier', $tier)
                ->whereNull('team_id')
                ->first();

            if ($tierPolicy) {
                return $tierPolicy;
            }
        }

        return $this->defaultPolicy();
    }

    public function createPolicy(array $data): SlaPolicy
    {
        return SlaPolicy::query()->create($data);
    }

    public function updatePolicy(SlaPolicy $policy, array $data): SlaPolicy
    {
        $policy->update($data);

        return $policy->fresh(['businessHours', 'targets.priority', 'team:id,name']);
    }

    public function deletePolicy(SlaPolicy $policy): void
    {
        $policy->delete();
    }

    public function copyTargetsFromPolicy(SlaPolicy $source, SlaPolicy $destination): void
    {
        foreach ($source->targets as $target) {
            SlaTarget::query()->updateOrCreate(
                [
                    'sla_policy_id' => $destination->id,
                    'ticket_priority_id' => $target->ticket_priority_id,
                ],
                [
                    'first_response_minutes' => $target->first_response_minutes,
                    'resolution_minutes' => $target->resolution_minutes,
                ],
            );
        }
    }

    public function policies(): Collection
    {
        return SlaPolicy::query()
            ->with(['businessHours', 'targets.priority', 'team:id,name'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function findPolicy(int $id): SlaPolicy
    {
        return SlaPolicy::query()
            ->with(['businessHours', 'targets.priority', 'team:id,name'])
            ->findOrFail($id);
    }

    public function targetForPolicyAndPriority(int $policyId, int $priorityId): ?SlaTarget
    {
        return SlaTarget::query()
            ->where('sla_policy_id', $policyId)
            ->where('ticket_priority_id', $priorityId)
            ->first();
    }

    public function createTimer(array $data): TicketSlaTimer
    {
        return TicketSlaTimer::query()->create($data);
    }

    public function timerForTicket(int $ticketId): ?TicketSlaTimer
    {
        return TicketSlaTimer::query()
            ->with('policy.businessHours')
            ->where('ticket_id', $ticketId)
            ->first();
    }

    public function updateTimer(TicketSlaTimer $timer, array $data): TicketSlaTimer
    {
        $timer->update($data);

        return $timer->fresh('policy.businessHours');
    }

    public function markBreaches(): int
    {
        return count($this->collectBreaches());
    }

    public function collectBreaches(): array
    {
        $breaches = [];

        $firstResponseTimers = TicketSlaTimer::query()
            ->with('ticket')
            ->whereNull('first_responded_at')
            ->where('first_response_breached', false)
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<', now())
            ->get();

        foreach ($firstResponseTimers as $timer) {
            $timer->update(['first_response_breached' => true]);

            if ($timer->ticket) {
                $breaches[] = ['ticket' => $timer->ticket, 'type' => 'first_response'];
            }
        }

        $resolutionTimers = TicketSlaTimer::query()
            ->with('ticket')
            ->whereNull('resolved_at')
            ->where('resolution_breached', false)
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<', now())
            ->get();

        foreach ($resolutionTimers as $timer) {
            $timer->update(['resolution_breached' => true]);

            if ($timer->ticket) {
                $breaches[] = ['ticket' => $timer->ticket, 'type' => 'resolution'];
            }
        }

        return $breaches;
    }

    public function breachedCount(): int
    {
        return TicketSlaTimer::query()
            ->where(function ($q) {
                $q->where('first_response_breached', true)
                    ->orWhere('resolution_breached', true);
            })
            ->whereHas('ticket', fn ($q) => $q->whereNull('merged_into_ticket_id'))
            ->count();
    }

    public function updateTarget(SlaTarget $target, array $data): SlaTarget
    {
        $target->update($data);

        return $target->fresh('priority');
    }
}
