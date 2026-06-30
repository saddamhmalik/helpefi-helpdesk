<?php

return array (
  'zendesk' => 
  array (
    'nav_label' => 'Migrate from Zendesk',
    'source_name' => 'Zendesk',
    'badge' => 'Zendesk migration guide',
    'hero_title' => 'Migrate from Zendesk to Helpefi',
    'hero_highlight' => 'Without losing ticket history.',
    'hero_subtitle' => 'Export Zendesk tickets and contacts, import into {brand}, run both systems in parallel, then cut over email and chat when your team is confident.',
    'steps' => 
    array (
      0 => 
      array (
        'title' => 'Export tickets and users',
        'body' => 'Use Zendesk CSV export or API to pull tickets, users, and organizations. Map tags and custom fields to {brand} equivalents.',
      ),
      1 => 
      array (
        'title' => 'Import into a parallel workspace',
        'body' => 'Create your {brand} trial, import CSV data, and connect inbound email on a test address or subdomain.',
      ),
      2 => 
      array (
        'title' => 'Recreate automations and SLAs',
        'body' => 'Rebuild SLA policies, business hours, macros, and assignment rules. Enable AI Copilot on top of your imported KB articles.',
      ),
      3 => 
      array (
        'title' => 'Cut over channels',
        'body' => 'Point support{\'@\'} DNS to {brand}, embed chat on production, and disable Zendesk inbound when SLAs and reports match expectations.',
      ),
    ),
    'checklist' => 
    array (
      0 => 'Ticket and contact export validated',
      1 => 'Custom fields mapped',
      2 => 'SLA policies recreated',
      3 => 'KB articles published or imported',
      4 => 'Agents trained on split-pane workspace',
      5 => 'Parallel run of at least 5 business days',
      6 => 'CSAT and SLA dashboards reviewed',
    ),
    'faq' => 
    array (
      0 => 
      array (
        'q' => 'How long does a Zendesk migration take?',
        'a' => 'Most 5–30 agent teams complete import and parallel run within two weeks. Complex custom field mapping may add time.',
      ),
      1 => 
      array (
        'q' => 'Can we keep Zendesk during transition?',
        'a' => 'Yes. Forward a subset of email to {brand} or run chat on {brand} only while Zendesk handles legacy tickets.',
      ),
      2 => 
      array (
        'q' => 'Does {brand} support Zendesk-style SLAs?',
        'a' => 'Yes. Business hours, tier policies, breach alerts, and escalations are built in on all plans.',
      ),
    ),
    'cta_title' => 'Start your Zendesk migration trial',
    'cta_body' => 'Import sample data free, run parallel for a week, and compare handle time before you cancel Zendesk.',
  ),
  'freshdesk' => 
  array (
    'nav_label' => 'Migrate from Freshdesk',
    'source_name' => 'Freshdesk',
    'badge' => 'Freshdesk migration guide',
    'hero_title' => 'Migrate from Freshdesk to Helpefi',
    'hero_highlight' => 'One platform, optional ITSM.',
    'hero_subtitle' => 'Move off Freshdesk (and Freshservice if needed) with CSV import, parallel inbox testing, and optional Service Desk ITSM on {brand}.',
    'steps' => 
    array (
      0 => 
      array (
        'title' => 'Export Freshdesk data',
        'body' => 'Download tickets, contacts, and solutions from Freshdesk admin. Note tag and priority mappings.',
      ),
      1 => 
      array (
        'title' => 'Set up {brand} workspace',
        'body' => 'Register, import CSV, and publish KB articles migrated from Freshdesk solutions.',
      ),
      2 => 
      array (
        'title' => 'Enable ITSM if required',
        'body' => 'Add Service Desk add-on for incidents, changes, and problems instead of maintaining Freshservice separately.',
      ),
      3 => 
      array (
        'title' => 'Switch inbound channels',
        'body' => 'Update support email forwarding and chat embed. Monitor SLA compliance during the first week live.',
      ),
    ),
    'checklist' => 
    array (
      0 => 'Freshdesk ticket export complete',
      1 => 'Solution articles migrated to KB',
      2 => 'Agent roles and teams configured',
      3 => 'Automation rules rebuilt',
      4 => 'Optional Service Desk queues planned',
      5 => 'Stakeholders signed off on parallel metrics',
    ),
    'faq' => 
    array (
      0 => 
      array (
        'q' => 'Can {brand} replace Freshdesk and Freshservice?',
        'a' => 'For many teams, yes — customer support on {brand} plus Service Desk ITSM add-on covers both use cases.',
      ),
      1 => 
      array (
        'q' => 'Will we lose Freshdesk automations?',
        'a' => 'Recreate them with {brand} triggers, macros, and SLA policies. Most common rules map directly.',
      ),
    ),
    'cta_title' => 'Try migrating from Freshdesk',
    'cta_body' => 'Start a free trial, import your export, and compare side by side with Freshdesk.',
  ),
  'intercom' => 
  array (
    'nav_label' => 'Migrate from Intercom',
    'source_name' => 'Intercom',
    'badge' => 'Intercom migration guide',
    'hero_title' => 'Migrate from Intercom to Helpefi',
    'hero_highlight' => 'From conversations to tickets.',
    'hero_subtitle' => 'Move from Intercom inbox and Fin AI to a ticket-first helpdesk with SLA, KB deflection, and flat AI Copilot pricing on {brand}.',
    'steps' => 
    array (
      0 => 
      array (
        'title' => 'Export conversation history',
        'body' => 'Export contacts and conversation data from Intercom. Identify recurring topics for KB articles.',
      ),
      1 => 
      array (
        'title' => 'Build KB and deflection',
        'body' => 'Publish articles that powered Fin AI. Enable portal and chat deflection on {brand}.',
      ),
      2 => 
      array (
        'title' => 'Connect chat and email',
        'body' => 'Replace Intercom messenger embed with {brand} live chat. Forward support email to unified inbox.',
      ),
      3 => 
      array (
        'title' => 'Train agents on ticketing',
        'body' => 'Agents work from SLA-driven queues with Copilot drafts instead of conversation threads alone.',
      ),
    ),
    'checklist' => 
    array (
      0 => 'Top Fin deflection topics documented as KB',
      1 => 'Chat widget swapped on production site',
      2 => 'Email forwarding tested',
      3 => 'SLA policies defined',
      4 => 'AI Copilot trial with real tickets',
    ),
    'faq' => 
    array (
      0 => 
      array (
        'q' => 'Is {brand} a good Intercom replacement?',
        'a' => 'Yes for teams that need SLA, ticket management, and predictable AI cost — not primarily in-app product tours.',
      ),
      1 => 
      array (
        'q' => 'How does AI pricing compare to Fin?',
        'a' => '{brand} AI Copilot is a flat monthly add-on or bundled on Enterprise — not per-resolution fees.',
      ),
    ),
    'cta_title' => 'Migrate from Intercom with a free trial',
    'cta_body' => 'Run chat and email on {brand} for two weeks and compare total AI plus seat cost.',
  ),
  'helpscout' => 
  array (
    'nav_label' => 'Migrate from Help Scout',
    'source_name' => 'Help Scout',
    'badge' => 'Help Scout migration guide',
    'hero_title' => 'Migrate from Help Scout to Helpefi',
    'hero_highlight' => 'Keep simplicity, add depth.',
    'hero_subtitle' => 'Graduate from shared inbox to SLA, chat, SMS, AI, and optional ITSM without losing the clean agent experience you liked in Help Scout.',
    'steps' => 
    array (
      0 => 
      array (
        'title' => 'Export Help Scout conversations',
        'body' => 'Export customers and conversations. Tag recurring themes for KB and automation.',
      ),
      1 => 
      array (
        'title' => 'Import to {brand}',
        'body' => 'Load contacts and tickets via CSV. Connect the same support email address on a parallel forward.',
      ),
      2 => 
      array (
        'title' => 'Add SLA and chat',
        'body' => 'Configure response targets and embed live chat — capabilities beyond Help Scout shared inbox.',
      ),
      3 => 
      array (
        'title' => 'Go live',
        'body' => 'Remove Help Scout forwarding when agents prefer the {brand} workspace and reports meet leadership expectations.',
      ),
    ),
    'checklist' => 
    array (
      0 => 'Customer export imported',
      1 => 'Mailbox forwarding tested',
      2 => 'SLA timers verified',
      3 => 'Chat embed live on site',
      4 => 'CSAT survey configured',
    ),
    'faq' => 
    array (
      0 => 
      array (
        'q' => 'Will {brand} feel as simple as Help Scout?',
        'a' => 'The agent inbox stays focused. Advanced features live in settings until you need them.',
      ),
      1 => 
      array (
        'q' => 'Can we migrate gradually?',
        'a' => 'Yes. Forward email to both systems briefly or route one brand/channel to {brand} first.',
      ),
    ),
    'cta_title' => 'Upgrade from Help Scout',
    'cta_body' => 'Start a trial, connect email, and add SLA plus chat when you\'re ready.',
  ),
  'freshservice' => 
  array (
    'nav_label' => 'Migrate from Freshservice',
    'source_name' => 'Freshservice',
    'badge' => 'Freshservice migration guide',
    'hero_title' => 'Migrate from Freshservice to Helpefi',
    'hero_highlight' => 'ITSM plus customer support.',
    'hero_subtitle' => 'Replace Freshservice with {brand} employee support and optional Service Desk ITSM — plus customer-facing channels Freshservice lacks.',
    'steps' => 
    array (
      0 => 
      array (
        'title' => 'Inventory IT workflows',
        'body' => 'Document incident, request, change, and problem queues you use in Freshservice.',
      ),
      1 => 
      array (
        'title' => 'Configure Service Desk on {brand}',
        'body' => 'Enable Service Desk add-on and map Freshservice ticket types to {brand} type queues.',
      ),
      2 => 
      array (
        'title' => 'Migrate employee portal',
        'body' => 'Publish service catalog items and KB for employee self-service on {brand} portal.',
      ),
      3 => 
      array (
        'title' => 'Cut over IT team',
        'body' => 'Route employee email and portal to {brand}. Run war room and change workflows in Service Desk.',
      ),
    ),
    'checklist' => 
    array (
      0 => 'ITIL queues mapped',
      1 => 'Service catalog rebuilt',
      2 => 'Approval workflows tested',
      3 => 'CMDB or asset needs documented',
      4 => 'Major incident process validated',
    ),
    'faq' => 
    array (
      0 => 
      array (
        'q' => 'Does {brand} cover Freshservice ITSM?',
        'a' => 'Service Desk add-on includes incidents, changes, problems, approvals, and major incident war rooms.',
      ),
      1 => 
      array (
        'q' => 'Can we also run customer support?',
        'a' => 'Yes. {brand} unifies employee and customer channels in one platform.',
      ),
    ),
    'cta_title' => 'Replace Freshservice on your timeline',
    'cta_body' => 'Trial {brand} with Service Desk enabled and migrate one queue at a time.',
  ),
);
