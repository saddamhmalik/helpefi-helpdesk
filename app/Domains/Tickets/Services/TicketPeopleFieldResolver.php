<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Brands\Models\Brand;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Workforce\Models\Team;
use App\Domains\Workforce\Services\WorkforceService;

class TicketPeopleFieldResolver
{
    public function __construct(
        private ContactService $contacts,
        private HelpdeskSettingService $helpdeskSettings,
        private WorkforceService $workforce,
    ) {
    }

    public function extractPeopleFields(array &$data): array
    {
        $ccEmails = array_key_exists('cc_emails', $data) ? (array) $data['cc_emails'] : null;
        $requesterEmail = array_key_exists('requester_email', $data)
            ? trim((string) $data['requester_email'])
            : null;
        $requesterName = array_key_exists('requester_name', $data)
            ? trim((string) $data['requester_name'])
            : null;

        unset($data['cc_emails'], $data['requester_email'], $data['requester_name']);

        if ($requesterEmail === '') {
            $requesterEmail = null;
        }

        if ($requesterName === '') {
            $requesterName = null;
        }

        return [$ccEmails, $requesterEmail, $requesterName];
    }

    public function resolveRequester(
        array $data,
        ?string $requesterEmail,
        ?string $requesterName,
        ?int $userId,
        ?Ticket $ticket = null,
    ): array {
        if (! empty($data['contact_id'])) {
            return $data;
        }

        if ($requesterEmail) {
            $name = $requesterName ?: explode('@', $requesterEmail)[0];
            $contact = $this->contacts->findOrCreateByEmail($requesterEmail, $name, $userId);
            $data['contact_id'] = $contact->id;

            return $data;
        }

        if (array_key_exists('contact_id', $data) && ($data['contact_id'] === '' || $data['contact_id'] === null)) {
            if ($ticket && ! $requesterEmail) {
                unset($data['contact_id']);
            } else {
                $data['contact_id'] = null;
            }
        } elseif (! array_key_exists('contact_id', $data) && $ticket) {
            $data['contact_id'] = $ticket->contact_id;
        }

        return $data;
    }

    public function resolveTicketCustomFields(array $data): array
    {
        if (! empty($data['brand_id'])) {
            $brand = Brand::query()->find($data['brand_id']);

            return $this->helpdeskSettings->resolveFieldValuesForBrand('ticket', $data['custom_fields'] ?? [], $brand);
        }

        return $this->helpdeskSettings->resolveFieldValues('ticket', $data['custom_fields'] ?? []);
    }

    public function normalizeRichText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = MessageBodySanitizer::sanitize($value);

        return MessageBodySanitizer::isEmpty($sanitized) ? null : $sanitized;
    }

    public function applyWorkforceRouting(array $data, ?Ticket $ticket = null): array
    {
        if (! empty($data['team_id']) && empty($data['department_id'])) {
            $team = Team::query()->find($data['team_id']);
            $data['department_id'] = $team?->department_id;
        }

        if (empty($data['department_id']) && empty($data['team_id'])) {
            $assigneeId = $data['assigned_to'] ?? $ticket?->assigned_to;

            if ($assigneeId) {
                $data = array_merge($data, array_filter($this->workforce->resolveRoutingForAssignee($assigneeId)));
            }
        }

        return $data;
    }
}
