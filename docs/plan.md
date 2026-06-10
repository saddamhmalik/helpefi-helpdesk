---
name: helpefi Platform Build
overview: Single-tenant helpdesk on Laravel 13 with Vue 3 + Inertia. Phases 1–4 complete. Phase 5 closes market gaps vs Zendesk, Freshdesk, and Help Scout (agent productivity, scale, omnichannel, enterprise).
todos:
  - id: phase-0-scaffold
    content: Laravel 13 + Inertia/Vue3 + SQLite + Valet local dev
    status: completed
  - id: module-00-foundation
    content: Auth shell, Spatie roles, dashboard, demo seeders, tests
    status: completed
  - id: module-01-auth
    content: Invitations, member management, profile/password settings
    status: completed
  - id: module-02-contacts
    content: Organizations, tags, notes, activity timeline
    status: completed
  - id: module-03-tickets
    content: "Ticketing enhancements: attachments, watchers, merge/split, saved views"
    status: completed
  - id: module-05-knowledge
    content: KB collections/versions + public portal submit/track
    status: completed
  - id: module-06-sla
    content: SLA policies, business-hours clocks, breach jobs, timer UI
    status: completed
  - id: module-08-workspace
    content: Split-pane agent workspace with composer dock and details sidebar
    status: completed
  - id: module-09-reports
    content: Dashboard widgets, saved reports, CSV export
    status: completed
  - id: module-04-omnichannel
    content: Email channel, inbound webhook, IMAP/OAuth polling, outbound replies
    status: completed
  - id: module-07-automation
    content: Workflow automation rules with triggers, conditions, and actions
    status: completed
  - id: module-10-integrations
    content: Outbound webhooks with HMAC-signed ticket event delivery
    status: completed
  - id: module-11-ai
    content: AI suggested replies, ticket summaries, and KB assist for agents
    status: completed
  - id: module-12-itsm
    content: ITSM service catalog with portal requests and ticket types
    status: completed
  - id: module-13-assets
    content: CMDB asset inventory with contact assignment and ticket linkage
    status: completed
  - id: module-14-billing
    content: Plan tiers, usage limits, and feature gates with admin billing UI
    status: completed
  - id: module-15-security
    content: MFA, audit logs, data retention, and security observability
    status: completed
  - id: module-16-notifications
    content: In-app and email alerts for assignments, replies, and SLA breaches
    status: completed
  - id: module-17-csat
    content: Post-resolution CSAT surveys on portal with admin settings and reports
    status: completed
  - id: module-18-agent-ui
    content: "Agent UX: top bar, global search, shared list UI, modern forms, ticket layout"
    status: completed
  - id: module-19-requester-cc
    content: Requester combobox, ticket CCs, outbound CC on replies, merge CC copy
    status: completed
  - id: phase5-01-macros
    content: "Canned responses / macros with placeholders and composer insert"
    status: completed
  - id: phase5-02-realtime
    content: "Real-time updates via WebSockets (replace polling in workspace + chat widget)"
    status: completed
  - id: phase5-03-collision
    content: "Collision detection — agent presence and reply-in-progress warnings"
    status: completed
  - id: phase5-04-inbound-cc
    content: "Inbound email CC parsing and reply-from-CC threading"
    status: completed
  - id: phase5-05-auto-assign
    content: "Auto-assignment — round-robin and load-based routing rules"
    status: completed
  - id: phase5-06-side-conversations
    content: "Side conversations — email third parties without merging tickets"
    status: completed
  - id: phase5-07-email-csat
    content: "Email-triggered CSAT survey after ticket resolve/close"
    status: completed
  - id: phase5-08-live-chat
    content: "Embeddable live chat widget with agent inbox integration"
    status: completed
  - id: phase5-09-ai-deflection
    content: "Customer-facing AI deflection on portal and chat (KB + bot)"
    status: completed
  - id: phase5-10-scheduled-reports
    content: "Scheduled report delivery via email (weekly SLA, volume digests)"
    status: completed
  - id: phase5-11-api-parity
    content: "REST API for contacts, organizations, and ticket export endpoints"
    status: completed
  - id: phase5-12-automation-v2
    content: "Automation v2 — delays, multi-step chains, webhook actions, auto-tag"
    status: completed
  - id: phase5-13-kb-deflection
    content: "Semantic KB search and suggested articles before ticket submit"
    status: completed
  - id: phase5-14-multi-brand
    content: "Multi-brand — per-brand portal, mailbox, KB skin, and ticket forms"
    status: completed
  - id: phase5-15-time-tracking
    content: "Time tracking per ticket with agent report rollups"
    status: completed
  - id: phase5-16-stripe-billing
    content: "Real billing via Stripe — subscriptions, invoices, usage metering"
    status: cancelled
  - id: phase5-17-integrations-slack-jira
    content: "Bi-directional Slack and Jira/Linear integrations"
    status: completed
  - id: phase5-18-skills-routing
    content: "Skills-based routing and group SLA policies by team/customer tier"
    status: completed
  - id: phase7-01-feature-gate
    content: "Service Desk paid add-on — service_desk feature on Enterprise plan"
    status: completed
  - id: phase7-02-overview
    content: "Service desk hub with ITIL type stats and queue links"
    status: completed
  - id: phase7-03-type-queues
    content: "Type-filtered ticket queues (incident, request, change, problem)"
    status: completed
  - id: phase7-04-nav
    content: "Service Desk agent nav section and upgrade page"
    status: completed
  - id: phase7-05-approvals
    content: "Approval engine for catalog items and change requests"
    status: completed
  - id: phase7-06-change-mgmt
    content: "Change management — risk, schedule, CAB lite"
    status: completed
  - id: phase7-07-problem-mgmt
    content: "Problem records with linked incidents and known errors"
    status: completed
  - id: phase7-08-major-incident
    content: "Major incident flag, war-room view, post-incident review"
    status: completed
