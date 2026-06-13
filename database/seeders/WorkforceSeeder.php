<?php

namespace Database\Seeders;

use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class WorkforceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->role('admin')->orderBy('id')->first();

        if (! $admin) {
            return;
        }

        $support = Department::query()->updateOrCreate(
            ['slug' => 'support'],
            [
                'name' => 'Customer Support',
                'description' => 'Front-line support for customer issues.',
                'head_user_id' => $admin->id,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $tier1 = Team::query()->updateOrCreate(
            ['department_id' => $support->id, 'slug' => 'tier-1'],
            [
                'name' => 'Tier 1',
                'description' => 'First-line ticket handling.',
                'lead_user_id' => $admin->id,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $tier1->members()->syncWithoutDetaching([
            $admin->id => ['org_role' => Team::ROLE_TEAM_LEAD],
        ]);

        $policy = SlaPolicy::query()->where('is_default', true)->first();

        if ($policy) {
            $urgentPriority = TicketPriority::query()->where('slug', 'high')->value('id');

            SlaEscalationRule::query()->updateOrCreate(
                [
                    'sla_policy_id' => $policy->id,
                    'level' => 1,
                    'breach_type' => SlaEscalationRule::BREACH_FIRST_RESPONSE,
                ],
                [
                    'delay_minutes_after_breach' => 0,
                    'actions' => [
                        ['type' => 'notify_team_lead', 'value' => 'Level 1 escalation: first response SLA breached.'],
                        ['type' => 'add_watcher', 'value' => null],
                    ],
                    'is_active' => true,
                ],
            );

            SlaEscalationRule::query()->updateOrCreate(
                [
                    'sla_policy_id' => $policy->id,
                    'level' => 2,
                    'breach_type' => SlaEscalationRule::BREACH_FIRST_RESPONSE,
                ],
                [
                    'delay_minutes_after_breach' => 30,
                    'actions' => [
                        ['type' => 'notify_department_head', 'value' => 'Level 2 escalation: ticket still awaiting first response.'],
                        ['type' => 'assign_to_team_lead', 'value' => null],
                    ],
                    'is_active' => true,
                ],
            );

            SlaEscalationRule::query()->updateOrCreate(
                [
                    'sla_policy_id' => $policy->id,
                    'level' => 1,
                    'breach_type' => SlaEscalationRule::BREACH_RESOLUTION,
                ],
                [
                    'delay_minutes_after_breach' => 0,
                    'actions' => [
                        ['type' => 'notify_team_lead', 'value' => 'Level 1 escalation: resolution SLA breached.'],
                    ],
                    'is_active' => true,
                ],
            );

            SlaEscalationRule::query()->updateOrCreate(
                [
                    'sla_policy_id' => $policy->id,
                    'level' => 2,
                    'breach_type' => SlaEscalationRule::BREACH_RESOLUTION,
                ],
                [
                    'delay_minutes_after_breach' => 60,
                    'actions' => [
                        ['type' => 'notify_department_head', 'value' => 'Level 2 escalation: ticket remains unresolved.'],
                        ['type' => 'assign_to_department_head', 'value' => null],
                        ['type' => 'add_internal_note', 'value' => 'Auto-escalated to department head due to resolution SLA breach.'],
                    ],
                    'is_active' => true,
                ],
            );
        }
    }
}
