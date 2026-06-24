<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Requests\StoreTicketStatusRequest;
use App\Domains\Tickets\Requests\UpdateTicketStatusRequest;
use App\Domains\Tickets\Services\TicketStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TicketStatusController extends Controller
{
    public function __construct(private TicketStatusService $statuses)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/TicketStatuses', [
            'statuses' => $this->statuses->all(),
        ]);
    }

    public function store(StoreTicketStatusRequest $request): RedirectResponse
    {
        try {
            $this->statuses->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Status created.');
    }

    public function update(UpdateTicketStatusRequest $request, int $status): RedirectResponse
    {
        try {
            $this->statuses->update($status, $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Status updated.');
    }

    public function destroy(int $status): RedirectResponse
    {
        try {
            $this->statuses->delete($status);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'Status deleted.');
    }
}
