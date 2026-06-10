<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Services\TicketStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_closed' => ['boolean'],
        ]);

        try {
            $this->statuses->create($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Status created.');
    }

    public function update(Request $request, int $status): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_closed' => ['boolean'],
        ]);

        try {
            $this->statuses->update($status, $data);
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
