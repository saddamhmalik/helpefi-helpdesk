<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $organizationService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Organizations/Index', [
            'organizations' => $this->organizationService->list(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Organizations/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'customer_tier' => ['nullable', 'string', 'in:'.implode(',', array_column(config('customer_tiers', []), 'value'))],
            'domains' => ['array'],
            'domains.*' => ['string', 'max:255'],
        ]);

        $domains = $data['domains'] ?? [];
        unset($data['domains']);

        $organization = $this->organizationService->create($data, $domains);

        return redirect()->route('organizations.show', $organization)->with('success', 'Organization created.');
    }

    public function show(int $organization): Response
    {
        return Inertia::render('Organizations/Show', [
            'organization' => $this->organizationService->show($organization),
        ]);
    }

    public function update(Request $request, int $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'customer_tier' => ['nullable', 'string', 'in:'.implode(',', array_column(config('customer_tiers', []), 'value'))],
            'domains' => ['array'],
            'domains.*' => ['string', 'max:255'],
        ]);

        $domains = $data['domains'] ?? [];
        unset($data['domains']);

        $this->organizationService->update($organization, $data, $domains);

        return back()->with('success', 'Organization updated.');
    }

    public function destroy(int $organization): RedirectResponse
    {
        $this->organizationService->delete($organization);

        return redirect()->route('organizations.index')->with('success', 'Organization deleted.');
    }
}
