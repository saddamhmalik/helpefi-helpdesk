<?php

namespace Database\Seeders;

use App\Domains\Sla\Models\BusinessHours;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\SlaTarget;
use App\Domains\Tickets\Models\TicketPriority;
use Illuminate\Database\Seeder;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        $weekday = ['start' => '09:00', 'end' => '17:00'];

        $hours = BusinessHours::query()->updateOrCreate(
            ['name' => 'Standard Weekdays'],
            [
                'timezone' => config('app.timezone', 'UTC'),
                'schedule' => [
                    'mon' => $weekday,
                    'tue' => $weekday,
                    'wed' => $weekday,
                    'thu' => $weekday,
                    'fri' => $weekday,
                    'sat' => null,
                    'sun' => null,
                ],
            ],
        );

        $policy = SlaPolicy::query()->updateOrCreate(
            ['name' => 'Default SLA'],
            [
                'is_default' => true,
                'business_hours_id' => $hours->id,
            ],
        );

        $targets = [
            'low' => ['first_response_minutes' => 480, 'resolution_minutes' => 2880],
            'normal' => ['first_response_minutes' => 240, 'resolution_minutes' => 1440],
            'high' => ['first_response_minutes' => 60, 'resolution_minutes' => 480],
            'urgent' => ['first_response_minutes' => 15, 'resolution_minutes' => 240],
        ];

        foreach ($targets as $slug => $minutes) {
            $priority = TicketPriority::query()->where('slug', $slug)->first();

            if (! $priority) {
                continue;
            }

            SlaTarget::query()->updateOrCreate(
                [
                    'sla_policy_id' => $policy->id,
                    'ticket_priority_id' => $priority->id,
                ],
                $minutes,
            );
        }
    }
}
