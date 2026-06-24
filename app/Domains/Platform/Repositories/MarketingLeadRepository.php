<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingLead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MarketingLeadRepository
{
    public function create(array $attributes): MarketingLead
    {
        return MarketingLead::query()->create($attributes);
    }

    public function find(int $id): ?MarketingLead
    {
        return MarketingLead::query()->find($id);
    }

    public function findLatestByEmail(string $email): ?MarketingLead
    {
        return MarketingLead::query()
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();
    }

    public function update(MarketingLead $lead, array $attributes): MarketingLead
    {
        $lead->fill($attributes);
        $lead->save();

        return $lead;
    }

    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        $base = MarketingLead::query();

        return [
            'total' => (clone $base)->count(),
            'new' => (clone $base)->where('status', MarketingLead::STATUS_NEW)->count(),
            'contacted' => (clone $base)->where('status', MarketingLead::STATUS_CONTACTED)->count(),
            'qualified' => (clone $base)->where('status', MarketingLead::STATUS_QUALIFIED)->count(),
            'with_consent' => (clone $base)->whereNotNull('marketing_consent_at')->count(),
            'last_7_days' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = MarketingLead::query();

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($source = trim((string) ($filters['source'] ?? ''))) {
            $query->where('source', $source);
        }

        if ($intent = trim((string) ($filters['intent'] ?? ''))) {
            $query->where('intent', $intent);
        }

        if ($status = trim((string) ($filters['status'] ?? ''))) {
            $query->where('status', $status);
        }

        if (($filters['consent'] ?? '') === 'yes') {
            $query->whereNotNull('marketing_consent_at');
        }

        if (($filters['consent'] ?? '') === 'no') {
            $query->whereNull('marketing_consent_at');
        }

        return $query;
    }
}
