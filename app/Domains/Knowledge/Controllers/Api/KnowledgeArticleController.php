<?php

namespace App\Domains\Knowledge\Controllers\Api;

use App\Domains\Knowledge\Services\KnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeArticleController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->knowledgeService->list());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'knowledge_category_id' => ['nullable', 'exists:knowledge_categories,id'],
            'knowledge_collection_id' => ['nullable', 'exists:knowledge_collections,id'],
            'is_published' => ['boolean'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        return response()->json(
            $this->knowledgeService->create($data, $request->user()->id),
            201
        );
    }

    public function show(int $article): JsonResponse
    {
        return response()->json([
            'article' => $this->knowledgeService->show($article),
            'translations' => $this->knowledgeService->translations($article),
        ]);
    }

    public function update(Request $request, int $article): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'knowledge_category_id' => ['nullable', 'exists:knowledge_categories,id'],
            'knowledge_collection_id' => ['nullable', 'exists:knowledge_collections,id'],
            'is_published' => ['boolean'],
        ]);

        return response()->json(
            $this->knowledgeService->update($article, $data, $request->user()->id)
        );
    }

    public function versions(int $article): JsonResponse
    {
        return response()->json($this->knowledgeService->versions($article));
    }

    public function restoreVersion(Request $request, int $article, int $version): JsonResponse
    {
        return response()->json(
            $this->knowledgeService->restoreVersion($article, $version, $request->user()->id)
        );
    }
}
