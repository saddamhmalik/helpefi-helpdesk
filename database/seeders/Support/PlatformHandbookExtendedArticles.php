<?php

namespace Database\Seeders\Support;

final class PlatformHandbookExtendedArticles
{
    public static function articles(): array
    {
        return [
            [
                'section' => 'handbook-workspace-setup',
                'title' => 'Brands and custom domain',
                'slug' => 'handbook-brands-and-custom-domain',
                'excerpt' => 'Run multiple support brands and use your own domain.',
                'body' => <<<'MD'
Use brands when one workspace serves more than one product or company name.

## Create a brand

1. Go to **Settings → Brands** (`/settings/brands`)
2. Click **New brand**
3. Enter **Name**, **Slug**, and optional logo colors
4. Save

## Assign inboxes and tickets

1. Each **email inbox** belongs to one brand
2. New tickets inherit the inbox brand
3. Agents can filter tickets and reports by brand

## Custom domain (optional)

1. Go to **Settings → Custom domain** (`/settings/custom-domain`)
2. Enter your subdomain (e.g. `support.yourcompany.com`)
3. Add the DNS records shown on the page
4. Wait for verification (usually 5–30 minutes)
5. Once verified, agents and customers use your branded URL

## Common questions

**Do I need multiple brands?**
Single-brand teams can keep the default brand only. Add brands when you support multiple products or white-label clients.

**Will email still work after adding a custom domain?**
Yes. Inbound and outbound email use inbox settings, not the browser domain.

**Can customers see different portals per brand?**
Public help center collections can be scoped per brand when you publish articles.
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'title' => 'Connect Microsoft / Outlook with OAuth',
                'slug' => 'handbook-connect-email-microsoft-oauth',
                'excerpt' => 'Sign in with Microsoft 365 or Outlook.com — no app password for inbound.',
                'body' => <<<'MD'
Use this when your support mailbox runs on Microsoft 365, Exchange Online, or Outlook.com.

## Before you start

- You need admin access to the mailbox that receives support mail
- Your workspace must show **Microsoft / Outlook** in **Settings → Email**

## Step-by-step

1. Go to **Settings → Email** (`/settings/email`)
2. Click **Connect an inbox** (or open an existing inbox)
3. Choose **Microsoft / Outlook**
4. Enter the **Support address** (e.g. `support@yourcompany.com`) and **Brand**
5. Click **Create and connect** (or **Connect** on an existing inbox)
6. Sign in with the Microsoft account for that mailbox
7. Approve the permissions requested
8. Confirm **Connected as your@email.com** appears on the inbox card
9. Send a test email from another address to your support address
10. Click **Check for mail now** on the inbox

## Outbound replies

OAuth covers **inbound** mail only. To send replies from helpefi:

1. Open **Settings → Email → Outgoing email**
2. Enable outbound and configure SMTP for Microsoft (or use inbox SMTP if available)

## Common questions

**Which Microsoft accounts work?**
Work and school accounts (Microsoft 365) and personal Outlook.com accounts.

**Why is Microsoft not listed as a provider?**
Your platform operator must enable Microsoft OAuth on the server. Contact them if the card is missing.

**Mail is connected but no tickets appear?**
Send a **new unread** message to the inbox address, wait one minute, then click **Check for mail now**. Only unread Inbox messages are imported.

**Can I connect a shared mailbox?**
Use an account with access to the shared mailbox and connect with that account, or use IMAP with an app password instead.
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'title' => 'Email forwarding and webhooks',
                'slug' => 'handbook-email-forwarding-webhook',
                'excerpt' => 'Receive mail by forwarding to a unique helpefi address.',
                'body' => <<<'MD'
Use forwarding when you cannot use OAuth or IMAP but can forward mail from your provider.

## Steps

1. Go to **Settings → Email**
2. Click **Connect an inbox**
3. Choose **Email forwarding**
4. Enter inbox name, support address, and brand
5. Save — helpefi shows a **unique forwarding address** (e.g. `inbound+…@…`)
6. In your mail provider (Google Admin, Microsoft 365, Zoho, etc.), create a rule:
   - **When:** mail arrives at `support@yourcompany.com`
   - **Then:** forward to the helpefi forwarding address
7. Send a test email and confirm a ticket is created

## Webhook (advanced)

Some providers support HTTP webhooks instead of forwarding. Use the webhook URL and secret shown on the inbox card and configure your provider to POST new messages to that URL.

## Common questions

**Does forwarding keep threading?**
Yes, when subjects include the ticket number `[HD-00042]` on replies.

**Is forwarding less reliable than OAuth?**
OAuth or IMAP polling is preferred when available. Forwarding depends on your provider’s forward rules.

**Can I forward from multiple addresses to one inbox?**
Create separate inboxes or forward multiple addresses to the same helpefi forwarding address.
MD,
            ],
            [
                'section' => 'handbook-team-and-automation',
                'title' => 'Roles and permissions',
                'slug' => 'handbook-roles-and-permissions',
                'excerpt' => 'Control who can change settings, billing, and tickets.',
                'body' => <<<'MD'
helpefi ships with **Admin** and **Agent** roles. Admins can create custom roles with granular permissions.

## View default roles

1. Go to **Settings → Roles & permissions** (`/settings/roles`)
2. Review **Admin** and **Agent** permission sets

## Create a custom role

1. Click **New role**
2. Enter a name (e.g. "Team lead", "Billing only")
3. Toggle permissions by area:
   - Tickets (view, reply, assign, delete)
   - Settings (email, SLA, automation, billing)
   - Reports and audit logs
4. Save

## Assign a role

1. Go to **Settings → Agents**
2. Open an agent → change **Role**
3. Or set role when sending an invitation

## Common questions

**Should every agent be an admin?**
No. Give admin only to people who manage settings, billing, and team. Most agents need the Agent role only.

**Can agents see all tickets?**
By default yes. Restrict with departments, teams, or custom roles if your plan supports it.

**Who can remove demo data or change billing?**
Usually admins only. Check **Roles & permissions** if you use custom roles.
MD,
            ],
            [
                'section' => 'handbook-team-and-automation',
                'title' => 'Auto-assignment and routing',
                'slug' => 'handbook-auto-assignment',
                'excerpt' => 'Route new tickets to the right team or agent automatically.',
                'body' => <<<'MD'
Reduce manual triage by assigning tickets when they are created.

## Prerequisites

1. Create **Teams** under **Settings → Teams & departments**
2. Add agents to teams
3. Optional: define **Skills** under **Settings → Skills**

## Configure auto-assignment

1. Go to **Settings → Auto-assignment** (`/settings/assignment`)
2. Choose a strategy:
   - **Round robin** — rotate among team members
   - **Load balanced** — assign to agent with fewest open tickets
   - **By team** — map channel or inbox to a team
3. Set fallback when no one is available
4. Save

## Combine with automation

Use **Settings → Automation rules** to assign by priority, channel, or subject keywords for more control.

## Common questions

**Why are tickets still unassigned?**
Check that the team has active agents, auto-assignment is enabled, and no automation rule overrides assignment.

**Can I assign by brand or inbox?**
Use automation rules with conditions on inbox or brand, then set assignee or team as the action.

**Does auto-assignment work on email tickets?**
Yes, for tickets created from email, chat, portal, or manual creation depending on your rules.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'title' => 'Reply, notes, and attachments',
                'slug' => 'handbook-reply-and-internal-notes',
                'excerpt' => 'Customer replies vs internal notes, drafts, and files.',
                'body' => <<<'MD'
Every ticket conversation supports public replies and private internal notes.

## Reply to the customer

1. Open a ticket in **Workspace** or **Tickets**
2. In the composer, ensure **Reply** is selected (not Internal note)
3. Type your message — drafts save automatically
4. Optional: attach files with the paperclip icon
5. Click **Send**

The customer receives email if outbound mail is configured. The reply appears on the portal if they use it.

## Internal note (team only)

1. Switch the composer to **Internal note**
2. Type your message and send
3. Customers never see internal notes — only agents

Use notes for handoffs, investigation details, or manager review.

## Macros and AI

- **Macros** — insert canned text from **Settings → Macros**
- **AI suggest reply** — when enabled, generate a draft from the conversation

## Common questions

**Why did the customer not receive my reply?**
Check **Settings → Email → Outgoing email** is enabled and test SMTP. OAuth inbound alone does not send outbound mail.

**Can I edit a sent reply?**
You can add follow-up messages; editing sent email content depends on your workflow — add a correction as a new reply if needed.

**What file types can I attach?**
Most common images and documents. Very large files may be blocked by your server limit.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'title' => 'Ticket statuses and priorities',
                'slug' => 'handbook-ticket-statuses-priorities',
                'excerpt' => 'Open, pending, resolved — and when to use each priority.',
                'body' => <<<'MD'
Statuses track workflow. Priorities drive SLA timers and queue order.

## Change on a ticket

1. Open the ticket
2. Use **Status** and **Priority** dropdowns in the header (Workspace or ticket detail)
3. Changes save immediately and appear in activity history

## Default statuses

| Status | When to use |
|--------|-------------|
| **Open** | New or actively worked |
| **Pending** | Waiting on customer or third party |
| **Resolved** | Fix delivered; awaiting confirmation |
| **Closed** | Done — no further action |

Customize names and colors at **Settings → Ticket statuses**.

## Priorities

| Priority | Typical use |
|----------|-------------|
| **Urgent** | Outage, security, VIP blocked |
| **High** | Same-day business impact |
| **Normal** | Standard requests |
| **Low** | Questions, nice-to-have |

SLA targets are set per priority under **Settings → SLA**.

## Common questions

**Does resolving stop the SLA clock?**
Resolution SLA is met when status becomes Resolved (or Closed, depending on policy). Check your SLA settings.

**Can I add custom statuses?**
Yes — **Settings → Ticket statuses**. Keep a clear path from Open → Resolved for reporting.

**Who can change priority?**
Agents and admins by default. Custom roles may restrict this.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'title' => 'AI Copilot and suggested replies',
                'slug' => 'handbook-ai-copilot',
                'excerpt' => 'Search faster and draft replies with AI when enabled on your plan.',
                'body' => <<<'MD'
AI features help agents find answers and draft replies. Availability depends on your subscription and admin settings.

## Global search (Copilot)

1. Press **⌘K** / **Ctrl+K** anywhere in the app
2. Type a question or ticket subject
3. Copilot searches tickets, customers, settings, and knowledge articles
4. Open a result directly from the search panel

## Suggested reply on a ticket

1. Open a ticket in **Workspace**
2. Click **Suggest reply** (or similar) in the composer when AI is enabled
3. Review the draft — edit before sending
4. Send as a normal customer reply

## Knowledge deflection

Published help center articles improve AI answers. Keep your knowledge base up to date under **Content → Knowledge base**.

## Admin setup

1. **Settings → AI** — enable features and set usage limits if shown
2. Ensure articles are published for deflection

## Common questions

**Why don’t I see Suggest reply?**
Your plan may not include AI, or an admin disabled it under **Settings → AI**.

**Does AI send messages automatically?**
No. Agents always review and send manually unless you configure automation separately.

**Is customer data sent to AI providers?**
Follow your organization’s policy. Admins configure AI in settings according to your plan.
MD,
            ],
            [
                'section' => 'handbook-go-live',
                'title' => 'Billing, plans, and add-ons',
                'slug' => 'handbook-billing-and-plans',
                'excerpt' => 'Trials, upgrades, seats, and invoices.',
                'body' => <<<'MD'
Workspace billing is managed by admins under **Settings → Billing & plans**.

## View your plan

1. Go to **Settings → Billing & plans** (`/settings/billing`)
2. See current plan, trial days remaining, and seat usage
3. Review included features vs add-ons

## Upgrade or change plan

1. On the billing page, compare plans
2. Select a new plan and confirm checkout (Razorpay or your configured provider)
3. New features activate after successful payment

## Add-ons and seats

1. Open **Add-ons** on the billing page if available
2. Enable extras (AI, additional seats, etc.)
3. Changes apply according to your subscription terms

## Common questions

**What happens when the trial ends?**
You may enter read-only or grace mode until you subscribe. The billing page shows exact status.

**Who can access billing?**
Admins only by default. Custom roles can grant billing view separately.

**Can I get invoices?**
Download or email invoices from the billing portal when your payment provider supports it.

**How do I cancel?**
Use **Cancel subscription** on the billing page. Data retention follows your platform’s policy.
MD,
            ],
            [
                'section' => 'handbook-troubleshooting',
                'title' => 'Email not creating tickets',
                'slug' => 'handbook-troubleshooting-email',
                'excerpt' => 'Fix inbound mail when messages never become tickets.',
                'body' => <<<'MD'
Work through these checks in order when email should create tickets but does not.

## Checklist

1. **Inbox is active** — **Settings → Email** → inbox toggle **Active** is on
2. **OAuth connected** — shows “Connected as …” for OAuth inboxes
3. **Test message is unread** — Gmail OAuth imports unread Inbox mail only; read messages are skipped
4. **Poll manually** — click **Check for mail now** and wait 30 seconds
5. **Correct address** — you emailed the exact address on the inbox card, not an alias that is not forwarded
6. **Outbound separate** — missing replies does not mean inbound failed; check inbound first

## OAuth-specific

| Symptom | What to try |
|---------|-------------|
| Connect button missing | Provider not enabled on platform — contact operator |
| Error after Google sign-in | Try again; ensure you approved all permissions |
| Connected but no mail | New unread test to Inbox; click **Check for mail now** |

## IMAP-specific

| Symptom | What to try |
|---------|-------------|
| Auth failed | Use app password, not login password |
| Connection timeout | Check host, port, SSL/TLS, firewall |
| No new mail | Confirm folder is `INBOX` |

## Common questions

**How often does helpefi check mail?**
About every minute for OAuth and IMAP inboxes when the queue is running.

**Duplicate tickets from one email?**
Usually a threading issue — ensure replies keep `[HD-00042]` in the subject.

**Can I see poll errors?**
Yes — open the inbox in **Settings → Email**; errors show on the inbox card after a failed poll.
MD,
            ],
            [
                'section' => 'handbook-troubleshooting',
                'title' => 'Tickets, SLA, and assignment issues',
                'slug' => 'handbook-troubleshooting-tickets-sla',
                'excerpt' => 'When SLAs look wrong or tickets behave unexpectedly.',
                'body' => <<<'MD'
## SLA due times look wrong

1. Confirm **Settings → SLA → Timezone** matches your business
2. Confirm **Business hours** include the days/times you expect
3. Check ticket **Priority** — SLA targets are per priority
4. **Holidays** pause SLA — review the holiday list
5. Remember: SLA counts business hours, not 24/7 wall clock (unless configured otherwise)

## Ticket not assigned

1. Check **Settings → Auto-assignment** is enabled
2. Confirm agents are on the target team and active
3. Review **Automation rules** — another rule may clear assignee
4. Manually assign from the ticket header to unblock the customer

## Cannot find a ticket

1. Press **⌘K** / **Ctrl+K** and search by number (`HD-00042`) or email
2. Clear filters on **Support → Tickets**
3. Check **Status** filter — resolved tickets may be hidden
4. Search by customer under **Directory → Customers**

## Common questions

**Why did SLA breach overnight?**
If business hours are Mon–Fri 9–5, timers pause outside that window. Breaches show when targets are missed during open hours.

**Can I reopen a closed ticket?**
Change status back to Open from the ticket header.

**Who deleted my ticket?**
Admins can check **Settings → Audit logs** if enabled on your plan.
MD,
            ],
            [
                'section' => 'handbook-troubleshooting',
                'title' => 'Frequently asked questions',
                'slug' => 'handbook-frequently-asked-questions',
                'excerpt' => 'Quick answers to the most common workspace questions.',
                'body' => <<<'MD'
## Getting started

**Where do I start after signup?**
Open **Setup** (`/setup`) or read **Start here: your first 30 minutes** in this handbook.

**What is demo data?**
Sample tickets and customers to explore the product. Remove via the demo banner before going live.

**Where is this handbook later?**
**Content → How to use helpefi** or `/how-to` — it is never deleted with demo data.

## Email

**Do customers see my OAuth login?**
No. Only your agent connects OAuth once per inbox in Settings.

**Can one inbox serve multiple brands?**
Each inbox belongs to one brand. Create multiple inboxes for multiple brands.

**Why must I configure SMTP separately?**
Inbound (OAuth/IMAP) and outbound (SMTP) are separate mail paths. Many teams use OAuth for receiving and SMTP for sending.

## Tickets and workspace

**Workspace vs Tickets page?**
Workspace is a split-pane inbox for speed. Tickets is the full list with filters, views, and export.

**How do customers open tickets?**
Email to your support address, chat widget, customer portal, or agent-created tickets.

**What is an internal note?**
A team-only message on a ticket — never emailed to the customer.

## Team and security

**How do I reset an agent password?**
Agents use **Forgot password** on login, or admins re-invite from **Settings → Agents**.

**Can I restrict who sees billing?**
Use **Settings → Roles & permissions** to limit billing access to specific roles.

**Is two-factor authentication available?**
Check **Settings → Security** for 2FA requirements for your workspace.

## Portal and knowledge

**How do customers access the portal?**
Share your workspace portal URL (e.g. `https://your-workspace.example.com/portal`).

**Why is my article not on the portal?**
Article must be **Published** and the collection marked **Public on portal**.

## Still stuck?

1. Re-read the guide for your task in this handbook
2. Ask an admin to check **Settings → Platform feedback** if available
3. Contact your helpefi platform operator for server-side issues (OAuth, domain, billing)
MD,
            ],
        ];
    }
}
