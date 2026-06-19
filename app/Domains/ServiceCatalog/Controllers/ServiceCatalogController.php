<?php

namespace App\Domains\ServiceCatalog\Controllers;

use App\Domains\Billing\Services\BillingService;
use App\Domains\ServiceCatalog\Services\ServiceCatalogService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    public function __construct(
        private ServiceCatalogService $catalogService,
        private TicketFormReferenceService $ticketReferenceData,
        private BillingService $billing,
    ) {
    }

    public function index(): Response
    {
        $reference = $this->ticketReferenceData->only(['priorities', 'agents']);

        return Inertia::render('Settings/ServiceCatalog', [
            'categories' => $this->catalogService->adminCatalog(),
            'meta' => array_merge(
                $this->catalogService->meta(
                    collect($reference['priorities']),
                    collect($reference['agents']),
                ),
                ['service_desk_available' => $this->billing->canUseFeature('service_desk')],
            ),
            'agents' => $reference['agents'],
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->catalogService->createCategory($request->all());

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, int $category): RedirectResponse
    {
        $this->catalogService->updateCategory($category, $request->all());

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(int $category): RedirectResponse
    {
        $this->catalogService->deleteCategory($category);

        return back()->with('success', 'Category deleted.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->catalogService->createItem($request->all());

        return back()->with('success', 'Service item created.');
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        $this->catalogService->updateItem($item, $request->all());

        return back()->with('success', 'Service item updated.');
    }

    public function destroyItem(int $item): RedirectResponse
    {
        $this->catalogService->deleteItem($item);

        return back()->with('success', 'Service item deleted.');
    }
}
