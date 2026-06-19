<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use InvalidArgumentException;

class TenantInfrastructureAutoBackupScheduleService
{
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

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

    public function snapshot(TenantInfrastructure $record): array
    {
        return [
            'enabled' => (bool) $record->auto_backup_enabled,
            'frequency' => $this->frequency($record),
            'weekday' => $this->weekday($record),
            'time' => $this->time($record),
            'last_run_at' => $record->auto_backup_last_run_at?->toIso8601String(),
            'summary' => $this->summary($record),
        ];
    }

    public function apply(TenantInfrastructure $record, array $data): TenantInfrastructure
    {
        $record->auto_backup_enabled = (bool) ($data['enabled'] ?? false);
        $record->auto_backup_frequency = $this->normalizeFrequency($data['frequency'] ?? self::FREQUENCY_DAILY);
        $record->auto_backup_weekday = max(0, min(6, (int) ($data['weekday'] ?? 1)));
        $record->auto_backup_time = $this->normalizeTime($data['time'] ?? '02:00');

        return $record;
    }

    public function isDue(TenantInfrastructure $record): bool
    {
        if (! $record->auto_backup_enabled || ! $record->usesExternalStorage() || ! $record->usesExternalDatabase()) {
            return false;
        }

        if ($record->status !== TenantInfrastructure::STATUS_VERIFIED || $record->hasPendingBackupExport()) {
            return false;
        }

        $now = now();
        [$hour, $minute] = $this->parseTime($this->time($record));

        if ($now->hour !== $hour || $now->minute !== $minute) {
            return false;
        }

        if ($record->auto_backup_last_run_at?->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
            return false;
        }

        if ($this->frequency($record) === self::FREQUENCY_WEEKLY) {
            return $now->dayOfWeek === $this->weekday($record);
        }

        return true;
    }

    public function summary(TenantInfrastructure $record): string
    {
        if (! $record->auto_backup_enabled) {
            return 'Automatic database backups to your bucket are disabled.';
        }

        $time = $this->time($record);

        if ($this->frequency($record) === self::FREQUENCY_WEEKLY) {
            $weekday = collect($this->options()['weekdays'])
                ->firstWhere('value', $this->weekday($record))['label'] ?? 'Monday';

            return "Runs every {$weekday} at {$time}.";
        }

        return "Runs daily at {$time}.";
    }

    private function frequency(TenantInfrastructure $record): string
    {
        return $this->normalizeFrequency($record->auto_backup_frequency ?? self::FREQUENCY_DAILY);
    }

    private function weekday(TenantInfrastructure $record): int
    {
        return max(0, min(6, (int) ($record->auto_backup_weekday ?? 1)));
    }

    private function time(TenantInfrastructure $record): string
    {
        return $this->normalizeTime($record->auto_backup_time ?? '02:00');
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
