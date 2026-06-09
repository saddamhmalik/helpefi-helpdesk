<?php

namespace Database\Seeders;

use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketLookupSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Open', 'slug' => 'open', 'color' => 'blue', 'sort_order' => 1, 'is_closed' => false],
            ['name' => 'Pending', 'slug' => 'pending', 'color' => 'amber', 'sort_order' => 2, 'is_closed' => false],
            ['name' => 'Resolved', 'slug' => 'resolved', 'color' => 'green', 'sort_order' => 3, 'is_closed' => true],
            ['name' => 'Closed', 'slug' => 'closed', 'color' => 'slate', 'sort_order' => 4, 'is_closed' => true],
        ];

        foreach ($statuses as $status) {
            TicketStatus::query()->updateOrCreate(['slug' => $status['slug']], $status);
        }

        $priorities = [
            ['name' => 'Low', 'slug' => 'low', 'sort_order' => 1],
            ['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 2],
            ['name' => 'High', 'slug' => 'high', 'sort_order' => 3],
            ['name' => 'Urgent', 'slug' => 'urgent', 'sort_order' => 4],
        ];

        foreach ($priorities as $priority) {
            TicketPriority::query()->updateOrCreate(['slug' => $priority['slug']], $priority);
        }
    }
}
