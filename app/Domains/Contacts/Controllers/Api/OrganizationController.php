<?php

namespace App\Domains\Contacts\Controllers\Api;

use App\Domains\Contacts\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $organizationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->organizationService->list((int) $request->integer('per_page', 15))
        );
    }

    public function show(int $organization): JsonResponse
    {
        return response()->json($this->organizationService->show($organization));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedOrganization($request);
        $domains = $data['domains'] ?? [];
        unset($data['domains']);

        $organization = $this->organizationService->create($data, $domains);

        return response()->json($this->organizationService->show($organization->id), 201);
    }

    public function update(Request $request, int $organization): JsonResponse
    {
        $data = $this->validatedOrganization($request);
        $domains = $data['domains'] ?? [];
        unset($data['domains']);

        $this->organizationService->update($organization, $data, $domains);

        return response()->json($this->organizationService->show($organization));
    }

    public function destroy(int $organization): JsonResponse
    {
        $this->organizationService->delete($organization);

        return response()->json(['message' => 'Organization deleted.']);
    }

    private function validatedOrganization(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'domains' => ['array'],
            'domains.*' => ['string', 'max:255'],
        ]);
    }
}
