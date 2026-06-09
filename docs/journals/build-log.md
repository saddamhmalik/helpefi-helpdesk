# Build Log

## 2026-06-07 — Rebuild (single-tenant MVP)

- Wiped multitenancy codebase
- Laravel 13 + Inertia + Vue 3 + SQLite
- Modules: Auth, Dashboard, Contacts, Tickets, Knowledge

## 2026-06-07 — Module 01: Auth & Team

- Invitations table + accept flow
- Admin member management (invite, role, remove)
- Profile and password settings
- Plan updated: removed multitenancy

## 2026-06-07 — Module 02: Contacts & Organizations

- Sidebar layout (desktop + mobile)
- Organizations CRUD with email domains
- Contact tags, notes, activity timeline
- Domain-based auto-linking for contacts

## 2026-06-07 — Module 03: Ticketing Enhancements

- Attachments, watchers, merge/split
- Saved ticket views with filters
- Collapsible sidebar (localStorage)
- REST API v1 + Postman collection in `postman/`

## 2026-06-07 — Module 05: Knowledge Portal

- Collections CRUD + article assignment
- Version snapshots on edit with restore
- Public portal: browse, search, submit, track
- Portal + knowledge API routes in Postman

## 2026-06-07 — Module 06: SLA + Customer Portal Auth

- SLA policies, business-hours due dates, breach job
- Admin SLA settings, timer UI on tickets
- Customer portal login/register and My Tickets
- Demo customer: customer@example.com / password

## 2026-06-07 — Module 08: Agent Workspace

- Split-pane workspace at `/workspace` with queue filters and saved views
- Composer with draft auto-save, Cmd/Ctrl+Enter send, internal notes
- 5s polling for messages and queue updates
- Quick status/priority/assignee updates without full page reload
- Workspace web + API routes and Postman folder

## 2026-06-07 — Module 09: Reports

- Dashboard widgets: weekly stats, volume trend, priority/agent breakdown
- Reports at `/reports` with tickets, SLA breaches, agent performance
- Saved reports and CSV export
- Reports web + API routes in Postman

## 2026-06-07 — Module 04: Omnichannel

- Channels table with web, portal, email, API sources
- Ticket/message channel tracking and contact-authored email replies
- Inbound email API with token auth and `[HD-00001]` threading
- Admin channel settings UI

## 2026-06-07 — Module 07: Workflow Automation

- Automation rules with triggers, conditions, and actions
- Event-driven execution on ticket create/update and customer messages
- Admin automation builder at `/settings/automation`
- Automation API routes in Postman

## 2026-06-07 — Module 10: Integrations

- Outbound webhooks with HMAC-SHA256 signed payloads
- Events: ticket created, updated, customer message
- Delivery log, test send, secret regeneration
- Admin UI at `/settings/integrations` + API routes in Postman

## 2026-06-07 — Module 11: AI Layer

- Suggested replies, ticket summaries, KB article assist
- OpenAI when configured; local fallback for dev/tests
- AI panel in workspace and ticket views
- Admin settings at `/settings/ai` + API routes in Postman

## 2026-06-07 — Module 12: ITSM Service Catalog

- Service categories and catalog items with custom form fields
- Ticket types and catalog linkage on tickets
- Portal service catalog and structured request submission
- Admin UI at `/settings/service-catalog` + API routes in Postman

## 2026-06-07 — Module 13: Assets & CMDB

- Asset inventory with types, statuses, and AST tags
- Contact assignment and parent/child asset hierarchy
- Ticket linkage from ticket detail view
- Agent UI at `/assets` + API routes in Postman

## 2026-06-07 — Module 14: Billing & Plan Enforcement

- Subscription with Starter / Professional / Enterprise tiers
- Usage limits for agents and monthly tickets
- Feature gates on AI, integrations, automation, assets, and nav items
- Admin UI at `/settings/billing` + API routes in Postman

## 2026-06-07 — Module 15: Security Hardening

- TOTP two-factor auth with recovery codes on profile settings
- MFA challenge on login and optional agent-wide MFA requirement
- Audit log with admin observability dashboard
- Retention purge job for audit logs and closed tickets
- Admin UI at `/settings/security` + API routes in Postman

## 2026-06-07 — Module 16: Notifications

- In-app inbox with bell icon and unread count in agent header
- Email alerts for assignments, customer replies, and SLA breaches
- Admin settings at `/settings/notifications` + API routes in Postman

## 2026-06-07 — Module 17: CSAT

- Post-resolution 1–5 star surveys on portal (authenticated and guest track)
- Admin settings at `/settings/csat` with optional required comment
- CSAT report type, dashboard widget, and agent ticket detail view
- API routes in Postman

## 2026-06-07 — Email settings (inbound + outbound)

- Multiple inbound mailboxes at `/settings/email` with per-inbox tokens
- Inbound webhook creates tickets and threads replies by `[HD-xxxxx]`
- Outbound SMTP settings with customer reply emails on agent responses
- API routes for inbox and outbound management

## 2026-06-07 — Asset network discovery

- Local network scan at `/assets/discovery` (ARP + ping on /24 subnet)
- Auto-detects server subnet; import discovered devices into CMDB
- Assets track IP, MAC, hostname, and last seen timestamp
- Matches existing assets by MAC/IP before import

## 2026-06-07 — Roles & permissions

- Admin role management at `/settings/roles` with permission catalog
- Create custom roles and assign granular permissions
- Team invites use any assignable role; custom roles with `access.agent` can use the portal
- Default permissions seeded for admin and agent roles

## 2026-06-07 — Product knowledge base

- `ProductKnowledgeSeeder` with 12 published articles covering the full platform
- Collections: product guide, agent handbook, customer self-service
- Categories: product documentation, agent training, customer help
- Topics: overview, tickets/SLA, contacts/KB, email/automation, AI/assets, reports/CSAT, admin/API, customer portal guides
- Registered in `DatabaseSeeder` after admin user creation
- Portal article page renders markdown with typography (`KnowledgeBody`, `@tailwindcss/typography`, `marked`)

## 2026-06-07 — Mailbox polling (IMAP/POP3)

- Per-inbox polling for Gmail, Outlook, Yahoo, iCloud, and custom IMAP/POP3
- Scheduled command `channels:poll-inboxes` runs every minute
- Settings UI at `/settings/email` with provider presets, test connection, and poll now
- Reuses `ChannelService::processInboundEmail` for ticket create/reply threading
- Native PHP IMAP when `ext-imap` is available; socket fallback otherwise

## 2026-06-07 — OAuth mailbox (Google, Microsoft, Zoho)

- Sign-in flow for Gmail (Gmail API), Outlook (Microsoft Graph), and Zoho Mail
- OAuth tokens stored encrypted per inbox; automatic refresh before polling
- Settings UI: Connect / Disconnect buttons alongside webhook and manual IMAP
- Env vars: `GOOGLE_MAIL_*`, `MICROSOFT_MAIL_*`, `ZOHO_MAIL_*`
