<?php

namespace App\Domains\Reports\Services;

use App\Domains\Reports\Jobs\SendScheduledReportJob;
use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Repositories\ReportRepository;
use App\Domains\Reports\Repositories\ReportScheduleRepository;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ReportScheduleService
{
    public function __construct(
        private ReportScheduleRepository $schedules,
        private ReportRepository $reports,
    ) {
    }

    public function options(): array
    {
        return [
            'frequencies' => [
                ['value' => ReportSchedule::FREQUENCY_DAILY, 'label' => 'Daily'],
                ['value' => ReportSchedule::FREQUENCY_WEEKLY, 'label' => 'Weekly'],
            ],
            'weekdays' => [
                ['value' => 1, 'label' => 'Monday'],
                ['value' => 2, 'label' => 'Tuesday'],
                ['value' => 3, 'label' => 'Wednesday'],
                ['value' => 4, 'label' => 'Thursday'],
                ['value' => 5, 'label' => 'Friday'],
                ['value' => 6, 'label' => 'Saturday'],
                ['value' => 7, 'label' => 'Sunday'],
            ],
            'formats' => [
                ['value' => ReportSchedule::FORMAT_CSV, 'label' => 'CSV'],
                ['value' => ReportSchedule::FORMAT_PDF, 'label' => 'PDF'],
            ],
            'hours' => collect(range(0, 23))->map(fn (int $hour) => [
                'value' => $hour,
                'label' => sprintf('%02d:00', $hour),
            ])->values()->all(),
        ];
    }

    public function upsert(int $userId, int $savedReportId, array $data): ReportSchedule
    {
        $report = $this->reports->findSavedForUser($savedReportId, $userId);
        $frequency = $data['frequency'];
        $weekday = $data['weekday'] ?? null;
        $sendHour = (int) $data['send_hour'];
        $format = $data['format'] ?? ReportSchedule::FORMAT_CSV;

        $this->assertFrequency($frequency);
        $this->assertFormat($format);
        $this->assertSendHour($sendHour);

        if ($frequency === ReportSchedule::FREQUENCY_WEEKLY) {
            if ($weekday === null || $weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException('Weekday is required for weekly schedules.');
            }
        } else {
            $weekday = null;
        }

        $schedule = $this->schedules->upsert($report, $userId, [
            'frequency' => $frequency,
            'weekday' => $weekday,
            'send_hour' => $sendHour,
            'format' => $format,
            'is_enabled' => $data['is_enabled'] ?? true,
            'next_run_at' => $this->computeNextRunAt($frequency, $sendHour, $weekday),
        ]);

        return $schedule->fresh(['savedReport']);
    }

    public function delete(int $userId, int $savedReportId): void
    {
        $report = $this->reports->findSavedForUser($savedReportId, $userId);
        $this->schedules->deleteForSavedReport($report);
    }

    public function dispatchDue(): int
    {
        $count = 0;

        foreach ($this->schedules->due() as $schedule) {
            SendScheduledReportJob::dispatch($schedule->id);
            $count++;
        }

        return $count;
    }

    public function markSent(ReportSchedule $schedule): void
    {
        $schedule->update([
            'last_sent_at' => now(),
            'next_run_at' => $this->computeNextRunAt(
                $schedule->frequency,
                $schedule->send_hour,
                $schedule->weekday,
                now()->addMinute(),
            ),
        ]);
    }

    public function computeNextRunAt(string $frequency, int $sendHour, ?int $weekday, ?Carbon $after = null): Carbon
    {
        $after = ($after ?? now())->copy();
        $next = $after->copy()->startOfDay()->setTime($sendHour, 0);

        if ($frequency === ReportSchedule::FREQUENCY_WEEKLY) {
            while ($next->dayOfWeekIso !== $weekday || $next->lte($after)) {
                $next->addDay();
            }

            return $next;
        }

        if ($next->lte($after)) {
            $next->addDay();
        }

        return $next;
    }

    private function assertFrequency(string $frequency): void
    {
        if (! in_array($frequency, [ReportSchedule::FREQUENCY_DAILY, ReportSchedule::FREQUENCY_WEEKLY], true)) {
            throw new InvalidArgumentException('Invalid schedule frequency.');
        }
    }

    private function assertFormat(string $format): void
    {
        if (! in_array($format, [ReportSchedule::FORMAT_CSV, ReportSchedule::FORMAT_PDF], true)) {
            throw new InvalidArgumentException('Invalid schedule format.');
        }
    }

    private function assertSendHour(int $sendHour): void
    {
        if ($sendHour < 0 || $sendHour > 23) {
            throw new InvalidArgumentException('Invalid send hour.');
        }
    }
}
