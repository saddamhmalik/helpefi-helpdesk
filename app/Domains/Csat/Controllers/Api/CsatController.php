<?php

namespace App\Domains\Csat\Controllers\Api;

use App\Domains\Csat\Services\CsatService;
use App\Domains\Knowledge\Services\PortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsatController extends Controller
{
    public function __construct(
        private CsatService $csat,
        private PortalService $portal,
    ) {
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->csat->settingsSnapshot());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'comment_required' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
        ]);

        return response()->json($this->csat->updateSettings($data));
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin') || $request->user()?->hasRole('agent'), 403);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return response()->json($this->csat->report($filters));
    }

    public function submitPortal(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticketModel = $this->portal->customerTicket($request->user(), $ticket);

        return response()->json(
            $this->csat->submit($ticketModel, $request->user()->contact, $data['rating'], $data['comment'] ?? null),
            201,
        );
    }
}
