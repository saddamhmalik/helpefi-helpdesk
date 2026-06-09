<?php

namespace App\Domains\Knowledge\Controllers\Api;

use App\Domains\Knowledge\Services\KnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeCollectionController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->knowledgeService->collections());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'],
        ]);

        return response()->json($this->knowledgeService->createCollection($data), 201);
    }

    public function update(Request $request, int $collection): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'],
        ]);

        return response()->json($this->knowledgeService->updateCollection($collection, $data));
    }

    public function destroy(int $collection): JsonResponse
    {
        $this->knowledgeService->deleteCollection($collection);

        return response()->json(['message' => 'Collection deleted.']);
    }
}
