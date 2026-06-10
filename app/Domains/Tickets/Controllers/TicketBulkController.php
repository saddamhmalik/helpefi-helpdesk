<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Tickets\Services\TicketBulkService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketBulkController extends Controller
{
    public function __construct(
        private TicketBulkService $bulkService,
        private WorkforceService $workforceService,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['integer', 'distinct', 'exists:tickets,id'],
            'action' => ['required', 'string', 'in:assign,status,priority,close,snooze'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'ticket_status_id' => ['required_if:action,status', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required_if:action,priority', 'exists:ticket_priorities,id'],
            'minutes' => ['exclude_unless:action,snooze', 'required_without:until', 'integer', 'min:15', 'max:10080'],
            'until' => ['exclude_unless:action,snooze', 'required_without:minutes', 'date', 'after:now'],
        ]);

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
