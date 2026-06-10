<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformFeedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformFeedbackRepository
{
    public function create(array $data): PlatformFeedback
    {
        return PlatformFeedback::query()->create($data);
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): PlatformFeedback
    {
        return PlatformFeedback::query()
            ->with('tenant:id,name,slug')
            ->findOrFail($id);
    }

    public function update(PlatformFeedback $feedback, array $data): PlatformFeedback
    {
        $feedback->update($data);

        return $feedback->fresh();
    }

    public function summary(): array
    {
        return PlatformFeedback::query()
            ->selectRaw('type, status, COUNT(*) as total')
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->all())
            ->all();
    }

    private function filteredQuery(array $filters)
    {
        return PlatformFeedback::query()
            ->with('tenant:id,name,slug')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['tenant_id'] ?? null, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('subject', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('user_name', 'like', "%{$search}%")
                        ->orWhere('user_email', 'like', "%{$search}%")
                        ->orWhere('tenant_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }
}
