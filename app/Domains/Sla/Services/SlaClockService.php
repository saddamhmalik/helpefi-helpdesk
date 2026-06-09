<?php

namespace App\Domains\Sla\Services;

use App\Domains\Sla\Models\BusinessHours;
use Carbon\Carbon;

class SlaClockService
{
    private const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function addBusinessMinutes(Carbon $from, int $minutes, BusinessHours $hours): Carbon
    {
        if ($minutes <= 0) {
            return $from->copy();
        }

        $timezone = $hours->timezone ?: config('app.timezone');
        $current = $from->copy()->timezone($timezone);
        $remaining = $minutes;

        while ($remaining > 0) {
            $dayKey = self::DAY_KEYS[$current->dayOfWeek];
            $window = $hours->schedule[$dayKey] ?? null;

            if (! $window || empty($window['start']) || empty($window['end'])) {
                $current = $this->nextDayStart($current);

                continue;
            }

            [$startHour, $startMinute] = array_map('intval', explode(':', $window['start']));
            [$endHour, $endMinute] = array_map('intval', explode(':', $window['end']));

            $dayStart = $current->copy()->setTime($startHour, $startMinute, 0);
            $dayEnd = $current->copy()->setTime($endHour, $endMinute, 0);

            if ($current->lt($dayStart)) {
                $current = $dayStart->copy();
            }

            if ($current->gte($dayEnd)) {
                $current = $this->nextDayStart($current);

                continue;
            }

            $available = $current->diffInMinutes($dayEnd);

            if ($available >= $remaining) {
                return $current->copy()->addMinutes($remaining);
            }

            $remaining -= $available;
            $current = $this->nextDayStart($current);
        }

        return $current;
    }

    private function nextDayStart(Carbon $current): Carbon
    {
        return $current->copy()->addDay()->startOfDay();
    }
}
