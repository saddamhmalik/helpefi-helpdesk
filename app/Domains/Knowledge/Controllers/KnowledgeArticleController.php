<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Knowledge\Services\KnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeArticleController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Knowledge/Index', [
            'articles' => $this->knowledgeService->list(),
            'collections' => $this->knowledgeService->collections(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Knowledge/Create', [
            'categories' => $this->knowledgeService->categories(),
            'collections' => $this->knowledgeService->collections(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'knowledge_category_id' => ['nullable', 'exists:knowledge_categories,id'],
            'knowledge_collection_id' => ['nullable', 'exists:knowledge_collections,id'],
            'is_published' => ['boolean'],
        ]);

        $article = $this->knowledgeService->create($data, $request->user()->id);

        return redirect()->route('knowledge.show', $article)->with('success', 'Article created.');
    }

    public function show(int $article): Response
    {
        return Inertia::render('Knowledge/Show', [
            'article' => $this->knowledgeService->show($article),
            'categories' => $this->knowledgeService->categories(),
            'collections' => $this->knowledgeService->collections(),
            'versions' => $this->knowledgeService->versions($article),
        ]);
    }

    public function update(Request $request, int $article): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'knowledge_category_id' => ['nullable', 'exists:knowledge_categories,id'],
            'knowledge_collection_id' => ['nullable', 'exists:knowledge_collections,id'],
            'is_published' => ['boolean'],
        ]);

        $this->knowledgeService->update($article, $data, $request->user()->id);

        return back()->with('success', 'Article updated.');
    }

    public function restoreVersion(Request $request, int $article, int $version): RedirectResponse
    {
        $this->knowledgeService->restoreVersion($article, $version, $request->user()->id);

        return back()->with('success', 'Version restored.');
    }
}
