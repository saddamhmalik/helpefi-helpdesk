<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Models\OrganizationDomain;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantDummyDataService
{
    private const MANIFEST_KEYS = [
        'department_ids',
        'team_ids',
        'tag_ids',
        'organization_ids',
        'contact_ids',
        'ticket_ids',
    ];

    public function __construct(
        private HelpdeskSettingRepository $settings,
        private TicketRepository $tickets,
        private ChannelRepository $channels,
        private SlaService $sla,
    ) {
    }

    public function publicState(): array
    {
        $setting = $this->settings->current();

        return [
            'active' => (bool) $setting->dummy_data_active,
            'needs_choice' => $setting->dummy_data_choice_at === null,
            'summary' => $this->summary($setting->dummy_data_manifest ?? []),
        ];
    }

    public function needsChoice(): bool
    {
        return $this->settings->current()->dummy_data_choice_at === null;
    }

    public function isActive(): bool
    {
        return (bool) $this->settings->current()->dummy_data_active;
    }

    public function skip(): void
    {
        $setting = $this->settings->current();

        if ($setting->dummy_data_choice_at !== null) {
            return;
        }

        $this->settings->update($setting, [
            'dummy_data_choice_at' => now(),
        ]);
    }

    public function install(User $admin): void
    {
        $setting = $this->settings->current();

        if ($setting->dummy_data_active) {
            throw ValidationException::withMessages([
                'dummy_data' => 'Sample data is already loaded.',
            ]);
        }

        if ($setting->dummy_data_choice_at !== null && ! $setting->dummy_data_active) {
            throw ValidationException::withMessages([
                'dummy_data' => 'You already chose to start with an empty workspace.',
            ]);
        }

        $manifest = DB::transaction(fn () => $this->seed($admin));

        $this->settings->update($setting, [
            'dummy_data_active' => true,
            'dummy_data_choice_at' => now(),
            'dummy_data_manifest' => $manifest,
        ]);
    }

    public function remove(): void
    {
        $setting = $this->settings->current();

        if (! $setting->dummy_data_active) {
            throw ValidationException::withMessages([
                'dummy_data' => 'There is no sample data to remove.',
            ]);
        }

        DB::transaction(fn () => $this->purge($setting->dummy_data_manifest ?? []));

        $this->settings->update($setting, [
            'dummy_data_active' => false,
            'dummy_data_manifest' => null,
        ]);
    }

    private function seed(User $admin): array
    {
        $manifest = array_fill_keys(self::MANIFEST_KEYS, []);

        $support = Department::query()->create([
            'name' => 'Customer Support',
            'slug' => 'sample-support',
            'description' => 'Sample department for front-line support.',
            'head_user_id' => $admin->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $manifest['department_ids'][] = $support->id;

        $billing = Department::query()->create([
            'name' => 'Billing & Accounts',
            'slug' => 'sample-billing',
            'description' => 'Sample department for invoices and subscriptions.',
            'head_user_id' => $admin->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $manifest['department_ids'][] = $billing->id;

        $tier1 = Team::query()->create([
            'department_id' => $support->id,
            'name' => 'Tier 1',
            'slug' => 'sample-tier-1',
            'description' => 'Sample first-line team.',
            'lead_user_id' => $admin->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $tier1->members()->sync([$admin->id => ['org_role' => Team::ROLE_TEAM_LEAD]]);
        $manifest['team_ids'][] = $tier1->id;

        $tier2 = Team::query()->create([
            'department_id' => $support->id,
            'name' => 'Tier 2',
            'slug' => 'sample-tier-2',
            'description' => 'Sample escalation team.',
            'lead_user_id' => $admin->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $manifest['team_ids'][] = $tier2->id;

        $accounts = Team::query()->create([
            'department_id' => $billing->id,
            'name' => 'Accounts',
            'slug' => 'sample-accounts',
            'description' => 'Sample billing team.',
            'lead_user_id' => $admin->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $manifest['team_ids'][] = $accounts->id;

        $tagDefinitions = [
            ['name' => 'VIP', 'slug' => 'sample-vip', 'color' => 'amber'],
            ['name' => 'Billing', 'slug' => 'sample-billing-tag', 'color' => 'purple'],
            ['name' => 'Urgent', 'slug' => 'sample-urgent', 'color' => 'red'],
            ['name' => 'Onboarding', 'slug' => 'sample-onboarding', 'color' => 'blue'],
        ];

        $tags = [];

        foreach ($tagDefinitions as $definition) {
            $tag = Tag::query()->create($definition);
            $tags[$definition['slug']] = $tag;
            $manifest['tag_ids'][] = $tag->id;
        }

        $sampleCorp = Organization::query()->create([
            'name' => 'Sample Corp',
            'website' => 'https://sample-corp.example',
            'phone' => '+1 555 0101',
            'description' => 'Sample enterprise customer for testing.',
            'customer_tier' => 'enterprise',
        ]);
        $manifest['organization_ids'][] = $sampleCorp->id;
        OrganizationDomain::query()->create([
            'organization_id' => $sampleCorp->id,
            'domain' => 'sample-corp.example',
        ]);

        $northwind = Organization::query()->create([
            'name' => 'Northwind Traders',
            'website' => 'https://northwind.example',
            'phone' => '+1 555 0102',
            'description' => 'Sample mid-market customer for testing.',
            'customer_tier' => 'standard',
        ]);
        $manifest['organization_ids'][] = $northwind->id;
        OrganizationDomain::query()->create([
            'organization_id' => $northwind->id,
            'domain' => 'northwind.example',
        ]);

        $contactDefinitions = [
            ['name' => 'Alice Chen', 'email' => 'alice@sample-corp.example', 'organization_id' => $sampleCorp->id, 'tags' => ['sample-vip', 'sample-onboarding']],
            ['name' => 'Bob Martinez', 'email' => 'bob@sample-corp.example', 'organization_id' => $sampleCorp->id, 'tags' => ['sample-billing-tag']],
            ['name' => 'Carol Nguyen', 'email' => 'carol@northwind.example', 'organization_id' => $northwind->id, 'tags' => ['sample-urgent']],
            ['name' => 'Dave Wilson', 'email' => 'dave@northwind.example', 'organization_id' => $northwind->id, 'tags' => []],
            ['name' => 'Eve Park', 'email' => 'eve@freelance.example', 'organization_id' => null, 'tags' => ['sample-onboarding']],
        ];

        $contacts = [];

        foreach ($contactDefinitions as $definition) {
            $tagSlugs = $definition['tags'];
            unset($definition['tags']);

            $contact = Contact::query()->create($definition);
            $tagIds = collect($tagSlugs)
                ->map(fn (string $slug) => $tags[$slug]->id ?? null)
                ->filter()
                ->all();
            $contact->tags()->sync($tagIds);
            $contacts[$contact->email] = $contact;
            $manifest['contact_ids'][] = $contact->id;
        }

        $openStatus = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $pendingStatus = TicketStatus::query()->where('slug', 'pending')->firstOrFail();
        $normalPriority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $highPriority = TicketPriority::query()->where('slug', 'high')->firstOrFail();
        $webChannelId = $this->channels->findActiveBySlug('web')?->id;

        $ticketDefinitions = [
            [
                'subject' => 'Cannot reset my password',
                'description' => 'I need help signing in to my agent account.',
                'contact' => 'alice@sample-corp.example',
                'status' => $openStatus,
                'priority' => $highPriority,
                'team' => $tier1,
                'department' => $support,
                'tags' => ['sample-urgent'],
                'messages' => [
                    ['contact' => 'alice@sample-corp.example', 'body' => 'Hi, I cannot log in and need access today for a client demo.'],
                    ['user' => $admin->id, 'body' => 'Thanks Alice — I reset the lockout and sent a fresh reset link. Let me know if it arrives.'],
                ],
            ],
            [
                'subject' => 'Invoice shows the wrong plan',
                'description' => 'Our March invoice still lists the Starter plan after we upgraded last month.',
                'contact' => 'bob@sample-corp.example',
                'status' => $pendingStatus,
                'priority' => $normalPriority,
                'team' => $accounts,
                'department' => $billing,
                'tags' => ['sample-billing-tag'],
                'messages' => [
                    ['contact' => 'bob@sample-corp.example', 'body' => 'Please correct the invoice and send an updated PDF.'],
                    ['user' => $admin->id, 'body' => 'I am checking billing history now and will follow up shortly.', 'internal' => true],
                    ['user' => $admin->id, 'body' => 'Bob, I found the proration gap — finance is issuing a corrected invoice today.'],
                ],
            ],
            [
                'subject' => 'API rate limits too low',
                'description' => 'We are hitting 429 responses during nightly sync jobs.',
                'contact' => 'carol@northwind.example',
                'status' => $openStatus,
                'priority' => $highPriority,
                'team' => $tier2,
                'department' => $support,
                'tags' => ['sample-vip', 'sample-urgent'],
                'messages' => [
                    ['contact' => 'carol@northwind.example', 'body' => 'Our integration team needs higher limits before Friday.'],
                ],
            ],
            [
                'subject' => 'Add two agent seats',
                'description' => 'We hired two support specialists and need seats activated.',
                'contact' => 'dave@northwind.example',
                'status' => $openStatus,
                'priority' => $normalPriority,
                'team' => $accounts,
                'department' => $billing,
                'tags' => [],
                'messages' => [
                    ['contact' => 'dave@northwind.example', 'body' => 'Can you enable seats for jamie@northwind.example and priya@northwind.example?'],
                    ['user' => $admin->id, 'body' => 'Absolutely — I will confirm once the seats are active on your subscription.'],
                ],
            ],
            [
                'subject' => 'How do I set up email forwarding?',
                'description' => 'New admin here — need help connecting support@ourcompany.com.',
                'contact' => 'eve@freelance.example',
                'status' => $openStatus,
                'priority' => $normalPriority,
                'team' => $tier1,
                'department' => $support,
                'tags' => ['sample-onboarding'],
                'messages' => [
                    ['contact' => 'eve@freelance.example', 'body' => 'Is there a guide for Gmail forwarding into the helpdesk?'],
                ],
            ],
            [
                'subject' => 'Chat widget not appearing',
                'description' => 'Embedded the snippet but the bubble never shows on our marketing site.',
                'contact' => 'alice@sample-corp.example',
                'status' => $pendingStatus,
                'priority' => $normalPriority,
                'team' => $tier1,
                'department' => $support,
                'tags' => [],
                'messages' => [
                    ['contact' => 'alice@sample-corp.example', 'body' => 'We added the script to Webflow — anything we might be missing?'],
                    ['user' => $admin->id, 'body' => 'Please share the page URL and I will check the embed configuration.'],
                ],
            ],
        ];

        foreach ($ticketDefinitions as $definition) {
            $contact = $contacts[$definition['contact']];
            $messages = $definition['messages'];
            $tagSlugs = $definition['tags'];
            unset($definition['contact'], $definition['messages'], $definition['tags']);

            $ticket = $this->tickets->create([
                'channel_id' => $webChannelId,
                'subject' => $definition['subject'],
                'description' => $definition['description'],
                'contact_id' => $contact->id,
                'assigned_to' => $admin->id,
                'department_id' => $definition['department']->id,
                'team_id' => $definition['team']->id,
                'ticket_status_id' => $definition['status']->id,
                'ticket_priority_id' => $definition['priority']->id,
            ]);

            $this->sla->applyToTicket($ticket);

            $this->tickets->addMessage($ticket, [
                'channel_id' => $webChannelId,
                'contact_id' => $contact->id,
                'body' => $definition['description'],
                'is_internal' => false,
            ]);

            foreach ($messages as $message) {
                $this->tickets->addMessage($ticket, [
                    'channel_id' => $webChannelId,
                    'user_id' => $message['user'] ?? null,
                    'contact_id' => isset($message['contact']) ? $contacts[$message['contact']]->id : null,
                    'body' => $message['body'],
                    'is_internal' => (bool) ($message['internal'] ?? false),
                ]);
            }

            $ticketTagIds = collect($tagSlugs)
                ->map(fn (string $slug) => $tags[$slug]->id ?? null)
                ->filter()
                ->all();
            $ticket->tags()->sync($ticketTagIds);

            $manifest['ticket_ids'][] = $ticket->id;
        }

        return $manifest;
    }

    private function purge(array $manifest): void
    {
        if ($manifest !== []) {
            Ticket::query()->whereIn('id', $manifest['ticket_ids'] ?? [])->delete();

            Contact::query()->whereIn('id', $manifest['contact_ids'] ?? [])->delete();

            foreach ($manifest['organization_ids'] ?? [] as $organizationId) {
                OrganizationDomain::query()->where('organization_id', $organizationId)->delete();
            }

            Organization::query()->whereIn('id', $manifest['organization_ids'] ?? [])->delete();

            Team::query()->whereIn('id', $manifest['team_ids'] ?? [])->delete();
            Department::query()->whereIn('id', $manifest['department_ids'] ?? [])->delete();
            Tag::query()->whereIn('id', $manifest['tag_ids'] ?? [])->delete();
        }

        $this->purgeKnowledgeBase();
    }

    private function purgeKnowledgeBase(): void
    {
        KnowledgeArticle::query()->delete();
        KnowledgeCollection::query()->delete();
        KnowledgeCategory::query()->delete();
    }

    private function summary(array $manifest): array
    {
        return [
            'tickets' => count($manifest['ticket_ids'] ?? []),
            'contacts' => count($manifest['contact_ids'] ?? []),
            'teams' => count($manifest['team_ids'] ?? []),
            'departments' => count($manifest['department_ids'] ?? []),
            'tags' => count($manifest['tag_ids'] ?? []),
        ];
    }
}
