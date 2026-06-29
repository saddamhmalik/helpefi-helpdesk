<?php

return [
    'fintech' => [
        'nav_label' => 'Fintech',
        'badge' => 'For fintech & financial services support',
        'hero_title' => 'Customer support software for fintech teams',
        'hero_highlight' => 'Fast answers. Strong controls.',
        'hero_subtitle' => 'Handle KYC, billing, and account questions in one audited inbox — with SLA tiers, SSO, and optional BYO cloud for regulated data.',
        'pains' => [
            [
                'title' => 'Account issues need instant context',
                'body' => 'Agents lack CRM and transaction context while customers wait on secure channels.',
            ],
            [
                'title' => 'Regulators expect audit trails',
                'body' => 'Ticket history, retention, and access logs must survive compliance reviews.',
            ],
            [
                'title' => 'Chat and email silos create risk',
                'body' => 'Sensitive conversations scatter across tools without unified SLA or escalation.',
            ],
        ],
        'features' => [
            [
                'title' => 'Unified secure inbox',
                'body' => 'Email, chat, SMS, and portal tickets in one workspace with collision detection.',
            ],
            [
                'title' => 'CRM integrations',
                'body' => 'HubSpot or Salesforce sidebar context on Enterprise or with Integrations add-on.',
            ],
            [
                'title' => 'Enterprise SSO & 2FA',
                'body' => 'SAML/OIDC for agents plus optional two-factor authentication.',
            ],
            [
                'title' => 'SLA by customer tier',
                'body' => 'Premium account holders get stricter response targets and escalations.',
            ],
            [
                'title' => 'Data residency',
                'body' => 'Store tickets and attachments in your own database and object storage.',
            ],
            [
                'title' => 'AI with human approval',
                'body' => 'Copilot drafts replies from approved KB content — nothing auto-sends to customers.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we restrict agent permissions?',
                'a' => 'Yes. Custom roles control who sees billing fields, exports data, or manages security settings.',
            ],
            [
                'q' => 'Do you support encrypted attachments?',
                'a' => 'Attachments use private object storage with signed URLs. BYOS lets you keep files in your S3 or R2 bucket.',
            ],
            [
                'q' => 'How fast can we launch?',
                'a' => 'Most fintech teams connect email and SSO within the first week of trial.',
            ],
        ],
        'cta_title' => 'Support customers with confidence',
        'cta_body' => 'Trial {brand} free — connect email, publish security FAQs, and add SSO before go-live.',
    ],
    'healthcare' => [
        'nav_label' => 'Healthcare',
        'badge' => 'For healthcare & healthtech support',
        'hero_title' => 'HIPAA-aware helpdesk for patient and member support',
        'hero_highlight' => 'Secure support without enterprise bloat.',
        'hero_subtitle' => 'Run patient, member, and provider support with audit trails, SLA tiers, and optional data residency — store workspace data in your own cloud when compliance requires it.',
        'pains' => [
            [
                'title' => 'PHI must never leak into the wrong channel',
                'body' => 'Agents paste sensitive details into email threads, Slack, or spreadsheets with no access controls.',
            ],
            [
                'title' => 'Compliance audits need ticket history',
                'body' => 'Support conversations lack retention policies, SSO, or exportable audit logs.',
            ],
            [
                'title' => 'Member questions repeat across portals',
                'body' => 'Appointment, billing, and coverage FAQs flood the queue because self-service is disconnected from chat.',
            ],
        ],
        'features' => [
            [
                'title' => 'Role-based access & SSO',
                'body' => 'Enterprise SAML/OIDC, agent permissions, and audit logs for who viewed or changed each ticket.',
            ],
            [
                'title' => 'Branded patient or member portal',
                'body' => 'Publish HIPAA-conscious help content and let members submit requests without calling.',
            ],
            [
                'title' => 'AI deflection with approved content',
                'body' => 'Ground AI answers in published articles — agents review before anything sends.',
            ],
            [
                'title' => 'Data residency add-ons',
                'body' => 'BYO database and storage keep tickets and attachments in your AWS or Cloudflare account.',
            ],
            [
                'title' => 'Tier-based SLAs',
                'body' => 'Different response targets for urgent clinical escalations vs general billing questions.',
            ],
            [
                'title' => 'Service catalog for internal IT',
                'body' => 'Add Service Desk ITSM for employee requests, changes, and incident war rooms.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we host data in our own cloud?',
                'a' => 'Yes. Add BYO database (MySQL on RDS/Aurora) and BYO storage (S3 or R2) on paid plans. See our data residency feature page for control-plane vs workspace data.',
            ],
            [
                'q' => 'Does {brand} sign BAAs?',
                'a' => 'Contact sales{\'@\'}helpefi.com for enterprise compliance requirements. BYO infrastructure helps teams meet data residency policies today.',
            ],
            [
                'q' => 'Can we separate member support from internal IT?',
                'a' => 'Yes. Use type queues, brands, or Service Desk add-on to keep patient-facing and internal workflows organized.',
            ],
        ],
        'cta_title' => 'Modern support for regulated teams',
        'cta_body' => 'Start a trial, connect your member portal, and evaluate data residency when your security review is ready.',
    ],
    'saas' => [
        'nav_label' => 'SaaS companies',
        'badge' => 'For B2B SaaS support teams',
        'hero_title' => 'Customer support software built for SaaS',
        'hero_highlight' => 'Scale support without scaling headcount.',
        'hero_subtitle' => 'Give your CS team one inbox for email, chat, and in-app requests — with AI Copilot, product context in tickets, and SLAs that keep up with release cycles.',
        'pains' => [
            [
                'title' => 'Support volume spikes after every launch',
                'body' => 'New features generate tickets faster than manual triage and macros can handle.',
            ],
            [
                'title' => 'Product context lives outside the ticket',
                'body' => 'Agents tab between CRM, billing, and error logs to answer one customer question.',
            ],
            [
                'title' => 'Self-service does not reduce ticket load',
                'body' => 'Docs exist but customers still email because search and deflection are disconnected from chat and portal.',
            ],
        ],
        'features' => [
            [
                'title' => 'Unified inbox for every channel',
                'body' => 'Email, live chat, portal, and SMS create tickets in one workspace with full conversation history.',
            ],
            [
                'title' => 'AI Copilot and deflection',
                'body' => 'Draft replies from your KB, deflect repetitive questions on portal and chat, and triage new tickets automatically.',
            ],
            [
                'title' => 'CRM and dev tool integrations',
                'body' => 'Surface HubSpot, Salesforce, Jira, and Linear context in the ticket sidebar — no tab switching.',
            ],
            [
                'title' => 'SLA policies by customer tier',
                'body' => 'Apply different response targets for free, pro, and enterprise accounts with business hours and escalations.',
            ],
            [
                'title' => 'CSAT and agent performance',
                'body' => 'Measure satisfaction after resolve and track agent performance against SLAs in built-in reports.',
            ],
            [
                'title' => 'Transparent SaaS-friendly pricing',
                'body' => 'Per-agent plans with optional AI, Integrations, and Service Desk add-ons — no surprise per-resolution fees.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Does {brand} integrate with our CRM?',
                'a' => 'Yes. Connect HubSpot or Salesforce on Enterprise or add the Integrations Pack on Professional to pull customer profiles into every ticket.',
            ],
            [
                'q' => 'Can AI answer questions from our help center?',
                'a' => 'Yes. AI deflection uses semantic search across published articles and can suggest answers before customers submit tickets.',
            ],
            [
                'q' => 'How fast can a SaaS team go live?',
                'a' => 'Most teams connect email and embed chat within a day. Full setup with KB, SLAs, and automations typically fits inside the free trial.',
            ],
        ],
        'cta_title' => 'Support more customers with less friction',
        'cta_body' => 'Start a free trial, connect your channels, and see AI Copilot on your first ticket.',
    ],
    'ecommerce' => [
        'nav_label' => 'E-commerce',
        'badge' => 'For e-commerce & D2C brands',
        'hero_title' => 'Customer support software for online stores',
        'hero_highlight' => 'Turn order questions into loyal customers.',
        'hero_subtitle' => 'Unify Shopify order context, email, live chat, and your help center in one inbox. Deflect WISMO tickets with AI and SLAs that survive sale season spikes.',
        'pains' => [
            [
                'title' => 'Refund and delivery tickets flood the inbox',
                'body' => 'Agents jump between Shopify, Gmail, and WhatsApp with no shared history or ownership.',
            ],
            [
                'title' => 'Peak sale weeks break response times',
                'body' => 'Manual triage cannot keep up when order volume doubles overnight.',
            ],
            [
                'title' => 'Help center does not deflect repeat questions',
                'body' => 'Customers still email about shipping, returns, and order status because self-service is disconnected.',
            ],
        ],
        'features' => [
            [
                'title' => 'Shopify in the ticket sidebar',
                'body' => 'See orders, fulfillment status, and customer spend without leaving the conversation.',
            ],
            [
                'title' => 'Live chat + branded portal',
                'body' => 'Embed chat on your storefront and let customers track tickets on a portal that matches your brand.',
            ],
            [
                'title' => 'AI deflection & reply drafts',
                'body' => 'Suggest answers from your KB and draft replies for common order and return questions.',
            ],
            [
                'title' => 'SLA policies for peak seasons',
                'body' => 'Set response targets, business hours, and escalation rules before your next campaign.',
            ],
            [
                'title' => 'CSAT on resolve',
                'body' => 'Measure satisfaction after every ticket close and spot product or fulfillment issues early.',
            ],
            [
                'title' => 'Affordable INR pricing',
                'body' => 'Start on Professional from ₹999/mo with optional AI Copilot and Integrations add-ons.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Does helpefi integrate with Shopify?',
                'a' => 'Yes. Connect Shopify on Pro or add the Integrations Pack on Professional to pull order and customer data into every ticket.',
            ],
            [
                'q' => 'Can we handle festival sale volume spikes?',
                'a' => 'Yes. Use SLA policies, automation, and AI triage to prioritize urgent order issues during high-traffic periods.',
            ],
            [
                'q' => 'How fast can a D2C brand go live?',
                'a' => 'Most teams connect email and publish a help center within a day. Live chat embed code takes minutes.',
            ],
        ],
        'cta_title' => 'Ready to support shoppers at scale?',
        'cta_body' => 'Start a free trial with full platform access. Connect your channels and see order context in your first ticket.',
    ],
    'logistics' => [
        'nav_label' => 'Logistics',
        'badge' => 'For logistics & supply chain support',
        'hero_title' => 'Helpdesk software for logistics operations',
        'hero_highlight' => 'Resolve shipment issues faster.',
        'hero_subtitle' => 'Give dispatch, warehouse, and customer support teams one inbox for carrier delays, delivery exceptions, and shipper inquiries — with SLAs, automations, and order context in every ticket.',
        'pains' => [
            [
                'title' => 'Shipment status lives outside the ticket',
                'body' => 'Agents chase tracking numbers across carrier portals, WMS, and email threads with no shared view.',
            ],
            [
                'title' => 'Peak season overwhelms manual triage',
                'body' => 'Holiday and promotional surges flood inboxes before routing rules can prioritize urgent exceptions.',
            ],
            [
                'title' => 'Shippers and end customers use different channels',
                'body' => 'Phone, email, and portal requests never land in one queue with clear ownership.',
            ],
        ],
        'features' => [
            [
                'title' => 'Unified multi-channel inbox',
                'body' => 'Email, chat, SMS, and portal tickets in one workspace with full conversation history.',
            ],
            [
                'title' => 'SLA policies by priority',
                'body' => 'Set response targets for damaged goods, lost packages, and standard tracking inquiries.',
            ],
            [
                'title' => 'Automation and macros',
                'body' => 'Route tickets by region, carrier, or account tier and reply faster with canned responses.',
            ],
            [
                'title' => 'Branded shipper portal',
                'body' => 'Let partners submit exceptions and track resolution without calling dispatch.',
            ],
            [
                'title' => 'AI deflection for tracking FAQs',
                'body' => 'Deflect WISMO-style questions on your portal and chat before they become tickets.',
            ],
            [
                'title' => 'Reports for operations reviews',
                'body' => 'Track volume, SLA compliance, and CSAT by lane, hub, or customer segment.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we route tickets by warehouse or region?',
                'a' => 'Yes. Use automation rules, skills-based assignment, and team queues to route exceptions to the right hub.',
            ],
            [
                'q' => 'Does {brand} integrate with our TMS or WMS?',
                'a' => 'Use webhooks and REST API to link tickets to shipment records. CRM and integration add-ons surface customer context in the sidebar.',
            ],
            [
                'q' => 'How fast can a logistics team go live?',
                'a' => 'Most teams connect email and publish a shipper portal within a week. SLAs and automations fit inside the free trial.',
            ],
        ],
        'cta_title' => 'Keep deliveries on track',
        'cta_body' => 'Start a free trial, connect your support channels, and set SLAs before your next peak season.',
    ],
    'edtech' => [
        'nav_label' => 'Edtech',
        'badge' => 'For schools, edtech & training providers',
        'hero_title' => 'Helpdesk software for education support teams',
        'hero_highlight' => 'Help students and staff faster.',
        'hero_subtitle' => 'Centralize student, faculty, and IT requests in one platform — portal, live chat, knowledge base, and optional Service Desk for campus IT.',
        'pains' => [
            [
                'title' => 'Start-of-term ticket surges overwhelm email',
                'body' => 'Login, enrollment, and LMS questions spike without deflection or SLA routing.',
            ],
            [
                'title' => 'IT and student services use different tools',
                'body' => 'Faculty IT tickets live in one system while student services uses another inbox.',
            ],
            [
                'title' => 'Self-service portals are outdated',
                'body' => 'Students cannot find answers because KB search and chat are disconnected.',
            ],
        ],
        'features' => [
            [
                'title' => 'Student & faculty portal',
                'body' => 'Branded help center with articles, service catalog requests, and ticket tracking.',
            ],
            [
                'title' => 'Live chat for enrollment season',
                'body' => 'Embed chat on your site with AI deflection for common LMS and access questions.',
            ],
            [
                'title' => 'Service Desk for campus IT',
                'body' => 'Add incidents, changes, and asset CMDB when ITIL workflows are required.',
            ],
            [
                'title' => 'Automation & macros',
                'body' => 'Canned responses for password resets, course access, and financial aid routing.',
            ],
            [
                'title' => 'Multi-department routing',
                'body' => 'Route tickets to registrar, IT, or finance with skills-based assignment.',
            ],
            [
                'title' => 'Affordable per-agent pricing',
                'body' => 'Starter and Professional tiers fit departmental budgets with INR options.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can parents and students use the same portal?',
                'a' => 'Yes. Configure separate brands or forms if you need different experiences for students vs staff.',
            ],
            [
                'q' => 'Does {brand} integrate with our LMS?',
                'a' => 'Use webhooks and REST API to link tickets to Canvas, Moodle, or internal systems. CRM integrations available on Enterprise.',
            ],
            [
                'q' => 'Can we handle start-of-semester volume?',
                'a' => 'Use SLA policies, automation, and AI deflection before peak registration weeks.',
            ],
        ],
        'cta_title' => 'Ready for the next enrollment cycle?',
        'cta_body' => 'Start your trial, publish five KB articles, and embed chat before registration opens.',
    ],
    'government' => [
        'nav_label' => 'Government',
        'badge' => 'For public sector & government agencies',
        'hero_title' => 'Helpdesk software for government service teams',
        'hero_highlight' => 'Serve citizens with accountability.',
        'hero_subtitle' => 'Run citizen inquiries, internal IT, and program support from one audited platform — with SLA tiers, role-based access, SSO, and optional data residency for sensitive workloads.',
        'pains' => [
            [
                'title' => 'Citizen requests arrive through every channel',
                'body' => 'Phone, email, walk-in notes, and web forms never consolidate into one accountable queue.',
            ],
            [
                'title' => 'Audit and FOIA requirements need full history',
                'body' => 'Ticket retention, access logs, and exportable records are hard to maintain across spreadsheets.',
            ],
            [
                'title' => 'Department silos slow resolution',
                'body' => 'Cases bounce between agencies with no shared timeline or escalation path.',
            ],
        ],
        'features' => [
            [
                'title' => 'Citizen-facing portal',
                'body' => 'Publish program FAQs, service catalog requests, and ticket tracking on a branded public portal.',
            ],
            [
                'title' => 'Enterprise SSO & role-based access',
                'body' => 'SAML/OIDC for staff with granular permissions and audit logs for every ticket action.',
            ],
            [
                'title' => 'SLA policies by program',
                'body' => 'Set response targets, business hours, and escalations aligned to service-level commitments.',
            ],
            [
                'title' => 'Data residency add-ons',
                'body' => 'Store tickets and attachments in your own database and object storage when policy requires it.',
            ],
            [
                'title' => 'Service Desk for internal IT',
                'body' => 'Add incidents, changes, approvals, and asset tracking for agency IT operations.',
            ],
            [
                'title' => 'Exportable reporting',
                'body' => 'Generate volume, SLA, and CSAT reports for oversight reviews and program dashboards.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we host data in our own cloud?',
                'a' => 'Yes. BYO database and storage add-ons keep workspace data in your AWS or Cloudflare account.',
            ],
            [
                'q' => 'Does {brand} support accessibility requirements?',
                'a' => 'Portals and agent workspace support keyboard navigation and semantic markup. Contact sales for agency-specific reviews.',
            ],
            [
                'q' => 'Can multiple departments share one platform?',
                'a' => 'Yes. Use teams, brands, or type queues to separate programs while leadership sees unified reporting.',
            ],
        ],
        'cta_title' => 'Modernize citizen support',
        'cta_body' => 'Start a trial, publish your first service catalog, and connect SSO when your security review is ready.',
    ],
    'startups' => [
        'nav_label' => 'Startups',
        'badge' => 'For fast-growing startup support teams',
        'hero_title' => 'Helpdesk software that grows with your startup',
        'hero_highlight' => 'Launch fast. Scale when you need to.',
        'hero_subtitle' => 'Start with email and a help center on day one, then add live chat, AI Copilot, and SLAs as your customer base grows — without switching tools or renegotiating contracts.',
        'pains' => [
            [
                'title' => 'Founders still answer support email',
                'body' => 'No shared inbox, macros, or assignment means every reply lives in a personal Gmail thread.',
            ],
            [
                'title' => 'Enterprise helpdesks are too heavy',
                'body' => 'Per-seat minimums, annual contracts, and ITSM complexity slow down a five-person team.',
            ],
            [
                'title' => 'Self-service is an afterthought',
                'body' => 'Docs live in Notion while customers keep emailing the same onboarding questions.',
            ],
        ],
        'features' => [
            [
                'title' => 'Free trial with full platform access',
                'body' => 'Test tickets, chat, KB, automations, and AI during your trial before you pick a plan.',
            ],
            [
                'title' => 'Affordable per-agent pricing',
                'body' => 'Starter and Professional tiers with INR options — add AI and integrations only when you need them.',
            ],
            [
                'title' => 'Unified inbox from day one',
                'body' => 'Email, chat, and portal in one workspace so your first support hire has context immediately.',
            ],
            [
                'title' => 'AI Copilot for lean teams',
                'body' => 'Draft replies from your KB and deflect repetitive questions so one agent handles more volume.',
            ],
            [
                'title' => 'Help center + live chat embed',
                'body' => 'Publish articles and embed chat on your marketing site in an afternoon.',
            ],
            [
                'title' => 'Scales to ITSM when you hire IT',
                'body' => 'Add Service Desk for internal requests without migrating to a new platform.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Do we need a credit card to start?',
                'a' => 'No. Start a free trial with full platform access and upgrade when you are ready.',
            ],
            [
                'q' => 'How fast can a startup go live?',
                'a' => 'Most teams connect email and publish a help center within a day. Chat embed takes minutes.',
            ],
            [
                'q' => 'Can we migrate from Intercom or Zendesk later?',
                'a' => 'Yes. Import CSV data and run parallel during cutover. See our migration guides for Zendesk, Intercom, and Freshdesk.',
            ],
        ],
        'cta_title' => 'Ship support before your next launch',
        'cta_body' => 'Start a free trial, connect support email, and publish your first five KB articles this week.',
    ],
    'banking' => [
        'nav_label' => 'Banking',
        'badge' => 'For banks & credit unions',
        'hero_title' => 'Customer support software for banking teams',
        'hero_highlight' => 'Secure, auditable, tier-aware.',
        'hero_subtitle' => 'Handle account servicing, card disputes, and digital banking inquiries in one controlled inbox — with SSO, audit trails, tier SLAs, and optional BYO infrastructure for regulated data.',
        'pains' => [
            [
                'title' => 'Account context is scattered across systems',
                'body' => 'Agents tab between core banking, CRM, and email with no unified customer view.',
            ],
            [
                'title' => 'Regulators expect complete audit trails',
                'body' => 'Ticket history, retention, and access controls must survive examinations.',
            ],
            [
                'title' => 'Premium clients expect faster responses',
                'body' => 'Wealth and business banking customers need tier-based SLAs that generic queues cannot enforce.',
            ],
        ],
        'features' => [
            [
                'title' => 'Unified secure inbox',
                'body' => 'Email, chat, SMS, and portal tickets in one workspace with collision detection and permissions.',
            ],
            [
                'title' => 'Enterprise SSO & 2FA',
                'body' => 'SAML/OIDC for agents with optional two-factor authentication and custom roles.',
            ],
            [
                'title' => 'SLA by customer segment',
                'body' => 'Different response targets for retail, business, and private banking with escalations.',
            ],
            [
                'title' => 'CRM integrations',
                'body' => 'HubSpot or Salesforce sidebar context on Enterprise or with Integrations add-on.',
            ],
            [
                'title' => 'Data residency',
                'body' => 'Store tickets and attachments in your own database and object storage.',
            ],
            [
                'title' => 'AI with human approval',
                'body' => 'Copilot drafts replies from approved KB content — agents review before anything sends.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we restrict who sees sensitive account data?',
                'a' => 'Yes. Custom roles control field visibility, exports, and admin settings per team.',
            ],
            [
                'q' => 'Do you support encrypted attachments?',
                'a' => 'Attachments use private object storage with signed URLs. BYOS keeps files in your S3 or R2 bucket.',
            ],
            [
                'q' => 'How long does a banking pilot take?',
                'a' => 'Most teams connect email and SSO within the first two weeks of trial, then expand to chat and portal.',
            ],
        ],
        'cta_title' => 'Elevate banking customer service',
        'cta_body' => 'Trial {brand} free — connect email, publish security FAQs, and add SSO before your pilot goes live.',
    ],
    'insurance' => [
        'nav_label' => 'Insurance',
        'badge' => 'For insurers & MGAs',
        'hero_title' => 'Helpdesk software for insurance support teams',
        'hero_highlight' => 'Claims and policy questions, resolved.',
        'hero_subtitle' => 'Manage policyholder inquiries, claims status updates, and agent support in one platform — with SLA tiers, automations, branded portals, and audit-ready ticket history.',
        'pains' => [
            [
                'title' => 'Policy and claims context lives outside the ticket',
                'body' => 'Agents search policy admin systems, email, and phone notes to answer one coverage question.',
            ],
            [
                'title' => 'Renewal season floods the queue',
                'body' => 'Billing and endorsement questions spike without deflection or automated routing.',
            ],
            [
                'title' => 'Brokers and policyholders use different channels',
                'body' => 'Phone, email, and portal requests never share a timeline or ownership.',
            ],
        ],
        'features' => [
            [
                'title' => 'Branded policyholder portal',
                'body' => 'Let customers submit requests, browse coverage FAQs, and track ticket status online.',
            ],
            [
                'title' => 'SLA policies by line of business',
                'body' => 'Set response targets for claims, billing, and underwriting inquiries with escalations.',
            ],
            [
                'title' => 'Automation and macros',
                'body' => 'Route tickets by policy type, region, or partner and reply faster with canned responses.',
            ],
            [
                'title' => 'AI deflection for common questions',
                'body' => 'Deflect coverage, billing, and claims-status FAQs on portal and chat before they become tickets.',
            ],
            [
                'title' => 'Enterprise SSO & audit logs',
                'body' => 'SAML/OIDC for staff with permissions and exportable history for compliance reviews.',
            ],
            [
                'title' => 'CRM integrations',
                'body' => 'Surface HubSpot or Salesforce context in the ticket sidebar on Enterprise or with Integrations add-on.',
            ],
        ],
        'faq' => [
            [
                'q' => 'Can we separate claims and policy servicing queues?',
                'a' => 'Yes. Use teams, type queues, and automation rules to route inquiries by line of business.',
            ],
            [
                'q' => 'Does {brand} integrate with our policy admin system?',
                'a' => 'Use webhooks and REST API to link tickets to policy records. CRM integrations add customer context in the sidebar.',
            ],
            [
                'q' => 'How fast can an insurance team go live?',
                'a' => 'Most teams connect email and publish a policyholder portal within two weeks of trial.',
            ],
        ],
        'cta_title' => 'Support policyholders with confidence',
        'cta_body' => 'Start a free trial, connect your channels, and set SLAs before renewal season.',
    ],
];
