<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Services\ProblemRecordService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProblemController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ProblemRecordService $problems,
    ) {
    }

    public function update(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();

        $this->problems->update($ticket, $request->validate([
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'workaround' => ['nullable', 'string', 'max:10000'],
            'is_known_error' => ['boolean'],
        ]));

        return back()->with('success', 'Problem details updated.');
    }

    public function linkIncident(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();

        $data = $request->validate([
            'incident_ticket_id' => ['required', 'integer', 'exists:tickets,id'],
        ]);

        $this->problems->linkIncident($ticket, (int) $data['incident_ticket_id'], $request->user()->id);

        return back()->with('success', 'Incident linked.');
    }

    public function unlinkIncident(int $ticket, int $incident): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $this->problems->unlinkIncident($ticket, $incident);

        return back()->with('success', 'Incident unlinked.');
    }
}
