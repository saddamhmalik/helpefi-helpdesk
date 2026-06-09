<?php

namespace App\Domains\Assets\Controllers\Api;

use App\Domains\Assets\Services\AssetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private AssetService $assetService)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json($this->assetService->meta());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->assetService->list($request->only(['search', 'status', 'asset_type_id']))
        );
    }

    public function show(int $asset): JsonResponse
    {
        return response()->json($this->assetService->show($asset));
    }

    public function store(Request $request): JsonResponse
    {
        $asset = $this->assetService->create($this->validatedAsset($request));

        return response()->json($asset, 201);
    }

    public function update(Request $request, int $asset): JsonResponse
    {
        return response()->json(
            $this->assetService->update($asset, $this->validatedAsset($request))
        );
    }

    public function destroy(int $asset): JsonResponse
    {
        $this->assetService->delete($asset);

        return response()->json(['message' => 'Asset deleted.']);
    }

    public function attachTicket(Request $request, int $asset): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'exists:tickets,id'],
        ]);

        return response()->json(
            $this->assetService->attachToTicket($asset, $data['ticket_id'])
        );
    }

    public function detachTicket(int $asset, int $ticket): JsonResponse
    {
        return response()->json(
            $this->assetService->detachFromTicket($asset, $ticket)
        );
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
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
