<?php

namespace App\Domains\Search\Controllers\Api;

use App\Domains\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $search)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        return response()->json(
            $this->search->search(
                (string) ($validated['q'] ?? ''),
                (int) ($validated['limit'] ?? 5),
            ),
        );
    }
}
