<?php

namespace App\Domains\Sla\Repositories;

use App\Domains\Sla\Models\BusinessHours;

class BusinessHoursRepository
{
    private static ?BusinessHours $default = null;

    public function default(): ?BusinessHours
    {
        return self::$default ??= BusinessHours::query()->orderBy('id')->first();
    }

    public function find(int $id): BusinessHours
    {
        return BusinessHours::query()->findOrFail($id);
    }

    public function update(BusinessHours $hours, array $data): BusinessHours
    {
        $hours->update($data);
        self::$default = null;

        return $hours->fresh();
    }
}
