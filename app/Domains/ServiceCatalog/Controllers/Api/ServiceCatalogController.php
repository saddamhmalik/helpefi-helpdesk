<?php

namespace App\Domains\ServiceCatalog\Controllers\Api;

use App\Domains\ServiceCatalog\Services\ServiceCatalogService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function __construct(
        private ServiceCatalogService $catalogService,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->catalogService->publicCatalog());
    }

    public function show(string $service): JsonResponse
    {
        return response()->json($this->catalogService->publicItem($service));
    }

    public function adminIndex(): JsonResponse
    {
        $reference = $this->ticketReferenceData->only(['priorities', 'agents']);

        return response()->json([
            'categories' => $this->catalogService->adminCatalog(),
            'meta' => $this->catalogService->meta(
                $reference['priorities'],
                $reference['agents'],
            ),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json(
            $this->catalogService->createCategory($request->all()),
            201
        );
    }

    public function updateCategory(Request $request, int $category): JsonResponse
    {
        return response()->json(
            $this->catalogService->updateCategory($category, $request->all())
        );
    }

    public function destroyCategory(int $category): JsonResponse
    {
        return response()->json(
            $this->catalogService->deleteCategory($category)
        );
    }

    public function storeItem(Request $request): JsonResponse
    {
        return response()->json(
            $this->catalogService->createItem($request->all()),
            201
        );
    }

    public function updateItem(Request $request, int $item): JsonResponse
    {
        return response()->json(
            $this->catalogService->updateItem($item, $request->all())
        );
    }

    public function destroyItem(int $item): JsonResponse
    {
        return response()->json(
            $this->catalogService->deleteItem($item)
        );
    }

    public function submit(Request $request, string $service): JsonResponse
    {
        $ticket = $this->catalogService->submitRequest($service, $request->all(), $request->user());

        return response()->json($ticket, 201);
    }
}
