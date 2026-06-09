<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use InvalidArgumentException;

class PlatformBackupScheduleService
{
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public function __construct(
        private CentralSettingRepository $settings,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function isEnabled(): bool
    {
        $setting = $this->settings->current();

        if ($setting->backup_schedule_enabled !== null) {
            return (bool) $setting->backup_schedule_enabled;
        }

        return (bool) config('backup.schedule_enabled', false);
    }

    public function frequency(): string
    {
        return $this->normalizeFrequency(
            $this->settings->current()->backup_schedule_frequency
                ?? config('backup.schedule_frequency', self::FREQUENCY_DAILY),
        );
    }

    public function weekday(): int
    {
        $weekday = $this->settings->current()->backup_schedule_weekday;

        if ($weekday === null) {
            $weekday = (int) config('backup.schedule_weekday', 1);
        }

        return max(0, min(6, (int) $weekday));
    }

    public function time(): string
    {
        return $this->normalizeTime(
            $this->settings->current()->backup_schedule_time
                ?? config('backup.schedule_time', '02:00'),
        );
    }

    public function isDue(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $now = now();
        [$hour, $minute] = $this->parseTime($this->time());

        if ($now->hour !== $hour || $now->minute !== $minute) {
            return false;
        }

        if ($this->frequency() === self::FREQUENCY_WEEKLY) {
            return $now->dayOfWeek === $this->weekday();
        }

        return true;
    }

    public function snapshot(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'frequency' => $this->frequency(),
            'weekday' => $this->weekday(),
            'time' => $this->time(),
            'summary' => $this->summary(),
        ];
    }

    public function options(): array
    {
        return [
            'frequencies' => [
                ['value' => self::FREQUENCY_DAILY, 'label' => 'Daily'],
                ['value' => self::FREQUENCY_WEEKLY, 'label' => 'Weekly'],
            ],
            'weekdays' => [
                ['value' => 0, 'label' => 'Sunday'],
                ['value' => 1, 'label' => 'Monday'],
                ['value' => 2, 'label' => 'Tuesday'],
                ['value' => 3, 'label' => 'Wednesday'],
                ['value' => 4, 'label' => 'Thursday'],
                ['value' => 5, 'label' => 'Friday'],
                ['value' => 6, 'label' => 'Saturday'],
            ],
        ];
    }

    public function update(array $data): array
    {
        $before = $this->snapshot();

        $payload = [
            'backup_schedule_enabled' => (bool) ($data['enabled'] ?? false),
            'backup_schedule_frequency' => $this->normalizeFrequency($data['frequency'] ?? self::FREQUENCY_DAILY),
            'backup_schedule_weekday' => max(0, min(6, (int) ($data['weekday'] ?? 1))),
            'backup_schedule_time' => $this->normalizeTime($data['time'] ?? '02:00'),
        ];

        $this->settings->update($this->settings->current(), $payload);

        $after = $this->snapshot();

        $this->audit->record('platform.backup.schedule_updated', properties: [
            'before' => $before,
            'after' => $after,
        ]);

        return $after;
    }

    public function summary(): string
    {
        if (! $this->isEnabled()) {
            return 'Automatic backups are disabled.';
        }

        $time = $this->time();

        if ($this->frequency() === self::FREQUENCY_WEEKLY) {
            $weekday = collect($this->options()['weekdays'])
                ->firstWhere('value', $this->weekday())['label'] ?? 'Monday';

            return "Runs every {$weekday} at {$time}.";
        }

        return "Runs daily at {$time}.";
    }

    private function normalizeFrequency(string $frequency): string
    {
        $frequency = strtolower(trim($frequency));

        if (! in_array($frequency, [self::FREQUENCY_DAILY, self::FREQUENCY_WEEKLY], true)) {
            throw new InvalidArgumentException('Invalid backup schedule frequency.');
        }

        return $frequency;
    }

    private function normalizeTime(string $time): string
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($time), $matches)) {
            throw new InvalidArgumentException('Invalid backup schedule time.');
        }

        return $matches[1].':'.$matches[2];
    }

    private function parseTime(string $time): array
    {
        [$hour, $minute] = explode(':', $this->normalizeTime($time));

        return [(int) $hour, (int) $minute];
    }
}
