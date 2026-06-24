<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Requests\BulkTicketActionRequest;
use App\Domains\Tickets\Services\TicketBulkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TicketBulkController extends Controller
{
    public function __construct(private TicketBulkService $bulkService)
    {
    }

    public function store(BulkTicketActionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $result = $this->bulkService->execute(
            $data['ticket_ids'],
            $data['action'],
            $data,
            $request->user()->id,
        );

        $message = sprintf('Updated %d ticket(s).', $result['updated']);

        if ($result['failed'] !== []) {
            $message .= sprintf(' %d failed.', count($result['failed']));
        }

        return back()->with('success', $message);
    }
}
