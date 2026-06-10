<?php

namespace App\Domains\Tickets\Controllers\Api;

use App\Domains\Tickets\Services\TicketStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TicketStatusController extends Controller
{
    public function __construct(private TicketStatusService $statuses)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->statuses->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_closed' => ['boolean'],
        ]);

        try {
            $status = $this->statuses->create($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return response()->json($status, 201);
    }

    public function update(Request $request, int $status): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_closed' => ['boolean'],
        ]);

        try {
            $updated = $this->statuses->update($status, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return response()->json($updated);
    }

    public function destroy(Request $request, int $status): JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            $this->statuses->delete($status);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Status deleted.']);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
