<?php

namespace App\Domains\Macros\Repositories;

use App\Domains\Macros\Models\CannedResponse;
use Illuminate\Database\Eloquent\Collection;

class CannedResponseRepository
{
    public function forUser(int $userId): Collection
    {
        return CannedResponse::query()
            ->with('user:id,name')
            ->where(fn ($query) => $query
                ->where('user_id', $userId)
                ->orWhere('is_shared', true))
            ->orderBy('title')
            ->get();
    }

    public function search(int $userId, ?string $term, int $limit = 12): Collection
    {
        return CannedResponse::query()
            ->where(fn ($query) => $query
                ->where('user_id', $userId)
                ->orWhere('is_shared', true))
            ->when($term, function ($query, $term) {
                $like = '%'.$term.'%';

                return $query->where(fn ($inner) => $inner
                    ->where('title', 'like', $like)
                    ->orWhere('shortcut', 'like', $like)
                    ->orWhere('body', 'like', $like));
            })
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'shortcut', 'body', 'is_shared', 'user_id']);
    }

    public function find(int $id): CannedResponse
    {
        return CannedResponse::query()->findOrFail($id);
    }

    public function create(array $data): CannedResponse
    {
        return CannedResponse::query()->create($data);
    }

    public function update(CannedResponse $response, array $data): CannedResponse
    {
        $response->update($data);

        return $response->fresh();
    }

    public function delete(CannedResponse $response): void
    {
        $response->delete();
    }
}
