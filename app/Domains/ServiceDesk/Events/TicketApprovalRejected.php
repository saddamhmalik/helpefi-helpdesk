<?php

namespace App\Domains\ServiceDesk\Events;

use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketApprovalRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ApprovalRequest $approvalRequest,
        public array $context = [],
    ) {
    }
}
