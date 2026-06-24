<?php

namespace App\Domains\Automation\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\ServiceDesk\Events\TicketApprovalApproved;
use App\Domains\ServiceDesk\Events\TicketApprovalRejected;
use App\Domains\Tickets\Events\TicketCreated;
use App\Domains\Tickets\Events\TicketCustomerMessageReceived;
use App\Domains\Tickets\Events\TicketUpdated;

class BridgeTicketLifecycleToAutomation
{
    public function handleCreated(TicketCreated $event): void
    {
        TicketAutomationTrigger::dispatch($event->ticket, AutomationRule::TRIGGER_TICKET_CREATED, $event->context);
    }

    public function handleUpdated(TicketUpdated $event): void
    {
        TicketAutomationTrigger::dispatch($event->ticket, AutomationRule::TRIGGER_TICKET_UPDATED, $event->context);
    }

    public function handleCustomerMessage(TicketCustomerMessageReceived $event): void
    {
        TicketAutomationTrigger::dispatch(
            $event->ticket,
            AutomationRule::TRIGGER_CUSTOMER_MESSAGE,
            array_merge($event->context, [
                'message_body' => strip_tags($event->message->body),
            ]),
        );
    }

    public function handleApprovalApproved(TicketApprovalApproved $event): void
    {
        TicketAutomationTrigger::dispatch(
            $event->ticket,
            AutomationRule::TRIGGER_APPROVAL_APPROVED,
            array_merge($event->context, [
                'approval_request_id' => $event->approvalRequest->id,
            ]),
        );
    }

    public function handleApprovalRejected(TicketApprovalRejected $event): void
    {
        TicketAutomationTrigger::dispatch(
            $event->ticket,
            AutomationRule::TRIGGER_APPROVAL_REJECTED,
            array_merge($event->context, [
                'approval_request_id' => $event->approvalRequest->id,
            ]),
        );
    }
}
