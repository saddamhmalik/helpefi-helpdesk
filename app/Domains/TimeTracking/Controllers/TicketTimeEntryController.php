<?php

namespace App\Domains\TimeTracking\Controllers;

use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketTimeEntryController extends Controller
{
    public function __construct(private TimeTrackingService $timeTracking)
    {
    }

    public function store(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string', 'max:1000'],
            'logged_at' => ['nullable', 'date'],
        ]);

        try {
            $this->timeTracking->log(
                $ticket,
                $request->user()->id,
                (int) $data['minutes'],
                $data['note'] ?? null,
                $data['logged_at'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['minutes' => $exception->getMessage()]);
        }

        return back()->with('success', 'Time logged.');
    }

    public function destroy(Request $request, int $ticket, int $entry): RedirectResponse
    {
        try {
            $this->timeTracking->delete(
                $ticket,
                $entry,
                $request->user()->id,
                $request->user()->hasRole('admin'),
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return back()->withErrors(['time' => $exception->getMessage()]);
        }

        return back()->with('success', 'Time entry removed.');
    }
}
