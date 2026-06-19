<?php

return [
    'Enterprise teams increasingly need helpdesk software that respects data residency, audit requirements, and cloud governance policies. Bring Your Own Database (BYOD) and Bring Your Own Storage (BYOS) on helpefi let you keep workspace data and files in infrastructure you control — while still using the full helpefi product for tickets, AI, knowledge base, and customer support workflows.',

    'This guide explains what BYOD and BYOS are, who they are for, what data stays where, which cloud providers are supported, and how to configure, migrate, and operate external infrastructure with confidence.',

    'What is Bring Your Own Database (BYOD)?',

    'BYOD connects your helpefi workspace to a MySQL database you own and operate. Instead of storing tickets, users, knowledge base content, and settings on helpefi-managed database servers, that data lives in your database — for example Amazon RDS MySQL, Amazon Aurora MySQL, or a self-hosted MySQL 8.0+ instance in your VPC or data center.',

    'When BYOD is enabled, helpefi runs tenant migrations against your database, routes all workspace queries to your connection, and skips creating or dropping managed databases during provisioning and deletion. You retain full control over backups, encryption at rest, instance sizing, and regional placement.',

    'What is Bring Your Own Storage (BYOS)?',

    'BYOS stores file blobs — ticket attachments, avatars, and other uploaded assets — in object storage you control. Supported drivers include Amazon S3 and Cloudflare R2. helpefi registers a dynamic storage disk per workspace, serves private files through signed URLs, and never requires public bucket access.',

    'Object keys follow a predictable prefix layout (for example helpefi/{tenant_id}/attachments/...) so you can scope IAM policies or R2 tokens to the minimum required prefix.',

    'Why enterprises choose BYOD and BYOS',

    'Regulatory and contractual requirements often mandate that customer support data remain in a specific geography or cloud account. BYOD and BYOS address data at rest: tickets, replies, user profiles, knowledge articles, and file attachments can reside entirely in your AWS or Cloudflare account while your team continues using helpefi as the application layer.',

    'Security and operations teams also benefit from unified cloud governance. You apply your own backup schedules, encryption keys, VPC security groups, CloudTrail logging, and access reviews to the same infrastructure that already hosts other business systems. helpefi publishes egress IP addresses for database allowlisting so you can restrict inbound MySQL traffic to helpefi application servers only.',

    'What stays on helpefi vs what moves to your cloud',

    'With BYOD and BYOS enabled, all tenant business data moves to your infrastructure. That includes tickets and replies, users and roles, knowledge base articles and embeddings, helpdesk settings, mail configuration, tenant-scoped audit logs, attachment metadata, and the actual file blobs in your bucket.',

    'helpefi retains control-plane metadata required to operate the platform: workspace identifier, name, and slug; domain mappings for HTTPS routing; subscription and billing identifiers; encrypted infrastructure credentials (connection host, username, and secrets — not your business records); platform audit events for infrastructure changes; and session rows for authentication on shared SaaS.',

    'Application servers connect to your RDS instance and S3 or R2 bucket over TLS during normal operation. This satisfies at-rest residency in your chosen region. If you require zero helpefi-hosted metadata or zero transit through helpefi servers, consider helpefi self-hosted or dedicated VPC deployment instead of shared SaaS with BYO.',

    'Supported providers and requirements',

    'Database: MySQL 8.0 or newer (MySQL 8.4 recommended). Supported deployments include Amazon RDS MySQL, Amazon Aurora MySQL, and self-hosted MySQL reachable from helpefi egress IPs on port 3306. Cloudflare D1 and other non-MySQL engines are not supported for BYOD.',

    'Storage: Amazon S3 (any standard region) or Cloudflare R2 (S3-compatible API). For R2, configure the account endpoint (https://{account_id}.r2.cloudflarestorage.com), bucket name, API token credentials, and region (auto, wnam, enam, weur, eeur, apac, or oc).',

    'A common enterprise pattern is AWS RDS for the database and either AWS S3 or Cloudflare R2 for files. You can enable BYOD and BYOS independently, though most compliance-driven customers enable both.',

    'Eligibility and add-ons',

    'BYOD and BYOS are enterprise capabilities. Your workspace must be on an eligible plan with BYO allowed by platform administration, and trial workspaces cannot configure external infrastructure until the trial ends and BYO is explicitly enabled.',

    'Each capability requires its own add-on: Bring Your Own Database for external MySQL, and Bring Your Own Storage for external S3 or R2. Once eligible, workspace administrators configure infrastructure under Settings → Infrastructure.',

    'How to configure BYOD step by step',

    'First, provision an empty MySQL database in your cloud account. Create a dedicated application user with privileges on that database only — avoid using the RDS master account for daily connections. Enable SSL on RDS and plan to check Use SSL in helpefi infrastructure settings.',

    'Second, configure network access. Add an inbound security group rule allowing TCP 3306 from helpefi egress IPs only. Do not expose the database to 0.0.0.0/0. Egress IPs are shown in the infrastructure admin UI and configured via HELPEFI_EGRESS_IPS on the platform.',

    'Third, open Settings → Infrastructure in your workspace. Set database mode to External (your MySQL). Enter host, port (3306), database name, username, and password. Run Test connection before saving. On first save with external mode, acknowledge that switching will migrate existing workspace data from helpefi-managed storage.',

    'Fourth, verify and migrate. After saving, helpefi verifies connectivity, runs tenant migrations against your empty database if needed, and can migrate existing data from managed storage through a background job. Monitor migration status in the infrastructure settings page until complete.',

    'How to configure BYOS step by step',

    'Create a private S3 bucket or R2 bucket in your chosen region. Block all public access. For S3, attach an IAM policy scoped to a prefix such as helpefi/{tenant_id} with ListBucket and object read/write/delete on that prefix only. For R2, create an API token with Object Read and Write on the bucket.',

    'In Settings → Infrastructure, set storage mode to External. Choose driver AWS S3 or Cloudflare R2. Enter bucket, region, endpoint (required for R2), prefix, access key ID, and secret access key. Run Test connection, then save and verify.',

    'helpefi serves attachments and avatars via signed URLs. Keep the bucket private; do not enable public ACLs or anonymous read policies.',

    'Migrating from helpefi-managed infrastructure',

    'Existing workspaces on managed database and local disk storage can migrate to external infrastructure without re-creating the workspace. Database migration exports the managed tenant database and imports it into your MySQL instance. Storage migration copies files from managed storage to your bucket under the configured prefix.',

    'Both migrations run as background jobs. While migration is queued or running, the UI shows active status and prevents conflicting changes. Plan a maintenance window for large workspaces and confirm your external database has sufficient storage and your bucket policy allows writes before starting.',

    'Security best practices',

    'Use TLS for database connections (especially RDS). Store IAM and R2 credentials with least privilege — prefix-scoped S3 policies and bucket-scoped R2 tokens. Rotate credentials periodically; helpefi preserves existing secrets when you leave password or key fields blank on update.',

    'Infrastructure credentials are encrypted at rest in the central tenant_infrastructure record. They are metadata for connection management, not copies of your business data. Restrict platform admin access in your organization and review platform audit logs for infrastructure changes.',

    'Enable default encryption on S3 (SSE-S3 or SSE-KMS) and configure backup retention on RDS according to your policy. helpefi health checks monitor external connectivity; repeated failures increment a failure count and can trigger alerts to platform administrators.',

    'Troubleshooting common issues',

    'Database connection timeout: verify security groups, network ACLs, and that helpefi egress IPs are allowlisted on port 3306. Access denied: confirm the application user has grants on the correct database name. SSL errors: enable SSL in both RDS and helpefi settings and ensure the server supports TLS.',

    'Storage verify failures on S3: confirm the IAM policy includes ListBucket on the bucket with a prefix condition and object permissions on arn:aws:s3:::bucket/prefix/*. On R2: verify the endpoint URL includes your Cloudflare account ID and the token has write access.',

    'Migration failures: ensure the target database is empty or compatible, the database user has DDL privileges for migrations, and the bucket prefix is writable. Retry verification from the infrastructure page after correcting configuration.',

    'Workspace deletion and customer responsibility',

    'Deleting a workspace on shared SaaS does not drop your external RDS database or S3/R2 bucket. You are responsible for deprovisioning external resources after offboarding. helpefi removes routing, billing association, and encrypted credential metadata from the control plane.',

    'When BYO is not enough: self-hosted and dedicated options',

    'BYOD and BYOS on shared SaaS move data at rest to your cloud but application requests still flow through helpefi servers, and control-plane metadata remains on helpefi. Organizations requiring full isolation — air-gapped networks, customer-managed Redis, or zero third-party metadata — should evaluate helpefi self-hosted Docker deployment or dedicated VPC Terraform templates.',

    'Getting started',

    'Contact helpefi sales or your account manager to enable BYO on your enterprise workspace and purchase the BYOD and/or BYOS add-ons. Prepare your RDS MySQL instance and S3 or R2 bucket using the configuration steps above, then open Settings → Infrastructure to connect, test, and verify.',

    'For step-by-step cloud setup, see the helpefi runbooks for AWS RDS MySQL, AWS S3 bucket policies, and Cloudflare R2 API tokens. Combined with this guide, your team can meet data residency requirements without sacrificing modern AI helpdesk capabilities.',
];
