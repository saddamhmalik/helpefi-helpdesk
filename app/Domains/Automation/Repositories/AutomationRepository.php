<?php

namespace App\Domains\Automation\Repositories;

use App\Domains\Automation\Models\AutomationRule;
use Illuminate\Database\Eloquent\Collection;

class AutomationRepository
{
    public function all(): Collection
    {
        return AutomationRule::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function activeForTrigger(string $trigger): Collection
    {
        return AutomationRule::query()
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): AutomationRule
    {
        return AutomationRule::query()->findOrFail($id);
    }

    public function create(array $data): AutomationRule
    {
        return AutomationRule::query()->create($data);
    }

    public function update(AutomationRule $rule, array $data): AutomationRule
    {
        $rule->update($data);

        return $rule->fresh();
    }

    public function delete(AutomationRule $rule): void
    {
        $rule->delete();
    }
}
