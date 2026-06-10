<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Knowledge\Services\KnowledgeService;
use App\Domains\Knowledge\Services\KnowledgeSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeArticleController extends Controller
{
    public function __construct(
        private KnowledgeService $knowledgeService,
        private KnowledgeSettingService $knowledgeSettings,
    ) {
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
            'locales' => $this->knowledgeSettings->localeOptions(),
            'defaultLocale' => $this->knowledgeSettings->defaultLocale(),
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
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $article = $this->knowledgeService->create($data, $request->user()->id);

        return redirect()->route('knowledge.edit', $article)->with('success', 'Article created.');
    }

    public function show(int $article): Response
    {
        return Inertia::render('Knowledge/Show', [
            'article' => $this->knowledgeService->show($article),
            'versions' => $this->knowledgeService->versions($article),
            'translations' => $this->knowledgeService->translations($article),
        ]);
    }

    public function edit(int $article): Response
    {
        return Inertia::render('Knowledge/Edit', [
            'article' => $this->knowledgeService->show($article),
            'categories' => $this->knowledgeService->categories(),
            'collections' => $this->knowledgeService->collections(),
            'versions' => $this->knowledgeService->versions($article),
            'translations' => $this->knowledgeService->translations($article),
            'locales' => $this->knowledgeSettings->localeOptions(),
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

    public function storeTranslation(Request $request, int $article): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        $translation = $this->knowledgeService->createTranslation(
            $article,
            $data['locale'],
            $data,
            $request->user()->id,
        );

        return redirect()->route('knowledge.edit', $translation)->with('success', 'Translation created.');
    }

    public function restoreVersion(Request $request, int $article, int $version): RedirectResponse
    {
        $this->knowledgeService->restoreVersion($article, $version, $request->user()->id);

        return back()->with('success', 'Version restored.');
    }
}
