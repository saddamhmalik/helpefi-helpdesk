<?php

namespace App\Domains\Knowledge\Controllers\Api;

use App\Domains\Knowledge\Services\PortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct(private PortalService $portalService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->portalService->home());
    }

    public function collection(\App\Domains\Brands\Models\Brand $brand, string $collectionSlug): JsonResponse
    {
        return response()->json($this->portalService->collection($collectionSlug));
    }

    public function article(\App\Domains\Brands\Models\Brand $brand, string $articleSlug): JsonResponse
    {
        return response()->json($this->portalService->article($articleSlug));
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json($this->portalService->search($request->query('q')));
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $ticket = $this->portalService->submitTicket($data);

        return response()->json([
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
        ], 201);
    }

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $ticket = $this->portalService->trackTicket($data['number'], $data['email']);

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json($this->portalService->publicTrackedTicket($ticket));
    }
}
