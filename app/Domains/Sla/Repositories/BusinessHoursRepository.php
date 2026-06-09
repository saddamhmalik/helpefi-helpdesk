<?php

namespace App\Domains\Sla\Repositories;

use App\Domains\Sla\Models\BusinessHours;

class BusinessHoursRepository
{
    public function default(): ?BusinessHours
    {
        return BusinessHours::query()->orderBy('id')->first();
    }

    public function find(int $id): BusinessHours
    {
        return BusinessHours::query()->findOrFail($id);
    }

    public function update(BusinessHours $hours, array $data): BusinessHours
    {
        $hours->update($data);

        return $hours->fresh();
    }
}