isProject: true
---

# helpefi Platform — Implementation Plan (Single-Tenant)

## Current State (June 2026)

**Phases 1–4 are complete.** The product is a full single-tenant helpdesk comparable to Freshdesk Growth / Help Scout Plus for email-centric support.

| Area | Status |
|------|--------|
| Core ticketing | Merge, split, views, filters, CC, requester, custom fields, export |
| Channels | Email (webhook + IMAP/OAuth), web, portal, API |
| Agent UX | Workspace (queue + conversation + sidebar), global search, modern forms |
| SLA & automation | Business hours, breach job, escalations, rule engine |
| KB & portal | Collections, versions, guest/auth submit & track, service catalog |
| CSAT, reports, notifications | Portal surveys, saved reports, CSV, in-app + email alerts |
| AI (agent-side) | Suggest reply, summarize, KB assist |
| CMDB, workforce, security | Assets, depts/teams, 2FA, audit, custom roles |
| Integrations | Outbound webhooks, broad REST API, Postman collection |

**Not yet at parity with:** Zendesk Professional (routing depth, side convos, multi-brand), Intercom (in-app chat, proactive messaging, AI deflection).

---

## Architecture

| Decision | Choice |
|---|---|
| Repo layout | Single Laravel app with `app/Domains/` |
| Frontend | Vue 3 + Inertia.js + Tailwind 4 |
| Backend | Controller → Service → Repository |
| Auth | Session login + Spatie roles + custom roles |
| Database | SQLite (local) / PostgreSQL (production) |
| Local dev | Laravel Valet — `http://helpdesk.test` |

---

## Completed — Phases 1–4

<details>
<summary>Modules 00–17 (click to expand)</summary>

### Module 00 — Foundation
Login, dashboard, MVP CRUD, demo seeder.

### Module 01 — Auth & Team
Invitations, member management, profile/password.

### Module 02 — Contacts & Organizations
Organizations, tags, notes, activity timeline, domain auto-link.

### Module 03 — Ticketing Enhancements
Attachments, watchers, merge/split, saved views, REST API.

### Module 04 — Omnichannel
Channel registry, inbound email webhook, IMAP/POP3 polling, OAuth (Google/Microsoft/Zoho), outbound SMTP, threading.

### Module 05 — Knowledge Portal
Collections, version history, public portal, customer auth, service catalog.

### Module 06 — SLA Engine
Business hours, priority targets, breach job, escalations, timer UI.

### Module 07 — Workflow Automation
Triggers (created/updated/customer message), conditions, actions.

### Module 08 — Agent Workspace
Split-pane queue, polling, composer drafts, quick updates, viewport-locked layout, details sidebar.

### Module 09 — Reports
Dashboard widgets, saved reports, CSV export.

