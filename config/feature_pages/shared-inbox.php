<?php

return array (
  'nav_label' => 'Shared Inbox',
  'badge' => 'Shared inbox software',
  'hero_title' => 'One shared inbox for your whole support team',
  'hero_highlight' => 'Email, chat, and portal in one place',
  'hero_subtitle' => 'Stop juggling personal inboxes and forwarded threads. Helpefi gives every agent a unified shared inbox with assignment, collision detection, and full conversation history.',
  'features' =>
  array (
    0 =>
    array (
      'title' => 'Unified team workspace',
      'body' => 'Every channel creates a ticket in one inbox so nothing gets lost in individual email accounts.',
    ),
    1 =>
    array (
      'title' => 'Collision detection',
      'body' => 'See when another agent is viewing or replying so customers never get duplicate responses.',
    ),
    2 =>
    array (
      'title' => 'Internal notes and @mentions',
      'body' => 'Collaborate on complex tickets privately without cluttering the customer thread.',
    ),
  ),
  'intro' => 'A shared inbox is the foundation of professional support. When every agent reads support@ from a personal Gmail tab, customers get duplicate replies, managers cannot see queue depth, and SLAs exist only in spreadsheets. Helpefi replaces that chaos with one team workspace where email, live chat, SMS, and portal submissions become tickets with assignment, status, and full history. Agents see who is viewing a conversation before they hit send. Leaders see workload by person, team, and priority without exporting threads.

The shift from forwarded email to a shared inbox is operational, not cosmetic. Tickets carry tags, customer records, and SLA timers from the moment they arrive. Macros and automation apply consistently because the system—not an individual inbox—owns the conversation. Internal notes and @mentions let engineers and account managers collaborate without exposing side conversations to customers. When chat ends, the transcript stays on the same ticket if email follow-up is needed.

Helpefi shared inbox is omnichannel by default. You do not buy a separate product for chat or portal requests. Collision detection works across channels so two agents never answer the same live chat or email thread unaware of each other. Multi-inbox support connects Gmail, Microsoft 365, and Zoho Mail—or forward any address—while customers still receive replies from your domain. Multi-brand workspaces route tickets to the right queue with separate SLAs and portals when agencies or product lines need isolation.

Migration from shared Gmail, Outlook folders, or legacy helpdesks is a common starting point. Helpefi setup wizard connects your first inbox in minutes during trial. Parallel runs let you forward new mail while keeping the old system read-only until macros, SLAs, and automations are validated. This guide covers assignment models, collision etiquette, channel handoffs, and how shared inbox pairs with AI Copilot, knowledge deflection, and optional Service Desk ITSM when employee requests share the same operation.

Security and compliance reviewers increasingly ask how shared access avoids credential sprawl. Helpefi uses role-based permissions instead of sharing one support@ password in a team vault. Agents authenticate individually while working the same queue, producing audit trails that show who replied and when. Attachments stay on the ticket timeline with access controls appropriate to your plan. For agencies, database-per-tenant isolation means client A tickets never share a datastore boundary with client B even when the same agents serve both brands from filtered views.',
  'deep_dives' =>
  array (
    0 =>
    array (
      'title' => 'Why personal inboxes fail at scale',
      'body' => 'Personal inboxes work until they do not—usually the week two agents reply to the same urgent refund request with different answers. Forwarding rules hide ownership: everyone sees the thread, nobody knows who owes the next reply. Vacation coverage becomes archaeology in Sent folders. Managers ask for backlog counts and receive guesses.

Shared inbox software fixes ownership at the ticket level. Every conversation has status, assignee, priority, and timestamps auditors can trust. Helpefi shows queue depth in real time so staffing decisions use data, not Slack polls. Customers experience one support team even when ten agents rotate shifts.

The cultural shift matters too. Agents stop hoarding relationships in private mailboxes when the team succeeds together on metrics like first response and resolution time. Sales and success teams gain visibility into open issues without being CC’d on everything. Finance can trace billing disputes to ticket IDs instead of email subjects that change every reply.

If you are still on shared Gmail, count how many hours per week go to "did you see this?" messages. That tax is the business case for Helpefi. Trials include full platform access so you can connect one inbox, invite two agents, and compare duplicate-reply incidents for two weeks—evidence leadership understands faster than feature checklists.',
    ),
    1 =>
    array (
      'title' => 'Connecting email, chat, SMS, and portal channels',
      'body' => 'Omnichannel support fails when channels live in separate tools with separate histories. Helpefi unifies them in one inbox. Email arrives via OAuth-connected Gmail or Microsoft 365, Zoho Mail, or forwarding from any provider. Live chat embeds on your site and opens tickets agents handle beside email. SMS via Twilio creates tickets with the same assignment rules. Portal submissions from your branded help center land in the same queues.

Each channel respects threading. Email replies stay grouped by conversation ID, not scattered by subject-line edits customers make. Chat transcripts attach to the ticket if the customer later emails from a different device. Portal forms capture custom fields upfront so agents do not re-ask for account IDs.

Channel-aware routing sends chat to agents marked online while email flows to specialists by product or language. Business hours on chat widgets set expectations when your team is offline; messages become tickets instead of disappearing. Consistent SLA policies apply regardless of entry channel, so leadership compares apples to apples in reporting.

Start rollout with email plus one additional channel—usually chat or portal—before enabling SMS. Validate assignment and collision workflows on two channels, then expand. Helpefi setup wizard walks through DNS and widget steps without requiring developers for basic deployments.',
    ),
    2 =>
    array (
      'title' => 'Assignment, queues, and workload balance',
      'body' => 'An empty shared inbox is easy; a fair one at fifty tickets per hour is craft. Helpefi supports assignment by team, individual agent, round-robin, and skill-based rules triggered by tags or customer tier. Unassigned views show what needs triage; personal views show what each agent owns. Managers reassign during absences without losing history.

Round-robin works for homogeneous queues—general SaaS support with similar handle times. Skill routing fits when billing, technical, and language specialists exist. Enterprise customer tiers may bypass round-robin entirely and land on named account owners. Document rules in a runbook so new leads do not improvise during incidents.

Workload balance is more than ticket count. Weight by priority and SLA proximity: ten low-priority how-tos differ from three breaches due in an hour. Helpefi dashboards highlight agents approaching capacity and tickets nearing breach. Pair assignment automation with escalation rules so stuck tickets surface before customers churn.

Review assignment weekly in the first month after launch. Tags drift, products launch, and seasonal spikes expose bad defaults. Small rule tweaks beat heroic agent effort every time.

During incidents, temporarily simplify assignment: one coordinator owns triage while others reply. Revert to standard rules after stability returns so everyday fairness returns. Document incident assignment overrides in postmortems so the next launch benefits.',
    ),
    3 =>
    array (
      'title' => 'Collision detection and reply discipline',
      'body' => 'Collision detection shows when another agent is viewing or actively replying to the same ticket. That single indicator prevents the embarrassing double-reply customers tweet about. In live chat, collisions are even more critical because response windows are seconds, not hours.

Establish team norms: if you see a collision warning, communicate in an internal note or @mention before sending. For complex tickets, assign explicitly instead of relying on whoever opens first. Leads should coach juniors to check assignee fields before macro spamming.

Collision pairs with status hygiene. Tickets in "pending customer" should not sit in personal mental backlogs—status tells the team who waits on whom. Snoozing or scheduled follow-ups reduce the urge to keep dozens of tabs open "just in case."

During high-volume incidents, temporary triage roles help: one agent coordinates assignments while others reply. Helpefi internal notes keep that coordination out of customer view. Post-incident reviews should ask whether collisions or status gaps caused duplicate work—not only whether resolution was fast.',
    ),
    4 =>
    array (
      'title' => 'Internal notes, @mentions, and cross-team collaboration',
      'body' => 'Customer-visible replies are not the place to debate refund policy with finance. Internal notes on the ticket timeline let agents ask legal, loop in engineering, or document manager approvals without forwarding email chains. @mentions notify specific teammates so questions do not get lost in channel noise.

Engineering escalations benefit when reproduction steps and error IDs live on the ticket linked to Jira or Linear issues. Account managers see context when success teams @mention them on renewal-risk tickets. The shared inbox becomes the system of record—not Slack threads that disappear in scrollback.

Set permissions so only appropriate roles see sensitive notes on HR or security tickets. Optional Service Desk ITSM extends the same collaboration model to employee requests with approval chains. Customer and internal work stay in one platform with different visibility rules.

Coach agents to write notes future shifts can understand. "Called finance" is useless at 2 a.m.; "Finance approved partial refund per policy 4.2, case #8821" saves hours. Good notes make AI Copilot summaries more accurate too because context is structured.',
    ),
    5 =>
    array (
      'title' => 'Multi-brand and multi-inbox operations',
      'body' => 'Agencies and multi-product companies often operate several support addresses and portals. Helpefi multi-brand workspaces route each inbox and portal to the correct queue, SLA policy, and knowledge collection. Customers see the right brand; agents work one unified inbox with filters instead of logging into five tools.

Database-per-tenant isolation gives agencies hard boundaries between client data—important in security reviews and offboarding. Custom domains on Professional make each portal client-ready without generic vendor URLs. Assignment rules can tag tickets by brand automatically so reporting stays clean.

Shared macros should be branded carefully: insert the correct signature and policy links per brand. Test cross-brand collisions—agents supporting multiple clients need visual cues which portal a customer used. Color tags and views reduce mistakes.

When one brand spikes during a launch, temporary reassignment across brands is easier in one Helpefi workspace than juggling separate legacy accounts. Leaders see aggregate capacity while brand managers keep SLA reports separate.',
    ),
    6 =>
    array (
      'title' => 'Migrating from Gmail, Outlook, or another helpdesk',
      'body' => 'Migration anxiety keeps teams on broken inboxes longer than necessary. Helpefi supports incremental moves. Connect your support address via forwarding or OAuth, invite agents, and run new mail through Helpefi while old threads remain read-only in the previous system. Most teams reach confidence in one to two weeks of parallel operation.

Import open and recent closed tickets when leaving Zendesk, Freshdesk, or Help Scout so agents retain context. Map tags and custom fields that drive SLAs and automations—do not import ten years of clutter. Rebuild macros as Helpefi macros with multi-actions instead of copying brittle text fragments.

Train agents on three habits: assign or claim tickets, use internal notes instead of side email, and trust collision indicators. Lunch-and-learn beats a fifty-page manual. Measure duplicate replies and average first response time before and after cutover—leadership sponsors continue when numbers move.

Dedicated migration landing pages document vendor-specific steps. Support can advise on DNS, portal cutover, and chat widget timing. The goal is not a big-bang weekend risk but a controlled shift where customers notice faster replies, not a new tool.',
    ),
    7 =>
    array (
      'title' => 'Shared inbox with SLA, AI, and reporting',
      'body' => 'Inbox unification is step one; operating discipline is step two. Helpefi SLA policies attach timers to every ticket in the shared inbox—first response, resolution, and optional update targets—with business hours and holiday calendars. Breach escalations notify managers and can reassign automatically so silent failures do not hide in a busy queue.

AI Copilot assists inside the same inbox: drafts, summaries, and knowledge suggestions without opening another product. Deflection on portal and chat reduces volume entering the inbox while SLA metrics stay honest on what remains. Analytics dashboards show volume by channel, agent performance, and SLA compliance without exporting spreadsheets.

Optional Service Desk ITSM adds employee requests to the same operational muscle memory—incidents, changes, and approvals when internal IT matures. Support leaders who later absorb IT do not retrain on a second interface.

The shared inbox is not a feature checkbox—it is the daily workspace where Helpefi earns trust. Pair it with clear assignment rules, collision etiquette, and SLA accountability to turn channel chaos into a team sport.

Leaders should review inbox health weekly during the first quarter: unassigned backlog age, percent of tickets touched by more than one agent without internal notes, and channel mix shifts after marketing campaigns. Those operational signals tell you whether to adjust staffing, macros, or deflection articles before customers feel the strain. A mature shared inbox feels boring—incoming work appears, the right person owns it, and customers get one coherent team on the other end.',
    ),
  ),
  'use_cases' =>
  array (
    'title' => 'Teams that thrive on a shared inbox',
    'items' =>
    array (
      0 =>
      array (
        'title' => 'Growing SaaS support',
        'body' => 'Five agents outgrow shared Gmail; Helpefi unifies trial onboarding questions from email and chat with SLAs leadership can report in board meetings.',
      ),
      1 =>
      array (
        'title' => 'Ecommerce peak season',
        'body' => 'Holiday volume spikes across email and chat; round-robin assignment and collision detection prevent duplicate shipping promises.',
      ),
      2 =>
      array (
        'title' => 'Digital agencies',
        'body' => 'Multiple client inboxes and portals feed one agency queue with brand-tagged views and isolated customer data per tenant.',
      ),
      3 =>
      array (
        'title' => 'Hybrid customer and IT support',
        'body' => 'Employee laptop requests and customer tickets share collaboration habits—notes, mentions, and SLAs—before optional ITSM formalizes internal workflows.',
      ),
      4 =>
      array (
        'title' => 'Migrating off legacy suites',
        'body' => 'Teams leaving Zendesk or Freshdesk keep threading and history while shedding per-seat suite complexity for a focused inbox core.',
      ),
    ),
  ),
  'faq' =>
  array (
    0 =>
    array (
      'q' => 'Can multiple agents work the same inbox?',
      'a' => 'Yes. Assign tickets by team, skill, or round-robin. Every agent sees the same queue with real-time updates, collision detection, and full conversation history. Personal views show assigned work without hiding team backlog from leads.',
    ),
    1 =>
    array (
      'q' => 'Does shared inbox include live chat?',
      'a' => 'Yes. Chat, email, SMS, and portal submissions all land in the same shared inbox as tickets. Agents handle multiple chats alongside email with collision detection and SLA tracking on every channel.',
    ),
    2 =>
    array (
      'q' => 'How do we connect our existing support email?',
      'a' => 'Connect Gmail, Microsoft 365, or Zoho Mail via OAuth, or forward any address into Helpefi. Customers continue receiving replies from your domain. The setup wizard guides DNS and forwarding steps during trial.',
    ),
    3 =>
    array (
      'q' => 'What is collision detection?',
      'a' => 'Collision detection shows when another agent is viewing or replying to the same ticket. That prevents duplicate customer responses—a common failure mode in shared Gmail workflows—especially on fast-moving chat conversations.',
    ),
    4 =>
    array (
      'q' => 'Can we operate multiple brands in one workspace?',
      'a' => 'Yes. Multi-brand workspaces support separate inboxes, portals, SLAs, and knowledge collections while agents work from unified views filtered by brand. Database-per-tenant isolation helps agencies separate client data.',
    ),
    5 =>
    array (
      'q' => 'Do internal notes stay private?',
      'a' => 'Internal notes and @mentions are visible to agents on the ticket, not to customers. Use them for finance approvals, engineering context, and manager guidance without cluttering the public thread.',
    ),
    6 =>
    array (
      'q' => 'How does shared inbox work with SLAs?',
      'a' => 'Every ticket in the shared inbox can have SLA policies for first response and resolution. Timers respect business hours and holidays. Breach escalations apply uniformly regardless of whether the ticket arrived via email, chat, or portal.',
    ),
    7 =>
    array (
      'q' => 'Can we migrate from Zendesk or Freshdesk?',
      'a' => 'Yes. Import open and recent tickets, reconnect channels, and parallel-run inboxes before cutover. Migration guides cover macros, tags, and SLA remapping so the shared inbox feels familiar on day one.',
    ),
    8 =>
    array (
      'q' => 'Is mobile access available for agents?',
      'a' => 'Helpefi agent workspace is mobile-ready for triage, replies, and assignment on the go. Critical collision and SLA indicators remain visible so mobile agents do not accidentally duplicate desktop replies.',
    ),
    9 =>
    array (
      'q' => 'Which plans include shared inbox?',
      'a' => 'Shared inbox is core to every Helpefi plan, including trial. Limits on agents and monthly tickets vary by plan tier; channel connectivity and automations expand on Professional and Enterprise. Multi-brand filtering and database-per-tenant isolation are available as you grow into Professional and Enterprise packaging.',
    ),
    10 =>
    array (
      'q' => 'How do views and filters help large teams?',
      'a' => 'Custom views slice the shared inbox by assignee, brand, priority, SLA risk, or tag so agents focus without losing team visibility. Managers pin breach-risk views during incidents. Saved views replace brittle inbox rules in Gmail that break whenever someone renames a label.',
    ),
  ),
  'related_links' =>
  array (
    0 =>
    array (
      'href' => '/pricing',
      'label' => 'Helpefi pricing',
    ),
    1 =>
    array (
      'href' => '/ai-agent',
      'label' => 'AI Agent',
    ),
    2 =>
    array (
      'href' => '/sla-management',
      'label' => 'SLA management',
    ),
    3 =>
    array (
      'href' => '/email-ticketing',
      'label' => 'Email ticketing',
    ),
    4 =>
    array (
      'href' => '/compare/front',
      'label' => 'Helpefi vs Front',
    ),
    5 =>
    array (
      'href' => '/compare/help-scout',
      'label' => 'Helpefi vs Help Scout',
    ),
  ),
  'conclusion' =>
  array (
    'title' => 'One inbox, one team, one source of truth',
    'body' => 'Helpefi shared inbox turns scattered channels into a disciplined queue agents can trust. Collision detection, assignment rules, and internal collaboration replace forwarded-email chaos. Connect your support address during trial, pilot with a small team, and measure duplicate replies and first response time against your old workflow. Omnichannel support only works when everyone works from the same place—Helpefi is that place. When the pilot succeeds, roll out chat and portal channels using the same habits so customers never feel a tooling fracture as you scale. Treat the inbox as infrastructure: stable, monitored, and improved in small weekly increments rather than heroic quarterly rewrites.',
  ),
  'updated_at' => '2026-07-08',
  'author' =>
  array (
    'name' => 'Sarah Chen',
    'role' => 'Product Marketing Manager',
  ),
  'reviewer' =>
  array (
    'name' => 'David Park',
    'role' => 'VP of Engineering',
  ),
  'screenshots' =>
  array (
  ),
  'external_references' =>
  array (
    0 =>
    array (
      'url' => 'https://www.g2.com/products/helpefi/reviews',
      'title' => 'Helpefi on G2',
      'description' => 'User reviews and ratings for Helpefi on G2.',
    ),
    1 =>
    array (
      'url' => 'https://helpefi.com/docs/security',
      'title' => 'Helpefi Security & Compliance',
      'description' => 'Helpefi security practices, certifications, and data protection policies.',
    ),
  ),
  'cta_title' => 'Unify your support inbox',
  'cta_body' => 'Start your free trial and connect your first email inbox in under two minutes.',
);
