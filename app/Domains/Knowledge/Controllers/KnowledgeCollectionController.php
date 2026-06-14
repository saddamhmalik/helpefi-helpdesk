<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Knowledge\Services\KnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class KnowledgeCollectionController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Knowledge/Collections', [
            'collections' => $this->knowledgeService->collections(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'],
        ]);

        $this->knowledgeService->createCollection($data);

        return back()->with('success', 'Collection created.');
    }

    public function update(Request $request, int $collection): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'],
        ]);

        $this->knowledgeService->updateCollection($collection, $data);

        return back()->with('success', 'Collection updated.');
    }

    public function destroy(int $collection): RedirectResponse
    {
        try {
            $this->knowledgeService->deleteCollection($collection);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['collection' => $exception->getMessage()]);
        }

        return back()->with('success', 'Collection deleted.');
    }
}
