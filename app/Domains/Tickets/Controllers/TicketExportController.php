<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Services\TicketExportService;
use App\Domains\Tickets\Services\TicketListExportService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketExportService $exportService,
        private TicketListExportService $listExportService,
        private TicketViewService $ticketViewService,
    ) {
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = TicketFilters::normalize($request->validate(TicketFilters::rules()));

        if ($request->filled('view_id')) {
            $view = $this->ticketViewService->findAccessible(
                $request->integer('view_id'),
                $request->user()->id,
            );
            $filters = TicketFilters::normalize(array_merge($view->filters ?? [], $filters));
        }

        return $this->listExportService->csv($filters, $request->user()->id);
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
