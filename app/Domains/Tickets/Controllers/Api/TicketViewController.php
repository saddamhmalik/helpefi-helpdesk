<?php

namespace App\Domains\Tickets\Controllers\Api;

use App\Domains\Tickets\Models\TicketView;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketViewController extends Controller
{
    public function __construct(private TicketViewService $ticketViewService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->ticketViewService->forUser($request->user()->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateView($request);

        $view = $this->ticketViewService->create(
            $request->user(),
            $data['name'],
            TicketFilters::normalize($data['filters'] ?? []),
            $data['is_default'] ?? false,
            $data['visibility'],
            $data['team_id'] ?? null,
        );

        return response()->json($view->load(['user:id,name', 'team:id,name']), 201);
    }

    public function destroy(Request $request, int $view): JsonResponse
    {
        $this->ticketViewService->delete($view, $request->user()->id);

        return response()->json(['message' => 'View deleted.']);
    }

    private function validateView(Request $request): array
    {
        $filterRules = collect(TicketFilters::rules())
            ->mapWithKeys(fn (array $rules, string $key) => ["filters.{$key}" => $rules])
            ->all();

        return $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'is_default' => ['boolean'],
            'visibility' => ['required', Rule::in([
                TicketView::VISIBILITY_PRIVATE,
                TicketView::VISIBILITY_TEAM,
            ])],
            'team_id' => ['nullable', 'required_if:visibility,team', 'integer', 'exists:teams,id'],
        ], $filterRules));
    }
}
