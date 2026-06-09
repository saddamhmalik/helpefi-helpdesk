<?php

namespace App\Domains\ServiceCatalog\Controllers;

use App\Domains\Brands\Models\Brand;
use App\Domains\ServiceCatalog\Services\ServiceCatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalServiceCatalogController extends Controller
{
    public function __construct(private ServiceCatalogService $catalogService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Portal/Services', [
            'categories' => $this->catalogService->publicCatalog(),
        ]);
    }

    public function show(string $service): Response
    {
        return Inertia::render('Portal/ServiceRequest', [
            'service' => $this->catalogService->publicItem($service),
            'customer' => request()->user()?->hasRole('customer') ? [
                'name' => request()->user()->name,
                'email' => request()->user()->email,
            ] : null,
        ]);
    }

    public function submit(Request $request, Brand $brand, string $service): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->catalogService->submitRequest($service, $request->all(), $user);

        if ($user?->hasRole('customer')) {
            return redirect()
                ->route('portal.my-tickets.show', ['brand' => $brand, 'ticket' => $ticket->id])
                ->with('success', 'Your service request was submitted.');
        }

        return redirect()
            ->route('portal.track', [
                'brand' => $brand,
                'number' => $ticket->number,
                'email' => $request->input('email'),
            ])
            ->with('success', 'Your service request was submitted.');
    }
}
