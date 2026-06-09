<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Assets\Services\AssetService;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Contacts\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function __construct(
        private AssetService $assetService,
        private ContactService $contactService,
        private OrganizationService $organizationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search',
            'status',
            'asset_type_id',
            'organization_id',
            'unassigned',
            'warranty_expiring',
        ]);

        return Inertia::render('Assets/Index', [
            'assets' => $this->assetService->list($filters),
            'stats' => $this->assetService->stats(),
            'meta' => $this->assetService->meta(),
            'organizations' => $this->organizationService->options(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Assets/Create', [
            'meta' => $this->assetService->meta(),
            'contacts' => $this->contactService->options(),
            'organizations' => $this->organizationService->options(),
            'parentOptions' => $this->assetService->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = $this->assetService->create($this->validatedAsset($request));

        return redirect()->route('assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(int $asset): Response
    {
        return Inertia::render('Assets/Show', [
            'asset' => $this->assetService->show($asset),
            'meta' => $this->assetService->meta(),
            'contacts' => $this->contactService->options(),
            'organizations' => $this->organizationService->options(),
            'parentOptions' => $this->assetService->options(),
        ]);
    }

    public function update(Request $request, int $asset): RedirectResponse
    {
        $this->assetService->update($asset, $this->validatedAsset($request));

        return back()->with('success', 'Asset updated.');
    }

    public function destroy(int $asset): RedirectResponse
    {
        $this->assetService->delete($asset);

        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }

    public function attachTicket(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
        ]);

        $this->assetService->attachToTicket($data['asset_id'], $ticket);

        return back()->with('success', 'Asset linked to ticket.');
    }

    public function detachTicket(int $ticket, int $asset): RedirectResponse
    {
        $this->assetService->detachFromTicket($asset, $ticket);

        return back()->with('success', 'Asset unlinked from ticket.');
    }

    private function validatedAsset(Request $request): array
    {
        return $request->validate([
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'parent_id' => ['nullable', 'exists:assets,id'],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:in_use,in_stock,maintenance,retired'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string', 'max:17'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
