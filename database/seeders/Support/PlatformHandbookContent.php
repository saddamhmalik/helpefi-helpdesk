<?php

namespace Database\Seeders\Support;

use App\Domains\Knowledge\Support\PlatformKnowledge;

final class PlatformHandbookContent
{
    public static function articles($categoryIds, $collectionId, ?int $authorId): array
    {
        $published = [
            'is_published' => true,
            'is_public' => false,
            'is_system' => true,
            'published_at' => now(),
            'author_id' => $authorId,
            'knowledge_collection_id' => $collectionId,
        ];

        $articles = [
            [
                'section' => 'handbook-getting-started',
                'title' => 'Start here: your first 30 minutes',
                'slug' => 'handbook-start-here',
                'excerpt' => 'The fastest path from a new workspace to handling real tickets.',
                'body' => <<<'MD'
Welcome to helpefi. This guide walks you through every action in order — from first login to a live helpdesk.

## Recommended order

1. Complete the **setup wizard** at `/setup`
2. Set **timezone and business hours** (Settings → SLA)
3. Connect **inbound email** (Settings → Email)
4. Configure **outbound SMTP** so agents can reply
5. **Invite your team** (Settings → Agents)
6. Handle tickets from **Workspace** (`/workspace`) or **Tickets**
7. Remove **demo data** when you are ready to go live

## Fastest path (minimum clicks)

| Goal | Go to | Action |
|------|-------|--------|
| See your queue | **Support → Inbox** | Open the first ticket |
| Create a ticket | **+ New ticket** (top bar) | Fill form → Save |
| Change settings | **Settings** (sidebar footer) | Pick a section |
| Search anything | Press **⌘K** / **Ctrl+K** | Type ticket, customer, or setting name |

## Roles

- **Admin** — full settings, billing, team, email, SLA
- **Agent** — tickets, workspace, customers, knowledge
- **Customer** — portal only (`/portal`)

Keep this handbook open while you configure the workspace. Each article is a complete start-to-finish walkthrough for one task.

## Common questions

**How long does setup take?**
Most teams finish core setup (email, SLA, one agent) in under an hour. Use demo data to explore first.

**Do I need every article before going live?**
No. Minimum: business hours, one inbox, outbound SMTP, and at least one agent.

**Where do I get help?**
Use **How to use helpefi** (`/how-to`) anytime. See **Frequently asked questions** at the end of this handbook.
MD,
            ],
            [
                'section' => 'handbook-getting-started',
                'title' => 'Complete the setup wizard',
                'slug' => 'handbook-complete-setup-wizard',
                'excerpt' => 'Finish required workspace steps from the guided setup checklist.',
                'body' => <<<'MD'
New admins are guided to **Setup** until required steps are done.

## Open setup

1. Click **Setup** in the top bar (or go to `/setup`)
2. Review the checklist on the left
3. Complete each required step — optional steps can be skipped

## Required steps

### Business hours and timezone
- Opens **Settings → SLA**
- Set your workspace timezone and weekly schedule
- SLA timers only count time inside business hours

### Email
- Opens **Settings → Email**
- Add at least one inbox and connect inbound mail
- See the email articles in this handbook for OAuth, IMAP, or webhook

### Chat widget
- Opens widget embed settings
- Copy the script tag to your website

### Invite team
- Opens **Settings → Agents**
- Send invitations to agents who will handle tickets

### SLA policies
- Confirm default SLA policies exist for your priorities

## Mark setup complete

When all required steps show as done, click **Finish setup**. Admins are no longer redirected to `/setup` on login.

**Tip:** If you loaded sample demo data, finish exploring first, then remove demo data before going live (see **Remove demo data** in this handbook).

## Common questions

**Can I skip optional setup steps?**
Yes. Required steps must be done before **Finish setup**; optional steps can wait.

**Why am I redirected to Setup on login?**
Admins are sent to `/setup` until required steps are complete or setup is marked finished.

**Can agents access Setup?**
Setup is for admins. Agents use Workspace and Tickets once invited.
MD,
            ],
            [
                'section' => 'handbook-workspace-setup',
                'title' => 'Set timezone and business hours',
                'slug' => 'handbook-timezone-and-business-hours',
                'excerpt' => 'Configure when your team is open and how SLA clocks run.',
                'body' => <<<'MD'
Workspace timezone and business hours control SLA due times and reports.

## Steps

1. Go to **Settings → SLA & business hours** (`/settings/sla`)
2. Under **Business hours**, set the **Timezone** (e.g. `Asia/Kolkata`, `America/New_York`)
3. For each weekday, toggle **Open** and set start/end times
4. Add **Holidays** if needed (SLA pauses on those dates)
5. Click **Save**

## SLA policies (same page)

1. Review policies for each priority (Urgent, High, Normal, Low)
2. Set **First response** and **Resolution** targets in minutes or hours
3. Save changes

## Verify

- Create a test ticket and check the SLA panel on the ticket sidebar
- Due times should reflect your timezone and business hours

**Next:** Set your personal display timezone in **Settings → Profile** if you work in a different zone than the workspace.
MD,
            ],
            [
                'section' => 'handbook-workspace-setup',
                'step' => 4,
                'title' => 'Set your personal timezone',
                'slug' => 'handbook-personal-timezone',
                'excerpt' => 'Show ticket timestamps in your local time.',
                'body' => <<<'MD'
Each agent can override how dates and times display without changing workspace SLA settings.

## Steps

1. Go to **Settings → Profile** (`/settings/profile`)
2. Find **Timezone**
3. Select your local timezone
4. Click **Save**

All ticket timestamps, reports, and activity feeds now display in your timezone. SLA calculations still use workspace business hours.
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'step' => 5,
                'title' => 'Connect Google / Gmail with OAuth',
                'slug' => 'handbook-connect-email-oauth',
                'excerpt' => 'Sign in with Google — no app password required for inbound.',
                'body' => <<<'MD'
OAuth is the fastest way to receive support mail without storing passwords.

## Step-by-step

1. Go to **Settings → Email** (`/settings/email`)
2. Click **Connect an inbox** (or open an existing inbox)
3. Choose **Google / Gmail**
4. Enter the **Support address** and **Brand**
5. Click **Create and connect** (or **Connect** on an existing inbox)
6. Sign in with the Google account for that mailbox
7. Approve access when Google asks
8. Confirm **Connected as your@email.com** on the inbox card
9. Send a test email from another address to your support address
10. Click **Check for mail now**

## After connecting

- Unread messages in the Gmail **Inbox** import as tickets (about every minute)
- Replies thread when the subject contains `[HD-00042]`
- Configure **outbound SMTP** separately so agents can send reply emails

## Common questions

**Why is Google not listed?**
Your platform operator must enable Google OAuth. Contact them if the provider card is missing.

**Do I need a Google App Password?**
Not for OAuth inbound. You may need an app password only for **outbound SMTP** if you send via Gmail SMTP.

**Why was my test email not imported?**
It must be **unread** in the Inbox. Mark unread or send a new message, then click **Check for mail now**.

**Can I use Google Workspace?**
Yes — connect with the Workspace account that receives support mail.
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'title' => 'Connect email with IMAP or POP3',
                'slug' => 'handbook-connect-email-imap',
                'excerpt' => 'Poll a mailbox with an app password.',
                'body' => <<<'MD'
Use IMAP/POP3 when OAuth is not available for your mail provider.

## Steps

1. Go to **Settings → Email**
2. Open or create an inbox
3. Set **Inbound method** to **IMAP/POP3**
4. Choose a **Provider preset** (Gmail, Outlook, Yahoo, iCloud, or Custom)
5. Enter:
   - **Host** and **Port**
   - **Encryption** (SSL or TLS)
   - **Username** (usually the full email address)
   - **App password** (not your login password)
   - **Folder** (usually `INBOX` for IMAP)
6. Click **Save inbox**
7. Click **Test connection**
8. Click **Check for mail now**

## Gmail app password

1. Enable 2FA on your Google account
2. Google Account → Security → App passwords
3. Generate a password for "Mail"
4. Paste it in the inbox **App password** field

## Troubleshooting

- **Auth failed** — use an app password, not the regular password
- **No mail imported** — send a test email to the inbox address, then click **Check for mail now**
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'step' => 7,
                'title' => 'Configure outbound email (SMTP)',
                'slug' => 'handbook-outbound-email-smtp',
                'excerpt' => 'Send agent replies and system notifications to customers.',
                'body' => <<<'MD'
Inbound email creates tickets. Outbound SMTP sends agent replies and notifications.

## Steps

1. Go to **Settings → Email**
2. Open the **Outgoing email** tab
3. Enable **Outbound email**
4. Choose:
   - **Use inbox SMTP** — send from a connected inbox, or
   - **Custom SMTP** — enter host, port, encryption, username, password
5. Set **From address** and **From name**
6. Save
7. Use **Send test email** to verify

## Common SMTP settings (Zoho example)

| Field | Value |
|-------|-------|
| Host | `smtp.zoho.com` |
| Port | `587` |
| Encryption | TLS |
| Username | your full email |
| Password | Zoho app password |

## Verify end-to-end

1. Send an email to your support address → ticket created
2. Reply from the ticket in helpefi → customer receives email
3. Customer reply threads into the same ticket
MD,
            ],
            [
                'section' => 'handbook-team-and-automation',
                'step' => 8,
                'title' => 'Invite your team',
                'slug' => 'handbook-invite-team',
                'excerpt' => 'Add agents and assign roles.',
                'body' => <<<'MD'
## Steps

1. Go to **Settings → Agents** (`/settings/members`)
2. Click **Invite agent**
3. Enter **Email** and **Name**
4. Select **Role** (Agent or Admin)
5. Send invitation

## Accepting an invite

The agent receives an email with a link to `/invitations/{token}`. They set a password and land in the workspace.

## Optional: teams and departments

1. **Settings → Teams & departments** — create departments and teams
2. Assign agents to teams for routing and saved views
3. **Settings → Auto-assignment** — route new tickets by team or skill

## Optional: roles

**Settings → Roles & permissions** — create custom roles with granular access instead of full admin.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'step' => 9,
                'title' => 'Create your first ticket',
                'slug' => 'handbook-first-ticket',
                'excerpt' => 'Manually open a ticket in three clicks.',
                'body' => <<<'MD'
## Fastest path

1. Click **+ New ticket** in the top bar
2. Fill **Subject**, **Customer** (name + email), **Priority**
3. Click **Create**

## From the Tickets page

1. Go to **Support → Tickets**
2. Click **+ New ticket**
3. Complete the form and save

## After creation

- Open the ticket to reply, assign, change status, add watchers
- Link assets or organizations from the sidebar
- Upload attachments in the composer

Tickets created by email skip this flow — they appear automatically in your queue.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'step' => 10,
                'title' => 'Use the workspace inbox',
                'slug' => 'handbook-workspace-inbox',
                'excerpt' => 'Handle tickets faster with the split-pane queue.',
                'body' => <<<'MD'
The workspace is the fastest way to work tickets — queue on the left, conversation on the right.

## Open workspace

1. Go to **Support → Inbox** (`/workspace`)
2. Click a ticket in the queue
3. Read and reply in the composer on the right

## Quick actions (no extra page loads)

- Change **status**, **priority**, or **assignee** from the header dropdowns
- Use **internal note** for team-only messages
- **AI suggest reply** when AI is enabled (Enterprise)
- Drafts auto-save while you type

## Keyboard shortcut

Press **⌘K** / **Ctrl+K** anywhere to search tickets, customers, or settings.

**Tip:** Use Workspace for high volume; use **Tickets** for bulk filters, export, and saved views.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'step' => 11,
                'title' => 'Search and filter tickets',
                'slug' => 'handbook-search-and-filter-tickets',
                'excerpt' => 'Find tickets by subject, customer, status, or channel.',
                'body' => <<<'MD'
## Search and filter

1. Go to **Support → Tickets**
2. Type in **Search tickets** (matches subject, number, customer name/email)
3. Set **Status**, **Priority**, or **Assignee** filters
4. Click **Apply**

## More filters

Click **More** for channel, department, team, customer, date range, and **Watching only**.

## Save a view

1. Set your filters
2. Click **Save view**
3. Name it (e.g. "Unassigned email") — access it from the views bar above the table

## Export

Click **Export CSV** — exports the current filtered list.
MD,
            ],
            [
                'section' => 'handbook-daily-agent-work',
                'step' => 12,
                'title' => 'Manage customers and organizations',
                'slug' => 'handbook-customers-and-organizations',
                'excerpt' => 'Contacts, companies, and tags.',
                'body' => <<<'MD'
## Customers (contacts)

1. Go to **Directory → Customers** (`/contacts`)
2. Click **New customer** or open an existing record
3. View all tickets, notes, and activity on the contact profile

## Organizations

1. Go to **Directory → Organizations**
2. Create a company and add **email domains** for auto-linking
3. Contacts with matching domains attach automatically

## Tags

Apply tags (e.g. VIP) on contacts for filtering and automation conditions.

## From a ticket

Open any ticket → sidebar shows the customer → click through to the full profile.
MD,
            ],
            [
                'section' => 'handbook-workspace-setup',
                'step' => 13,
                'title' => 'Configure SLA policies',
                'slug' => 'handbook-sla-policies',
                'excerpt' => 'First-response and resolution targets by priority.',
                'body' => <<<'MD'
## Steps

1. Go to **Settings → SLA & business hours**
2. Under **SLA policies**, edit each priority row
3. Set **First response** time (e.g. 1 hour for Urgent)
4. Set **Resolution** time (e.g. 8 hours for Urgent)
5. Save

## What happens automatically

- New tickets get SLA timers based on priority
- Breaches show in red on tickets and dashboard
- Notifications fire when **Settings → Notifications** is configured

## Ticket statuses

Customize statuses at **Settings → Ticket statuses** (Open, Pending, Resolved, etc.).
MD,
            ],
            [
                'section' => 'handbook-email-and-channels',
                'step' => 14,
                'title' => 'Add the chat widget to your site',
                'slug' => 'handbook-chat-widget',
                'excerpt' => 'Embed live chat on your website.',
                'body' => <<<'MD'
## Steps

1. Go to **Settings → Ticket sources** (`/settings/channels`)
2. Ensure the **Web chat** channel is enabled
3. Copy the **Widget embed code**
4. Paste before `</body>` on your website
5. Save and publish your site

## Test

1. Open your website in a browser
2. Click the chat bubble
3. Submit a message — a ticket appears in **Workspace**

Complete the **Chat widget** step in `/setup` to mark setup progress.
MD,
            ],
            [
                'section' => 'handbook-team-and-automation',
                'step' => 15,
                'title' => 'Create automation rules',
                'slug' => 'handbook-automation-rules',
                'excerpt' => 'Auto-assign, tag, or change status on events.',
                'body' => <<<'MD'
Requires Professional plan or higher.

## Steps

1. Go to **Settings → Automation rules** (`/settings/automation`)
2. Click **New rule**
3. Choose a **Trigger** (ticket created, updated, customer message)
4. Add **Conditions** (priority, channel, subject contains, etc.)
5. Add **Actions** (assign agent, set status, add watcher, internal note)
6. Enable the rule and save

## Example

- **Trigger:** Ticket created
- **Condition:** Channel = Email
- **Action:** Assign to Support team + set priority Normal
MD,
            ],
            [
                'section' => 'handbook-team-and-automation',
                'step' => 16,
                'title' => 'Macros and notifications',
                'slug' => 'handbook-macros-and-notifications',
                'excerpt' => 'Canned replies and email alerts.',
                'body' => <<<'MD'
## Macros (canned replies)

1. Go to **Settings → Macros** (`/settings/macros`)
2. Create a macro with a shortcut name and reply body
3. In the ticket composer, insert a macro to paste the text instantly

## Notifications

1. Go to **Settings → Notifications** (admin)
2. Enable email alerts for:
   - Ticket assigned to you
   - Customer replied
   - SLA breach
3. Each agent can override preferences in **Settings → Profile**

Reduces clicks: macros replace retyping common answers; notifications remove manual queue checking.
MD,
            ],
            [
                'section' => 'handbook-customer-portal',
                'step' => 17,
                'title' => 'Publish your help center',
                'slug' => 'handbook-publish-help-center',
                'excerpt' => 'Customer-facing articles and self-service.',
                'body' => <<<'MD'
## Agent knowledge base

1. Go to **Content → Knowledge base** (`/knowledge`)
2. Create **Collections** and **Articles**
3. Set **Publish immediately** when ready

## Customer portal

1. Published collections marked **Public on portal** appear at `/portal`
2. Click **View portal** from the Knowledge page to preview
3. Share the Help Center link from the top bar with customers

## Locales

**Knowledge → Locales** — enable languages for multilingual help centers.

## Deflection

Published articles power AI deflection and Copilot search (when AI is enabled).
MD,
            ],
            [
                'section' => 'handbook-customer-portal',
                'step' => 18,
                'title' => 'Reports and CSAT',
                'slug' => 'handbook-reports-and-csat',
                'excerpt' => 'Measure volume, SLA, and satisfaction.',
                'body' => <<<'MD'
## Reports

1. Go to **Insights → Reports** (`/reports`)
2. Choose report type: Tickets, SLA breaches, Agent performance, CSAT
3. Set date range → **Run**
4. **Export CSV** or save the report for reuse

## CSAT surveys

1. Go to **Settings → CSAT surveys**
2. Enable post-resolution surveys
3. Customers rate closed tickets on the portal (1–5 stars)
4. View scores on ticket detail and in CSAT reports

## Dashboard

**Overview → Dashboard** shows open tickets, breaches, volume trend, and CSAT summary at a glance.
MD,
            ],
            [
                'section' => 'handbook-go-live',
                'step' => 19,
                'title' => 'Remove demo data and go live',
                'slug' => 'handbook-remove-demo-data',
                'excerpt' => 'Clear sample tickets and keep this handbook.',
                'body' => <<<'MD'
Demo data helps you explore the product. Remove it before handling real customers.

## What gets removed

- Sample tickets, contacts, teams, and tags
- Bootstrap demo ticket (HD-00001), demo inbox, and Acme organization
- Old demo knowledge articles (not this handbook)

## What stays

- **This How to use helpefi handbook** — permanent, never deleted
- Your real inboxes, SLA settings, team, and custom articles
- Admin account and workspace configuration

## Steps

1. Finish setup (email, team, SLA) using the articles above
2. Click **Remove** on the demo banner in the top bar
3. Confirm removal
4. You are redirected to **Setup** to complete any remaining steps

## After removal

- Start receiving real email into your connected inbox
- Use **How to use helpefi** in the sidebar anytime you need a walkthrough

This handbook is always available at **Content → How to use helpefi** (`/how-to`).
MD,
            ],
        ];

        $articles = array_merge($articles, PlatformHandbookExtendedArticles::articles());

        return array_map(function (array $article) use ($published, $categoryIds) {
            return array_merge($published, [
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'body' => trim($article['body']),
                'knowledge_category_id' => $categoryIds[$article['section']],
            ]);
        }, $articles);
    }
}