### Module 10 — Integrations
Outbound webhooks with HMAC signatures and delivery log.

### Module 11 — AI Layer
Agent assist: suggest reply, summarize, KB assist.

### Module 12 — ITSM Service Catalog
Ticket types, catalog items, portal requests.

### Module 13 — Assets & CMDB
Asset types, hierarchy, ticket linkage.

### Module 14 — Billing (simulated)
Starter / Professional / Enterprise plans, feature gates.

### Module 15 — Security
TOTP 2FA, audit logs, retention purge.

### Module 16 — Notifications
In-app inbox, email alerts, admin toggles.

### Module 17 — CSAT
Portal surveys, reports, dashboard widget.

</details>

### Recent — Agent UX & Ticketing (Phase 4b)

| Task | Done |
|------|------|
| Top navbar — breadcrumbs, global search (⌘K), New ticket, user menu | ✅ |
| Shared list UI — PageHeader, ListPanel, FilterField, DataTable across pages | ✅ |
| Viewport-locked ticket show — conversation fill, composer dock, embedded sidebar | ✅ |
| Modern create forms — FormPage, FormField, FormRichTextField | ✅ |
| Requester combobox — search or create by email | ✅ |
| Ticket CCs — chip input, outbound CC on replies, merge copy | ✅ |
| Workspace layout fix — compact header, TicketComposerDock, xl sidebar | ✅ |
| Shared ticket views — team visibility on saved views | ✅ |

---

## Phase 5 — Market Parity Roadmap

Competitive analysis vs Zendesk, Freshdesk, Intercom, Help Scout (June 2026).

```mermaid
flowchart LR
    subgraph p5a [5A Agent productivity]
        M1[Macros]
        M2[Realtime]
        M3[Collision]
        M4[Inbound CC]
    end
    subgraph p5b [5B Scale]
        M5[Auto-assign]
        M6[Side convos]
        M7[Email CSAT]
    end
    subgraph p5c [5C Omnichannel]
        M8[Live chat]
        M9[AI deflection]
        M10[Scheduled reports]
    end
    subgraph p5d [5D Enterprise]
        M11[API parity]
        M12[Automation v2]
        M13[Multi-brand]
        M14[Stripe]
        M15[Slack/Jira]
    end
    p5a --> p5b --> p5c --> p5d
```

---

## Phase 5 — Task List

### 5A — Agent Productivity (do first)

High daily impact, relatively contained scope.

| ID | Task | Description | Depends on |
|----|------|-------------|------------|
| `phase5-01-macros` | **Canned responses / macros** | Saved reply library; placeholders (`{{ticket.number}}`, `{{contact.name}}`); search + insert in composer; admin CRUD at `/settings/macros` | — |
| `phase5-02-realtime` | **Real-time updates** | Laravel Reverb or Pusher; broadcast new messages, queue changes, ticket updates; remove 5s polling in workspace | — |
| `phase5-03-collision` | **Collision detection** | Track agents viewing/replying per ticket; show banner in workspace + full view; optional lock | `phase5-02-realtime` |
| `phase5-04-inbound-cc` | **Inbound CC parsing** | Parse CC from inbound email; sync to `ticket_ccs`; thread replies from CC addresses | — |

**Acceptance criteria (5A):**
- Agent inserts macro in &lt;2 clicks from composer
- New customer message appears in workspace without manual refresh
- Two agents on same ticket see each other's presence within 2s
- Inbound email with CC adds recipients; CC reply threads correctly

---

### 5B — Scale (teams 10+ agents)

| ID | Task | Description | Depends on |
|----|------|-------------|------------|
| `phase5-05-auto-assign` | **Auto-assignment** | Rules: round-robin, load-based (open ticket count), optional team scope; admin UI | — |
| `phase5-06-side-conversations` | **Side conversations** | Separate email thread to vendor/partner on a ticket; visible in sidebar; does not merge tickets | — |
| `phase5-07-email-csat` | **Email CSAT** | Send satisfaction survey email on resolve/close; link to portal rating; include in CSAT reports | — |

**Acceptance criteria (5B):**
- Unassigned tickets auto-distribute per configured rule
- Agent can email external party from ticket without exposing full thread
- CSAT response rate measurable separately for email vs portal

---

### 5C — Omnichannel & AI (RFP checkboxes)

