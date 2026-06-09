<?php

namespace App\Domains\Chat\Services;

use App\Domains\Channels\Models\Channel;
use App\Domains\Sla\Models\BusinessHours;
use Illuminate\Support\Carbon;

class ChatAvailabilityService
{
    public function isOnline(Channel $channel): bool
    {
        return $this->status($channel)['online'];
    }

    public function status(Channel $channel): array
    {
        $mode = $channel->settings['offline_mode'] ?? 'business_hours';

        return match ($mode) {
            'always' => [
                'online' => false,
                'reason' => 'Offline mode is set to always offline.',
            ],
            'never' => [
                'online' => true,
                'reason' => 'Offline mode is set to always online.',
            ],
            default => $this->businessHoursStatus(),
        };
    }

    private function businessHoursStatus(): array
    {
        $hours = BusinessHours::query()->orderBy('id')->first();

        if (! $hours || ! is_array($hours->schedule)) {
            return [
                'online' => true,
                'reason' => 'No business hours configured; chat is treated as online.',
            ];
        }

        $online = $this->withinBusinessHours($hours);

        return [
            'online' => $online,
            'reason' => $online
                ? "Within {$hours->name} ({$hours->timezone})."
                : "Outside {$hours->name} ({$hours->timezone}). Configure hours under Settings → SLA.",
        ];
    }

    private function withinBusinessHours(?BusinessHours $hours = null): bool
    {
        $hours ??= BusinessHours::query()->orderBy('id')->first();

        if (! $hours || ! is_array($hours->schedule)) {
            return true;
        }

        $now = Carbon::now($hours->timezone ?: config('app.timezone'));
        $day = strtolower($now->format('D'));
        $dayKey = match ($day) {
            'mon' => 'mon',
            'tue' => 'tue',
            'wed' => 'wed',
            'thu' => 'thu',
            'fri' => 'fri',
            'sat' => 'sat',
            'sun' => 'sun',
            default => 'mon',
        };

        $window = $hours->schedule[$dayKey] ?? null;

        if (! is_array($window) || empty($window['start']) || empty($window['end'])) {
            return false;
        }

        $start = $now->copy()->setTimeFromTimeString($window['start']);
        $end = $now->copy()->setTimeFromTimeString($window['end']);

        return $now->betweenIncluded($start, $end);
    }
}
