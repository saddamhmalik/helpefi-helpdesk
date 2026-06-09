<?php

namespace App\Domains\Knowledge\Controllers\Api;

use App\Domains\Knowledge\Services\KbDeflectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KbDeflectionController extends Controller
{
    public function __construct(private KbDeflectionService $deflection)
    {
    }

    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        return response()->json($this->deflection->suggest(
            $data['subject'],
            $data['description'] ?? null,
            $data['session_id'] ?? null,
        ));
    }

    public function articleClick(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'article_id' => ['required', 'integer', 'exists:knowledge_articles,id'],
            'query' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->deflection->recordArticleClick(
            $data['session_id'],
            (int) $data['article_id'],
            $data['query'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function deflect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'article_id' => ['nullable', 'integer', 'exists:knowledge_articles,id'],
            'query' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->deflection->recordDeflected(
            $data['session_id'],
            isset($data['article_id']) ? (int) $data['article_id'] : null,
            $data['query'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function continue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'query' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->deflection->recordContinued(
            $data['session_id'],
            $data['query'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return response()->json($this->deflection->summary($filters));
    }
}
