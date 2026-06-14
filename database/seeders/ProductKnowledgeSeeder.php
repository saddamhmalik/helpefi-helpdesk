<?php

namespace Database\Seeders;

use App\Domains\Brands\Models\Brand;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->role('admin')->orderBy('id')->value('id');

        $categories = [
            ['name' => 'Product documentation', 'slug' => 'product-documentation'],
            ['name' => 'Agent training', 'slug' => 'agent-training'],
            ['name' => 'Customer help', 'slug' => 'customer-help'],
        ];

        foreach ($categories as $category) {
            KnowledgeCategory::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }

        $collections = [
            [
                'name' => 'helpefi product guide',
                'slug' => 'product-guide',
                'description' => 'Complete documentation for the helpdesk platform',
                'sort_order' => 1,
                'is_public' => true,
            ],
            [
                'name' => 'Agent handbook',
                'slug' => 'agent-handbook',
                'description' => 'Day-to-day workflows for support agents',
                'sort_order' => 2,
                'is_public' => false,
            ],
            [
                'name' => 'Customer self-service',
                'slug' => 'customer-self-service',
                'description' => 'Help articles for portal customers',
                'sort_order' => 3,
                'is_public' => true,
            ],
        ];

        $brandId = Brand::query()->where('is_default', true)->value('id');

        foreach ($collections as $collection) {
            KnowledgeCollection::query()->updateOrCreate(['slug' => $collection['slug']], array_merge($collection, [
                'brand_id' => $brandId,
            ]));
        }

        $categoryIds = KnowledgeCategory::query()->pluck('id', 'slug');
        $collectionIds = KnowledgeCollection::query()->pluck('id', 'slug');

        foreach ($this->articles($categoryIds, $collectionIds, $authorId) as $article) {
            KnowledgeArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                $article,
            );
        }

        $this->call(PlatformHandbookSeeder::class);
    }

    private function articles($categoryIds, $collectionIds, ?int $authorId): array
    {
        $published = [
            'is_published' => true,
            'published_at' => now(),
            'author_id' => $authorId,
        ];

        return [
            array_merge($published, [
                'title' => 'helpefi platform overview',
                'slug' => 'helpdesk-platform-overview',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'Architecture, modules, and demo access for the single-tenant helpdesk.',
                'body' => $this->body(<<<'MD'
The helpdesk is a single-tenant support platform built with **Laravel 13**, **Vue 3**, and **Inertia.js**. It follows a **Controller → Service → Repository** pattern with domains under `app/Domains/`.

## Core areas

- **Agent portal** — `/dashboard` for admins and agents
- **Customer portal** — `/portal` for self-service and ticket tracking
- **REST API** — `/api/v1` with token authentication

## Demo access (local)

| | |
|---|---|
| Admin | `admin@helpdesk.test` / `password` |
| URL | http://helpdesk.test |

## Major modules

- Tickets, contacts, and organizations
- Knowledge base and customer portal
- SLA policies and agent workspace
- Reports, email channels, and automation
- Webhooks, AI assist, and service catalog
- Assets / CMDB and network discovery
- Billing plans, security (MFA / audit), and notifications
- CSAT surveys and roles & permissions

## Roles

| Role | Access |
|------|--------|
| **admin** | Full settings access |
| **agent** | Day-to-day ticket handling |
| **customer** | Portal access only |

Custom roles with granular permissions can be created at **Settings → Roles**.
MD),
            ]),
            array_merge($published, [
                'title' => 'Getting started for agents',
                'slug' => 'getting-started-for-agents',
                'knowledge_category_id' => $categoryIds['agent-training'],
                'knowledge_collection_id' => $collectionIds['agent-handbook'],
                'excerpt' => 'Dashboard, navigation, and your first ticket.',
                'body' => $this->body(<<<'TEXT'
After logging in you land on the Dashboard with open ticket counts, SLA breaches, volume trends, and CSAT summary.

Key navigation:
- Tickets — full ticket list with filters and saved views
- Workspace — split-pane queue for fast ticket handling (/workspace)
- Contacts / Organizations — customer records
- Assets — CMDB inventory (/assets)
- Knowledge — internal article management
- Reports — run and export helpdesk reports

Creating a ticket:
Go to Tickets → New ticket. Set subject, contact, status, priority, and assignee. Attachments, watchers, merge/split, and asset linking are available on the ticket detail page.

Replying:
Use the conversation panel on the ticket or workspace composer. Mark replies as internal notes when they should not be visible to customers. When outbound email is configured, public replies email the contact automatically.
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Tickets, workspace, and SLA',
                'slug' => 'tickets-workspace-and-sla',
                'knowledge_category_id' => $categoryIds['agent-training'],
                'knowledge_collection_id' => $collectionIds['agent-handbook'],
                'excerpt' => 'Ticket lifecycle, workspace, merge/split, and SLA timers.',
                'body' => $this->body(<<<'TEXT'
Ticket features:
- Attachments on tickets and messages
- Watchers for collaboration
- Merge another ticket into the current one
- Split conversation into a new ticket from a message
- Link CMDB assets from the sidebar
- Saved views with shared filters

Agent workspace (/workspace):
- Queue on the left, ticket detail and composer on the right
- Polls every few seconds for new messages
- Debounced draft auto-save
- Quick status, priority, and assignee updates
- AI suggest reply, summarize, and KB assist when enabled

SLA:
Each ticket gets first-response and resolution timers based on priority and business hours. Breaches are flagged in red and trigger notifications. Configure policies at Settings → SLA. A scheduled job detects breaches automatically.
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Contacts, organizations, and knowledge base',
                'slug' => 'contacts-and-knowledge-base',
                'knowledge_category_id' => $categoryIds['agent-training'],
                'knowledge_collection_id' => $collectionIds['agent-handbook'],
                'excerpt' => 'CRM records and managing help center content.',
                'body' => $this->body(<<<'TEXT'
Contacts:
- Store name, email, phone, and organization
- Tags for segmentation (e.g. VIP)
- Notes and activity timeline
- Auto-link to organizations by email domain

Organizations:
- Group contacts under companies
- View all related tickets and assets

Knowledge base (agent):
- Collections organize articles for the public portal
- Categories provide additional grouping
- Version history with restore on each edit
- Publish/unpublish controls visibility on /portal

Customer portal (/portal):
- Browse published collections and articles
- Submit new requests without logging in
- Register/login for My tickets
- Track tickets by number + email
- CSAT surveys on closed tickets
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Email, channels, and automation',
                'slug' => 'email-channels-and-automation',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'Inbound mailboxes, outbound SMTP, and workflow rules.',
                'body' => $this->body(<<<'TEXT'
Email (Settings → Email):
- Multiple inbound mailboxes — choose one inbound method per inbox:
  - **Webhook** — POST to `/api/v1/channels/inbound/email` with header `X-Channel-Token`
  - **Sign in (OAuth)** — Google Gmail, Microsoft Outlook, or Zoho Mail; tokens stored encrypted and refreshed automatically
  - **Manual IMAP/POP3** — Gmail, Outlook, Yahoo, iCloud, and custom servers with app passwords
- Creates tickets or threads replies when subject contains `[HD-00042]`
- OAuth and IMAP inboxes poll every minute via `channels:poll-inboxes` when enabled
- Outbound SMTP for agent reply emails and system notifications
- Send test email to verify configuration

Channels (Settings → Channels):
- Toggle web, portal, and API channel sources

Automation (Settings → Automation):
- Triggers: ticket created, ticket updated, customer message
- Conditions on status, priority, subject, channel, etc.
- Actions: set status/priority, assign agent, add watcher, internal note
- Requires Professional or Enterprise plan

Integrations (Settings → Integrations):
- Outbound webhooks with HMAC-SHA256 signatures
- Events: ticket.created, ticket.updated, ticket.customer_message
TEXT),
            ]),
            array_merge($published, [
                'title' => 'AI, service catalog, and assets',
                'slug' => 'ai-service-catalog-and-assets',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'AI assist, ITSM catalog, and CMDB with network discovery.',
                'body' => $this->body(<<<'TEXT'
AI (Settings → AI, Enterprise plan):
- Suggested replies in ticket and workspace composer
- Ticket conversation summaries
- Knowledge base article assist for agents
- Uses OpenAI-compatible API when OPENAI_API_KEY is set; local fallback otherwise

Service catalog (Settings → Service catalog):
- Categories and catalog items with custom fields
- Ticket types: incident, service request, change, problem
- Customers request services at /portal/services

Assets (/assets):
- CMDB with types, statuses, asset tags (AST-00001)
- Assign to contacts/organizations, parent/child hierarchy
- Link assets to tickets
- Network discovery (/assets/discovery): scan local subnet via ARP + ping, import devices with IP/MAC/hostname
- Requires Enterprise plan
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Reports, notifications, and CSAT',
                'slug' => 'reports-notifications-and-csat',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'Analytics, alerts, and customer satisfaction surveys.',
                'body' => $this->body(<<<'TEXT'
Reports (/reports):
- Ticket, SLA breach, agent performance, and CSAT report types
- Date filters, save reports per user, export CSV
- Dashboard widgets: volume trend, SLA breaches, agent load, CSAT (30 days)

Notifications:
- In-app bell with unread count
- Email alerts when mail is configured (Settings → Notifications)
- Events: ticket assigned, customer reply, SLA breach

CSAT (Settings → CSAT):
- Post-resolution 1–5 star surveys on portal for closed tickets
- Optional required comment
- CSAT report and dashboard widget
- Agents see submitted scores on ticket detail
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Administration: team, roles, billing, and security',
                'slug' => 'administration-guide',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'Settings for team, permissions, plans, and security hardening.',
                'body' => $this->body(<<<'TEXT'
Team (Settings → Team):
- Invite members by email with role assignment
- Change roles or remove members
- Invitation accept flow at /invitations/{token}

Roles & permissions (Settings → Roles):
- Create custom roles with granular permissions
- System roles admin, agent, customer are protected from deletion
- Custom roles need access.agent permission for portal access

Billing (Settings → Billing):
- Plans: Starter ($29), Professional ($79), Enterprise ($199)
- Limits on agents and monthly tickets
- Feature gates: AI, integrations, automation, assets, channels, etc.

Security (Settings → Security):
- TOTP two-factor authentication on profile
- Optional MFA requirement for all agents
- Audit log and retention purge for audit/closed tickets

Profile (Settings → Profile):
- Update name, email, password, and enable 2FA
TEXT),
            ]),
            array_merge($published, [
                'title' => 'REST API overview',
                'slug' => 'rest-api-overview',
                'knowledge_category_id' => $categoryIds['product-documentation'],
                'knowledge_collection_id' => $collectionIds['product-guide'],
                'excerpt' => 'Authentication and main API endpoints at /api/v1.',
                'body' => $this->body(<<<'TEXT'
Base URL: /api/v1

Authentication:
- POST /api/v1/auth/login with email and password returns a bearer token
- Include header Authorization: Bearer {token} on protected routes
- Public routes: inbound email webhook, portal auth register/login

Key endpoints:
- Tickets CRUD, reply, attachments, watchers, merge, split
- Knowledge collections and articles with version restore
- SLA policies and ticket timers
- Workspace queue, poll, draft, reply, quick update
- Reports dashboard, run, export, saved reports
- Channels, email inboxes, outbound settings
- Automation rules, integration webhooks
- AI suggest reply, summarize, KB assist
- Service catalog, assets, discovery scans
- Billing, security, notifications, CSAT, roles

Import the Postman collection from postman/helpefi.postman_collection.json for full examples.
TEXT),
            ]),
            array_merge($published, [
                'title' => 'How to submit a support request',
                'slug' => 'how-to-submit-a-support-request',
                'knowledge_category_id' => $categoryIds['customer-help'],
                'knowledge_collection_id' => $collectionIds['customer-self-service'],
                'excerpt' => 'Open a ticket from the customer portal.',
                'body' => $this->body(<<<'TEXT'
You can submit a request without an account:

1. Go to the Help Center at /portal
2. Click Submit a request or browse the knowledge base first
3. Fill in your name, email, subject, and description
4. Submit — you will receive a ticket number like HD-00042

With an account:
1. Register or log in at /portal/login
2. Go to My tickets or submit from the portal home
3. All your requests appear under My tickets

For IT service requests, browse /portal/services to pick a catalog item with structured fields.
TEXT),
            ]),
            array_merge($published, [
                'title' => 'How to track your ticket',
                'slug' => 'how-to-track-your-ticket',
                'knowledge_category_id' => $categoryIds['customer-help'],
                'knowledge_collection_id' => $collectionIds['customer-self-service'],
                'excerpt' => 'Check status by ticket number and email.',
                'body' => $this->body(<<<'TEXT'
Track without logging in:

1. Visit /portal/track
2. Enter your ticket number (e.g. HD-00042) and the email address used when submitting
3. View status, description, and agent replies

When your ticket is closed you may see a satisfaction survey — rate your experience from 1 to 5 stars.

Logged-in customers see all tickets at /portal/my-tickets without entering the number each time.

To reply by email, include [HD-00042] in the subject line when email channel is enabled.
TEXT),
            ]),
            array_merge($published, [
                'title' => 'Customer portal account',
                'slug' => 'customer-portal-account',
                'knowledge_category_id' => $categoryIds['customer-help'],
                'knowledge_collection_id' => $collectionIds['customer-self-service'],
                'excerpt' => 'Register, log in, and manage your tickets.',
                'body' => $this->body(<<<'TEXT'
Create an account at /portal/register with your name, email, and password.

Benefits of an account:
- See all your tickets in one place (/portal/my-tickets)
- Open ticket detail with full conversation history
- Submit CSAT feedback when tickets are resolved
- Request services from the service catalog

Note: Agent accounts cannot use the customer portal login. Customers use a separate customer role.

Forgot password flow uses standard Laravel password reset if configured by your administrator.
TEXT),
            ]),
        ];
    }

    private function body(string $text): string
    {
        return trim($text);
    }
}
