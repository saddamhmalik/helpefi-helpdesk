<?php

namespace App\Domains\Sla\Services;

use App\Domains\Sla\Models\BusinessHours;
use App\Domains\Sla\Repositories\BusinessHoursRepository;
use App\Domains\Security\Support\AuditRecorder;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BusinessHoursService
{
    public const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function __construct(
        private BusinessHoursRepository $hours,
        private AuditRecorder $audit,
    ) {
    }

    public function default(): BusinessHours
    {
        return $this->hours->default()
            ?? throw new InvalidArgumentException('Business hours are not configured.');
    }

    public function snapshot(): array
    {
        return $this->snapshotFrom($this->default());
    }

    public function optionalSnapshot(): ?array
    {
        $hours = $this->hours->default();

        return $hours ? $this->snapshotFrom($hours) : null;
    }

    public function updateValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'schedule' => ['required', 'array'],
            'schedule.mon' => ['nullable', 'array'],
            'schedule.tue' => ['nullable', 'array'],
            'schedule.wed' => ['nullable', 'array'],
            'schedule.thu' => ['nullable', 'array'],
            'schedule.fri' => ['nullable', 'array'],
            'schedule.sat' => ['nullable', 'array'],
            'schedule.sun' => ['nullable', 'array'],
            'schedule.*.start' => ['nullable', 'date_format:H:i'],
            'schedule.*.end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function update(int $id, array $data): array
    {
        $hours = $this->hours->find($id);
        $schedule = $this->normalizeSchedule($data['schedule'] ?? []);
        $this->assertValidSchedule($schedule);

        $before = $hours->only(['name', 'timezone', 'schedule']);
        $hours = $this->hours->update($hours, [
            'name' => trim($data['name']),
            'timezone' => $data['timezone'],
            'schedule' => $schedule,
        ]);

        $this->audit->recordChanges('business_hours.updated', $hours, $before, $hours->only(['name', 'timezone', 'schedule']));

        return $this->snapshotFrom($hours);
    }

    public function timezoneOptions(): array
    {
        $grouped = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $timezone) {
            if (! str_contains($timezone, '/')) {
                continue;
            }

            [$region] = explode('/', $timezone, 2);
            $grouped[$region][] = [
                'value' => $timezone,
                'label' => str_replace('_', ' ', substr($timezone, strlen($region) + 1)),
            ];
        }

        ksort($grouped);

        return collect($grouped)
            ->map(fn (array $options, string $region) => [
                'region' => $region,
                'options' => $options,
            ])
            ->values()
            ->all();
    }

    public function weekdayMeta(): array
    {
        return [
            ['key' => 'mon', 'label' => 'Monday'],
            ['key' => 'tue', 'label' => 'Tuesday'],
            ['key' => 'wed', 'label' => 'Wednesday'],
            ['key' => 'thu', 'label' => 'Thursday'],
            ['key' => 'fri', 'label' => 'Friday'],
            ['key' => 'sat', 'label' => 'Saturday'],
            ['key' => 'sun', 'label' => 'Sunday'],
        ];
    }

    public function snapshotFrom(BusinessHours $hours): array
    {
        return [
            'id' => $hours->id,
            'name' => $hours->name,
            'timezone' => $hours->timezone,
            'schedule' => $this->normalizeSchedule($hours->schedule ?? []),
        ];
    }

    private function normalizeSchedule(array $schedule): array
    {
        $normalized = [];

        foreach (self::DAY_KEYS as $day) {
            $window = $schedule[$day] ?? null;

            if (! is_array($window) || empty($window['start']) || empty($window['end'])) {
                $normalized[$day] = null;

                continue;
            }

            $normalized[$day] = [
                'start' => substr((string) $window['start'], 0, 5),
                'end' => substr((string) $window['end'], 0, 5),
            ];
        }

        return $normalized;
    }

    private function assertValidSchedule(array $schedule): void
    {
        foreach (self::DAY_KEYS as $day) {
            $window = $schedule[$day] ?? null;

            if ($window === null) {
                continue;
            }

            if ($window['start'] >= $window['end']) {
                throw ValidationException::withMessages([
                    "schedule.{$day}.end" => 'End time must be after start time.',
                ]);
            }
        }
    }
}
