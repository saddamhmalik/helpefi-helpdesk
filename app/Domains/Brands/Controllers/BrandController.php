<?php

namespace App\Domains\Brands\Controllers;

use App\Domains\Brands\Services\BrandService;
use App\Domains\Tickets\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BrandController extends Controller
{
    public function __construct(
        private BrandService $brands,
        private TicketService $tickets,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Brands', [
            'brands' => $this->brands->listForSettings(),
            'priorities' => $this->tickets->priorities(),
            'collections' => \App\Domains\Knowledge\Models\KnowledgeCollection::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'brand_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->brands->create($request->all(), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['brand' => $exception->getMessage()]);
        }

        return back()->with('success', 'Brand created.');
    }

    public function update(Request $request, int $brand): RedirectResponse
    {
        try {
            $this->brands->update($brand, $request->all(), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['brand' => $exception->getMessage()]);
        }

        if ($request->filled('collection_ids')) {
            \App\Domains\Knowledge\Models\KnowledgeCollection::query()
                ->whereIn('id', $request->input('collection_ids', []))
                ->update(['brand_id' => $brand]);
        }

        return back()->with('success', 'Brand saved.');
    }

    public function destroy(int $brand): RedirectResponse
    {
        try {
            $this->brands->delete($brand, request()->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['brand' => $exception->getMessage()]);
        }

        return back()->with('success', 'Brand deleted.');
    }
}
