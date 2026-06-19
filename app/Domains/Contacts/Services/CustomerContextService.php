<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Repositories\CustomerContextRepository;
use App\Domains\Integrations\Services\CommerceContextService;
use App\Domains\Integrations\Services\CrmProfileService;
use App\Domains\Tickets\Repositories\TicketRepository;

class CustomerContextService
{
    public function __construct(
        private TicketRepository $tickets,
        private CustomerContextRepository $metrics,
        private CrmProfileService $crmProfiles,
        private CommerceContextService $commerce,
    ) {
    }

    public function forTicket(int $ticketId, bool $refreshCrm = false): ?array
    {
        $ticket = $this->tickets->findContactContext($ticketId);

        if (! $ticket->contact) {
            return null;
        }

        return $this->forContact($ticket->contact, $refreshCrm);
    }

    public function forContact(Contact $contact, bool $refreshCrm = false): array
    {
        $contact->loadMissing('organization');
        $scopeIds = $this->metrics->contactIdsForScope($contact);
        $since = now()->subDays(90);
        $summary = $this->metrics->metricsSummary($scopeIds, $since);

        $health = $this->calculateHealth(
            $summary['open_tickets'],
            $summary['sla_breaches_90d'],
            $summary['csat'],
        );

        $organization = null;

        if ($contact->organization) {
            $organization = [
                'id' => $contact->organization->id,
                'name' => $contact->organization->name,
                'customer_tier' => $contact->organization->customer_tier,
                'website' => $contact->organization->website,
            ];
        }

        return [
            'scope' => $contact->organization_id ? 'organization' : 'contact',
            'health' => $health,
            'organization' => $organization,
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
            'metrics' => [
                'open_tickets' => $summary['open_tickets'],
                'total_tickets' => $summary['total_tickets'],
                'sla_breaches_90d' => $summary['sla_breaches_90d'],
                'csat_average_90d' => $summary['csat']['average'],
                'csat_responses_90d' => $summary['csat']['responses'],
                'last_contact_at' => $summary['last_contact_at']?->toIso8601String(),
            ],
            'crm' => $this->crmProfiles->snapshotForContact($contact, $refreshCrm),
            'commerce' => $this->commerce->snapshotForEmail($contact->email),
        ];
    }

    private function calculateHealth(int $openTickets, int $slaBreaches, array $csat): array
    {
        $score = 100;

        if ($csat['responses'] > 0 && $csat['average'] !== null) {
            if ($csat['average'] < 3) {
                $score -= 20;
            } elseif ($csat['average'] < 4) {
                $score -= 10;
            }
        }

        $score -= min($openTickets * 5, 25);
        $score -= min($slaBreaches * 10, 30);

        if ($csat['low_recent']) {
            $score -= 15;
        }

        $score = max(0, min(100, $score));

        if ($score >= 80) {
            return ['score' => $score, 'label' => 'Healthy', 'level' => 'healthy'];
        }

        if ($score >= 60) {
            return ['score' => $score, 'label' => 'At risk', 'level' => 'at_risk'];
        }

        return ['score' => $score, 'label' => 'Critical', 'level' => 'critical'];
    }
}
