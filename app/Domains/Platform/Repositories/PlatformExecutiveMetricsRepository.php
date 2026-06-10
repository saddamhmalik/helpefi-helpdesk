<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Tickets\Models\Ticket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PlatformExecutiveMetricsRepository
{
    public function aggregate(): array
    {
        return Cache::remember('platform:executive_metrics', now()->addMinutes(15), function () {
            $totals = [
                'workspaces_scanned' => 0,
                'open_tickets' => 0,
                'total_tickets' => 0,
                'tickets_last_30_days' => 0,
                'total_contacts' => 0,
                'total_agents' => 0,
                'csat_responses_30_days' => 0,
                'csat_average_30_days' => null,
            ];

            $csatSum = 0;
            $csatCount = 0;

            Tenant::query()
                ->where('is_blocked', false)
                ->orderBy('id')
                ->get()
                ->each(function (Tenant $tenant) use (&$totals, &$csatSum, &$csatCount) {
                    try {
                        $tenant->run(function () use (&$totals, &$csatSum, &$csatCount) {
                            $totals['workspaces_scanned']++;
                            $totals['total_tickets'] += Ticket::query()->whereNull('merged_into_ticket_id')->count();
                            $totals['open_tickets'] += Ticket::query()
                                ->whereNull('merged_into_ticket_id')
                                ->whereHas('status', fn ($q) => $q->where('is_closed', false))
                                ->count();
                            $totals['tickets_last_30_days'] += Ticket::query()
                                ->whereNull('merged_into_ticket_id')
                                ->where('created_at', '>=', now()->subDays(30))
                                ->count();
                            $totals['total_contacts'] += Contact::query()->count();
                            $totals['total_agents'] += User::query()
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'agent']))
                                ->count();

                            $recentCsat = CsatResponse::query()
                                ->where('created_at', '>=', now()->subDays(30))
                                ->get(['rating']);

                            $totals['csat_responses_30_days'] += $recentCsat->count();
                            $csatSum += $recentCsat->sum('rating');
                            $csatCount += $recentCsat->count();
                        });
                    } catch (\Throwable) {
                    }
                });

            if ($csatCount > 0) {
                $totals['csat_average_30_days'] = round($csatSum / $csatCount, 1);
            }

            return $totals;
        });
    }
}
