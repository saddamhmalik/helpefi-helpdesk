<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Requests\StoreTicketViewRequest;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketViewController extends Controller
{
    public function __construct(private TicketViewService $ticketViewService)
    {
    }

    public function store(StoreTicketViewRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->ticketViewService->create(
            $request->user(),
            $data['name'],
            TicketFilters::normalize($data['filters'] ?? []),
            $data['is_default'] ?? false,
            $data['visibility'],
            $data['team_id'] ?? null,
        );

        return back()->with('success', 'View saved.');
    }

    public function destroy(Request $request, int $view): RedirectResponse
    {
        $this->ticketViewService->delete($view, $request->user()->id);

        return back()->with('success', 'View deleted.');
    }
}