| ID | Task | Description | Depends on |
|----|------|-------------|------------|
| `phase5-08-live-chat` | **Live chat widget** | Embeddable JS snippet; visitor → ticket/message; agent replies from workspace; offline → email ticket | `phase5-02-realtime` |
| `phase5-09-ai-deflection` | **Customer AI deflection** | Bot on portal + widget: search KB, answer FAQs, create ticket if unresolved; usage metrics | `phase5-08-live-chat` (optional) |
| `phase5-10-scheduled-reports` | **Scheduled reports** | Cron: email CSV/PDF of saved reports on daily/weekly schedule | — |
| `phase5-13-kb-deflection` | **KB deflection at submit** | Suggest articles before ticket create on portal; track deflection rate | — |

**Acceptance criteria (5C):**
- Chat widget works on external site with one script tag
- ≥30% of portal visitors see KB suggestions before submit (measurable)
- Admin receives weekly SLA breach report by email without login

---

### 5D — Enterprise & Monetization

| ID | Task | Description | Depends on |
|----|------|-------------|------------|
| `phase5-11-api-parity` | **API parity** | REST endpoints for contacts, organizations; ticket PDF/email export via API | — |
| `phase5-12-automation-v2` | **Automation v2** | Delayed actions, multi-step chains, webhook as action, auto-tag/priority from keywords | — |
| `phase5-14-multi-brand` | **Multi-brand** | Brands with own portal URL, mailbox, KB collection, ticket form defaults | — |
| `phase5-15-time-tracking` | **Time tracking** | Log minutes per ticket; agent/team rollup in reports | — |
| `phase5-16-stripe-billing` | **Stripe billing** | Replace simulated plans; subscriptions, invoices, seat limits, usage overages | — |
| `phase5-17-integrations-slack-jira` | **Slack + Jira** | Post ticket events to Slack; create/link Jira issues bidirectionally | — |
| `phase5-18-skills-routing` | **Skills routing** | Agent skills tags; route by skill + priority; group SLA by team/tier | `phase5-05-auto-assign` |

---

## Known Gaps (not scheduled)

Defer unless a specific buyer requires them.

| Gap | Notes |
|-----|-------|
| Voice / telephony | Integrate Twilio later |
| Social channels (X, Facebook) | Low priority vs chat/email |
| Full ITIL change advisory board | Service catalog covers basic ITSM |
| Self-hosted deployment | Cloud-first |
| Native mobile apps | Responsive web sufficient |
| Multi-tenancy / white-label | Single-tenant by design |
| Asset network discovery / RDP | Removed in migration `2026_06_08_920000` |
| Intercom-style proactive messaging | Different product bet; only if SaaS ICP |

---

## API Gaps (web-only today)

Track under `phase5-11-api-parity` — **complete**:

- Contacts CRUD API — done
- Organizations CRUD API — done
- Ticket PDF/email export API — done
- SLA escalation rule CRUD API — done (`GET/POST/DELETE /api/v1/sla/escalations`, `GET /api/v1/sla/escalations/meta`)
- helpefi/ticket settings API — done (`GET/PUT /api/v1/settings/helpdesk`)
- Global search API — done (`GET /api/v1/search?q=`)

---

## Conventions

- Domains under `app/Domains/{Name}/`
- Inertia pages under `resources/js/Pages/`
- Controller → Service → Repository; no fat controllers
- Feature tests for auth, critical flows, and each Phase 5 module
- No code comments unless non-obvious business logic

---

