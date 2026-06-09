<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Services\TicketExportService;
use App\Domains\Tickets\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketExportController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketExportService $exportService,
    ) {
    }

    public function pdf(Request $request, int $ticket): Response
    {
        $ticketModel = $this->ticketService->show($ticket);
        $includeConversation = $request->boolean('conversation', true);

        return $this->exportService
            ->pdf($ticket, $includeConversation)
            ->download($this->exportService->filename($ticketModel));
    }

    public function email(Request $request, int $ticket): RedirectResponse
    {
        $this->ticketService->show($ticket);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'include_conversation' => ['boolean'],
        ]);

        $this->exportService->queueEmail(
            $ticket,
            $data['email'],
            $request->user()->id,
            $data['include_conversation'] ?? true,
        );

        return back()->with('success', 'Ticket export queued for delivery.');
    }
}
