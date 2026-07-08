<?php

return [
    'nav_label' => 'Security & Compliance',
    'hero_title' => 'Security & Compliance',
    'hero_subtitle' => 'How Helpefi protects customer data, isolates workspaces, and gives security reviewers the controls they need for procurement and compliance questionnaires.',
    'effective_date' => 'Last updated: July 9, 2026',
    'sections' => [
        [
            'title' => '1. Overview',
            'body' => 'Helpefi is a multi-tenant cloud helpdesk and optional ITSM platform. Security is built into workspace architecture, agent access controls, and operational settings — not added as an afterthought.',
            'paragraphs' => [
                'This page summarizes how Helpefi handles tenant isolation, authentication, auditability, AI data use, and optional data residency. For legal terms governing personal data, see our Privacy Policy. For contractual obligations, see our Terms of Service.',
            ],
        ],
        [
            'title' => '2. Tenant isolation',
            'body' => 'Each Helpefi workspace runs on its own database. Customer tickets, contacts, knowledge base content, and configuration data for one workspace are not co-mingled in a shared datastore with other customers.',
            'paragraphs' => [
                'Database-per-tenant isolation gives agencies, multi-brand operators, and regulated teams a concrete boundary for backups, upgrades, and offboarding — instead of relying only on logical partitions inside a shared pool.',
            ],
        ],
        [
            'title' => '3. Authentication and access control',
            'items' => [
                'Agents sign in with individual credentials — support queues are not accessed through shared mailbox passwords.',
                'Role-based permissions control who can view, reply, configure automations, manage billing, or access security settings.',
                'Two-factor authentication is available for agent accounts; workspace admins can require MFA for agents.',
                'Enterprise plans support SAML and OIDC single sign-on so agents authenticate through your corporate identity provider.',
            ],
        ],
        [
            'title' => '4. Audit logging and retention',
            'body' => 'Workspace administrators can review audit logs for security-relevant activity and configure retention policies from Settings → Security.',
            'items' => [
                'Audit log retention is configurable within published limits.',
                'Closed ticket retention can be set to support your data minimization policies.',
                'Retention purge tools let authorized admins remove aged audit logs and closed tickets according to workspace policy.',
            ],
        ],
        [
            'title' => '5. Encryption and infrastructure',
            'body' => 'Helpefi is hosted on modern cloud infrastructure. Data in transit is protected with TLS. Attachments and workspace files are stored in private object storage with access controlled by the application.',
            'paragraphs' => [
                'For teams with stricter residency requirements, optional bring-your-own database (BYO DB) and bring-your-own storage (BYO storage) add-ons let paid workspaces keep data in your AWS or Cloudflare account while Helpefi runs the application layer.',
            ],
        ],
        [
            'title' => '6. AI and customer data',
            'body' => 'Helpefi AI Copilot assists agents inside the ticket workflow. Your workspace data is used to ground replies and deflection for your organization — not to train public foundation models for other customers.',
            'paragraphs' => [
                'Review AI settings in your workspace and publish only knowledge base content you want Copilot to cite. Agents approve AI drafts before sending customer-facing replies.',
            ],
        ],
        [
            'title' => '7. Integrations and API access',
            'body' => 'Third-party integrations connect through OAuth or API credentials scoped to your workspace. REST API access is available on Professional and Enterprise plans (or with the Integrations add-on), with permissions governed by your workspace roles.',
            'paragraphs' => [
                'You are responsible for reviewing third-party terms and access when connecting external systems. Disable integrations you no longer use.',
            ],
        ],
        [
            'title' => '8. Vulnerability reports and security contact',
            'body' => 'If you believe you have found a security vulnerability in Helpefi, report it responsibly to {contactEmail} with subject line Security.',
            'paragraphs' => [
                'Include enough detail for us to reproduce the issue. Please do not publicly disclose vulnerabilities before we have had a reasonable opportunity to investigate and remediate.',
            ],
        ],
        [
            'title' => '9. Compliance documentation',
            'body' => 'Helpefi does not claim third-party certifications on this page. Enterprise customers evaluating procurement questionnaires can contact {contactEmail} with topic Enterprise for architecture diagrams, data processing summaries, and security review materials appropriate to your evaluation stage.',
        ],
    ],
    'related_links' => [
        ['href' => '/privacy', 'label' => 'Privacy Policy'],
        ['href' => '/terms', 'label' => 'Terms of Service'],
        ['href' => '/support', 'label' => 'Support'],
        ['href' => '/contact', 'label' => 'Contact us'],
    ],
    'cta_title' => '',
    'cta_body' => '',
];