## Local Setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed && npm run build
valet link helpdesk
```

Demo login: `admin@helpdesk.test` / `password`

---

## Next Up

**Phase 5B** (in progress):

1. `phase5-05-auto-assign` — round-robin and load-based routing (done)
2. `phase5-06-side-conversations` — email third parties from a ticket (done)
3. `phase5-07-email-csat` — satisfaction survey on resolve/close (done)
4. `phase5-08-live-chat` — embeddable live chat widget with agent inbox integration (done)
5. `phase5-09-ai-deflection` — customer-facing AI deflection on portal and chat (done)
6. `phase5-13-kb-deflection` — semantic KB search and suggested articles before ticket submit (done)

**Phase 5D** (next):

1. `phase5-14-multi-brand` — per-brand portal URL, mailbox, KB collection, ticket form defaults (done)

**Phase 5D** (next):

1. `phase5-15-time-tracking` — log minutes per ticket; agent/team rollup in reports (done)

**Phase 5D** (next):

1. `phase5-16-stripe-billing` — skipped (simulated billing retained)
2. `phase5-17-integrations-slack-jira` — Slack notifications; Jira/Linear issue create/link and status sync (done)

**Phase 5D** (next):

1. `phase5-18-skills-routing` — skills-based routing and group SLA policies by team/customer tier (done)

Phase 5 is complete. API parity follow-up (`phase5-11-api-parity`) is complete.

---

## Phase 6 — Enterprise Gaps (June 2026)

Competitive R&D vs Zendesk, Freshdesk, Intercom. Priority 1 implemented using official packages.

| Package | Purpose |
|---------|---------|
| `laravel/socialite` | OIDC/OAuth (Google, generic OIDC) |
| `socialiteproviders/microsoft-azure` | Microsoft Entra ID |
| `onelogin/php-saml` | SAML 2.0 SP |
| `twilio/sdk` | WhatsApp + SMS |
| `openai-php/client` | KB vector embeddings |
| `hubspot/api-client` | HubSpot CRM |
| `shopify/shopify-api` | Shopify store |
| `omniphx/forrest` | Salesforce |

### 6P1 — Enterprise blockers

| ID | Task | Status |
|----|------|--------|
| `phase6-01-sso` | SSO: OIDC (Google/Azure/generic) + SAML via Socialite & php-saml | done |
| `phase6-02-messaging` | WhatsApp + SMS via Twilio SDK, inbound webhook, agent replies | done |
| `phase6-03-marketplace` | Integration marketplace: Shopify, HubSpot, Salesforce, Teams, Zapier | done |
| `phase6-04-vector-kb` | Vector KB search via OpenAI embeddings + cosine similarity | done |

**Run after deploy:**
```bash
composer install
php artisan tenants:migrate
php artisan knowledge:embed-articles
npm run build
```

**Configure:** Settings → Security → Single sign-on, Settings → WhatsApp & SMS, Settings → Integrations (marketplace apps).

### 6P2 — Q1 competitive parity

| ID | Task | Status |
|----|------|--------|
| `phase6-05-snooze` | Ticket snooze with scheduled unsnooze | done |
| `phase6-06-statuses` | Custom ticket statuses admin CRUD | done |
| `phase6-07-crm-enrich` | HubSpot/Salesforce CRM enrichment on ticket create | done |
| `phase6-08-realtime-poll` | Disable workspace polling when realtime WebSocket is connected | done |
| `phase6-09-ai-triage` | AI auto-triage priority on new tickets | done |

**Run after deploy:**
```bash
php artisan tenants:migrate
npm run build
```

**Configure:** Settings → Ticket statuses, Settings → AI assistance (auto-triage), Settings → Integrations (HubSpot/Salesforce for CRM enrichment).

### 6P3 — API admin parity & OpenAPI

| ID | Task | Status |
|----|------|--------|
| `phase6-10-api-statuses` | Ticket statuses CRUD via REST API | done |
| `phase6-11-api-snooze` | Ticket snooze/unsnooze via workspace API | done |
| `phase6-12-api-sso` | SSO settings GET/PUT `/security/sso` | done |
| `phase6-13-api-messaging` | Twilio messaging settings GET/PUT | done |
| `phase6-14-api-integrations` | Marketplace connections GET/PUT/test | done |
| `phase6-15-api-ai` | AI settings API parity (triage + deflection) | done |
| `phase6-16-openapi` | OpenAPI 3.1 spec + Swagger UI at `/api/docs` | done |

**API docs:** `{workspace}/api/docs` — spec at `/api/v1/openapi.json`

### 6P4 — Customer 360 & executive dashboard

| ID | Task | Status |
|----|------|--------|
| `phase6-17-customer-360` | Unified customer timeline (tickets, messages, CSAT, chat, notes) | done |
| `phase6-18-executive-dashboard` | Central admin aggregated usage metrics across workspaces | done |
| `phase6-19-customer-context` | Ticket sidebar customer context: health score, CRM card, Shopify orders | done |
| `phase6-20-crm-profile-sync` | Cached CRM profiles with field sync; enriched HubSpot/Salesforce lookups | done |

**API:** `GET /api/v1/contacts/{id}/timeline`, `GET /api/v1/tickets/{id}/customer-context`

### 6P5 — Bulk ticket actions & multilingual KB

| ID | Task | Status |
|----|------|--------|
| `phase6-21-bulk-tickets` | Bulk assign, status, priority, close, snooze from ticket list + API | done |
| `phase6-22-kb-locales` | Locale settings, article translations, portal language switcher | done |
| `phase6-23-kb-portal-filter` | Portal KB filtered by resolved locale (query, session, Accept-Language) | done |
| `phase6-24-kb-search-locale` | Vector/keyword KB search scoped to active locale | done |

**API:** `POST /api/v1/tickets/bulk`, `GET/PUT /api/v1/knowledge/settings`

**Deploy:** `php artisan tenants:migrate` then `npm run build`

---

## Phase 7 — Service Desk (Paid ITSM Add-on)

Enterprise-only paid add-on (`service_desk` feature). Builds ITSM workflows on top of existing tickets, service catalog, assets, and SLA.

| Tier | Scope | Status |
|------|-------|--------|
| **7A — Foundation** | Feature gate, hub dashboard, type queues, nav | done |
| **7B — Approvals** | Reusable approval requests on catalog/change | done |
| **7C — Change & Problem** | Change records, problem linking, change calendar | done |
| **7D — Major Incidents** | Major incident mode, war-room, post-incident review | done |

### 7A — Foundation (done)

| ID | Task | Description |
|----|------|-------------|
| `phase7-01-feature-gate` | **Paid add-on** | `service_desk` feature on Enterprise; billing UI + upgrade page |
| `phase7-02-overview` | **Service desk hub** | `/service-desk` with open/unassigned counts per ITIL type |
| `phase7-03-type-queues` | **Type queues** | `/service-desk/queues/{type}` filtered ticket lists |
| `phase7-04-nav` | **Agent nav** | Service Desk section in sidebar; locked upgrade for non-Enterprise |

**Billing:** Enterprise plan includes `service_desk`. Professional retains `service_catalog` only.

**Configure:** Upgrade to Enterprise → Settings → Service catalog (per-item approvers) → Settings → Change approvals → `/service-desk/approvals`.

### 7B — Approvals (done)

| ID | Task | Description |
|----|------|-------------|
| `phase7-05-approvals` | **Approval engine** | Sequential approvers; catalog + change tickets; email/in-app; automation triggers |
| `phase7-05a-catalog-approval` | **Catalog approval** | Per-item `requires_approval` + approver list on service catalog items |
| `phase7-05b-change-approval` | **Change approval** | Global change-ticket approval settings at `/settings/service-desk/approvals` |
| `phase7-05c-approval-inbox` | **Approval inbox** | `/service-desk/approvals` queue with approve/reject actions |
| `phase7-05d-automation-hooks` | **Automation hooks** | Triggers `approval.approved` and `approval.rejected` |

**API:** `GET/PUT /api/v1/service-desk/approvals`, `POST .../approve`, `POST .../reject`, `GET /api/v1/tickets/{id}/approval`

### 7C — Change & Problem (done)

| ID | Task | Description |
|----|------|-------------|
| `phase7-06-change-mgmt` | **Change records** | Risk, schedule, CAB fields on change tickets; change calendar |
| `phase7-07-problem-mgmt` | **Problem records** | Root cause, known errors, linked incidents |

**API:** `GET /api/v1/service-desk/changes/calendar`, `GET/PUT /api/v1/tickets/{id}/change-record`, `GET/PUT /api/v1/tickets/{id}/problem-record`, `POST/DELETE /api/v1/tickets/{id}/problem-incidents`

### 7D — Major Incidents (done)

| ID | Task | Description |
|----|------|-------------|
| `phase7-08-major-incident` | **Major incident flag** | Declare on incident tickets; coordinator list |
| `phase7-08a-war-room` | **War room** | `/service-desk/major-incidents/{id}/war-room` with live conversation |
| `phase7-08b-post-review` | **Post-incident review** | Summary, timeline, lessons learned, action items |

**API:** `GET /api/v1/service-desk/major-incidents`, `GET/POST/PUT /api/v1/tickets/{id}/major-incident`, `POST .../resolve`, `POST .../complete-review`

**Deploy:** `php artisan tenants:migrate` then `npm run build`
