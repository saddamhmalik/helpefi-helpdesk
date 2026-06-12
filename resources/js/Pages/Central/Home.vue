<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import CentralSeoHead from '../../Components/CentralSeoHead.vue';
import CentralAiDemoWidget from '../../Components/CentralAiDemoWidget.vue';
import IntegrationStackIcon from '../../Components/IntegrationStackIcon.vue';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';

const { t } = useI18n();

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    plans: { type: Array, default: () => [] },
    currency: { type: Object, default: () => ({ code: 'USD', symbol: '$', name: 'US Dollar' }) },
    seo: { type: Object, default: () => ({}) },
    centralDomain: { type: String, default: '' },
    aiDemoEnabled: { type: Boolean, default: true },
});

const platformName = computed(() => t('app.name'));

const workspaceDomainExample = computed(() => {
    const domain = props.centralDomain || 'helpefi.com';

    return `your-company.${domain}`;
});

const { formatPrice } = useCurrency(() => props.currency);

const { billingInterval, intervalSuffix, planPrice, yearlySavingsPercent } = useBillingInterval();

const previewTab = ref('ai');
const featureCategory = ref('operations');
const openFaq = ref(null);

const featureLabels = {
    automation: 'Automation & macros',
    service_catalog: 'Service catalog',
    channels: 'Live chat & channels',
    sla: 'SLA & business hours',
    workspace: 'Multi-brand workspace',
    ai: 'AI assist & deflection',
    integrations: 'Integrations & webhooks',
    assets: 'Asset management (CMDB)',
    custom_domain: 'Custom workspace domain',
    sso: 'SSO (SAML / OIDC)',
    service_desk: 'Service Desk ITSM',
};

const previewTabs = [
    { id: 'ai', label: 'AI Copilot' },
    { id: 'inbox', label: t('central.shared_inbox') },
    { id: 'chat', label: t('central.live_chat') },
    { id: 'servicedesk', label: t('nav.service_desk') },
    { id: 'analytics', label: t('central.analytics') },
];

const aiCapabilities = [
    {
        title: 'Agent Copilot',
        body: 'Side-panel assistant on every ticket — summarize threads, draft replies, suggest KB articles, and recommend next steps with full context.',
        icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        accent: 'from-violet-500/20 to-purple-500/10',
    },
    {
        title: 'Reply drafts & summaries',
        body: 'One-click AI reply drafts and thread summaries in the composer. Agents review before sending — faster responses without losing the human touch.',
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        accent: 'from-indigo-500/20 to-blue-500/10',
    },
    {
        title: 'Customer deflection',
        body: 'AI answers on your portal and live chat widget before customers submit tickets. Semantic search finds the right article even when wording differs.',
        icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        accent: 'from-fuchsia-500/20 to-pink-500/10',
    },
    {
        title: 'AI triage',
        body: 'New tickets analyzed on creation for suggested priority, tags, and routing hints. Agents accept or override — smarter queues from day one.',
        icon: 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
        accent: 'from-cyan-500/20 to-sky-500/10',
    },
];

const featureCategories = [
    { id: 'operations', label: t('central.ticket_operations'), icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { id: 'channels', label: t('settings.groups.channels'), icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { id: 'selfservice', label: t('central.self-service'), icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { id: 'automation', label: t('central.automation_ai'), icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { id: 'itsm', label: t('central.service_desk_itsm'), icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { id: 'platform', label: t('central.platform'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
];

const categoryContent = {
    operations: {
        title: t('central.run_support_like_a_well-oiled_machine'),
        description: t('central.every_conversation_becomes_a_trackable_ticket_with_ownership_priority_'),
        highlights: [
            'Split-pane agent workspace with queue, conversation, and sidebar',
            'Saved views, filters, bulk actions, merge/split, and custom fields',
            'Assignments, teams, departments, skills routing, and collision detection',
            'Side conversations to email third parties without merging tickets',
            'Time tracking, watchers, CCs, and full audit trail on every ticket',
            'Real-time updates, presence, and scheduled report delivery',
        ],
    },
    channels: {
        title: t('central.meet_customers_where_they_are'),
        description: t('central.email_live_chat_sms_and_your_branded_portal_all_feed_the_same_queue_mu'),
        highlights: [
            'Inbound email via webhook, IMAP, or OAuth with CC parsing',
            'Embeddable live chat widget with visitor context and deflection',
            'Branded customer portal with guest and authenticated ticket tracking',
            'Multi-brand portals, inboxes, KB skins, and ticket forms',
            'CSAT surveys on portal and email after resolve/close',
            'Twilio SMS messaging and real-time agent notifications',
        ],
    },
    selfservice: {
        title: t('central.deflect_tickets_before_they_arrive'),
        description: t('central.publish_a_searchable_knowledge_base_route_structured_requests_through_'),
        highlights: [
            'Rich-text articles with collections, versions, and locale support',
            'Semantic KB search and suggested articles on portal submit',
            'Customer-facing AI deflection on portal and live chat',
            'Service catalog with categories, request types, and approval flows',
            'Public help center linked from your portal and chat widget',
            'CSAT feedback to measure article and deflection effectiveness',
        ],
    },
    automation: {
        title: t('central.automate_the_repetitive_work'),
        description: t('central.route_tickets_automatically_apply_macros_chain_multi-step_workflows_an'),
        highlights: [
            'Round-robin and load-based auto-assignment rules',
            'Canned responses with placeholders and macro actions',
            'Automation triggers with delays, webhooks, and auto-tagging',
            'AI-suggested replies, thread summaries, and KB assist',
            'SLA policies with business hours, escalations, and team SLAs',
            'Outbound webhooks with HMAC-signed ticket event delivery',
        ],
    },
    itsm: {
        title: t('central.full_itsm_on_top_of_your_helpdesk'),
        description: t('central.enterprise_teams_get_itil-style_workflows_type_queues_approvals_change'),
        highlights: [
            'Service Desk hub with incident, request, change, and problem queues',
            'Catalog and change approval workflows with email and in-app inbox',
            'Change records with risk, schedule, CAB, and change calendar',
            'Problem management with root cause, known errors, and linked incidents',
            'Major incident declaration, war room, and post-incident review',
            'Asset CMDB with network discovery linked to tickets and catalog items',
        ],
    },
    platform: {
        title: t('central.built_for_teams_that_scale'),
        description: t('central.role-based_access_crm_context_enterprise_integrations_sso_and_billing_'),
        highlights: [
            'Admin, agent, and customer roles with custom permissions',
            'HubSpot, Salesforce, Shopify, Slack, Jira, and Linear integrations',
            'Customer context panel with CRM, commerce, and health scores in the ticket sidebar',
            'Network asset discovery imports devices into your CMDB automatically',
            'Two-factor auth, SSO (SAML/OIDC), audit logs, and data retention',
            'Custom workspace domain on Enterprise plans',
            'REST API, OpenAPI docs, and usage-based plan limits',
        ],
    },
};

const allFeatures = [
    { title: t('central.shared_inbox_tickets'), description: t('central.manage_email_chat_sms_and_portal_requests_in_one_workspace_with_merge_'), icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { title: t('central.agent_workspace'), description: t('central.split-pane_queue_conversation_and_details_sidebar_with_real-time_updat'), icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z' },
    { title: t('central.live_chat_sms'), description: t('central.embed_a_chat_widget_on_your_site_and_receive_sms_via_twilio_every_conv'), icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { title: t('central.knowledge_base_portal'), description: t('central.publish_help_articles_with_semantic_search_locale_support_and_a_brande'), icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { title: t('settings.service_catalog'), description: t('central.structured_request_types_on_your_portal_with_per-item_approval_workflo'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    { title: t('central.service_desk_itsm'), description: t('central.itil_type_queues_change_calendar_problem_linking_catalog_approvals_and'), icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: t('settings.sla_business_hours'), description: t('central.set_response_targets_escalation_rules_team_slas_and_operating_hours_wi'), icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { title: t('central.automation_macros'), description: t('central.multi-step_automation_chains_canned_responses_auto-assignment_webhooks'), icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { title: t('central.ai_assist_deflection'), description: t('central.draft_replies_summarize_threads_surface_kb_articles_for_agents_and_def'), icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { title: t('central.csat_reporting'), description: t('central.measure_satisfaction_on_portal_and_email_build_saved_reports_and_sched'), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { title: t('central.multi-brand_workspaces'), description: t('central.run_multiple_brands_with_separate_portals_inboxes_kb_skins_and_routing'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { title: t('central.integrations_crm'), description: t('central.connect_slack_jira_linear_hubspot_salesforce_shopify_teams_and_custom_'), icon: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z' },
    { title: t('central.asset_management'), description: t('central.track_hardware_and_software_assets_link_them_to_contacts_and_tickets_a'), icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
    { title: t('central.contacts_organizations'), description: t('central.track_customers_companies_vip_tags_activity_timelines_and_crm_context_'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { title: t('central.security_sso'), description: t('central.two-factor_authentication_saml_oidc_single_sign-on_role_permissions_au'), icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { title: t('central.workforce_management'), description: t('central.organize_agents_into_teams_and_departments_with_skills_routing_perform'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
];

const featurePalette = [
    { icon: 'bg-gradient-to-br from-blue-500 to-indigo-600 shadow-blue-500/30', glow: 'from-blue-400 to-indigo-400' },
    { icon: 'bg-gradient-to-br from-sky-500 to-blue-600 shadow-sky-500/30', glow: 'from-sky-400 to-blue-400' },
    { icon: 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/30', glow: 'from-emerald-400 to-teal-400' },
    { icon: 'bg-gradient-to-br from-violet-500 to-purple-600 shadow-violet-500/30', glow: 'from-violet-400 to-purple-400' },
    { icon: 'bg-gradient-to-br from-fuchsia-500 to-pink-600 shadow-fuchsia-500/30', glow: 'from-fuchsia-400 to-pink-400' },
    { icon: 'bg-gradient-to-br from-red-500 to-rose-600 shadow-red-500/30', glow: 'from-red-400 to-rose-400' },
    { icon: 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/30', glow: 'from-amber-400 to-orange-400' },
    { icon: 'bg-gradient-to-br from-cyan-500 to-blue-600 shadow-cyan-500/30', glow: 'from-cyan-400 to-blue-400' },
];

const featureGroupDefs = [
    { label: 'Inbox & workspace', hint: 'Where agents live every day', indices: [0, 1, 13, 15] },
    { label: 'Channels & self-service', hint: 'Meet customers and deflect tickets', indices: [2, 3, 4, 8] },
    { label: 'Automation, SLA & insights', hint: 'Work smarter and measure results', indices: [6, 7, 9, 10] },
    { label: 'ITSM & enterprise', hint: 'Scale with IT and security', indices: [5, 11, 12, 14] },
];

const bentoItems = [
    { title: t('central.one_inbox_every_channel'), body: 'Email, chat, SMS, and portal tickets land in a single queue with smart routing and multi-brand support.', span: 'lg:col-span-2', accent: 'from-blue-600/20 to-indigo-600/10', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { title: t('central.sla_timers'), body: 'Never miss a deadline with visual countdowns, business hours, and breach alerts.', span: '', accent: 'from-amber-500/20 to-orange-500/10', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { title: t('central.ai_reply_drafts'), body: 'Agents get suggested responses and KB articles; customers get deflection before they submit.', span: '', accent: 'from-violet-600/20 to-purple-600/10', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { title: t('central.customer_portal'), body: 'Branded self-service hub for articles, service catalog requests, and ticket tracking.', span: 'lg:col-span-2', accent: 'from-emerald-600/20 to-teal-600/10', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { title: t('central.service_desk_itsm'), body: 'Incidents, changes, problems, approvals, and major incident war rooms on Enterprise.', span: '', accent: 'from-red-500/20 to-rose-500/10', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: t('central.real-time_workspace'), body: 'Live ticket updates, agent presence, and collision warnings — no refresh required.', span: '', accent: 'from-cyan-500/20 to-sky-500/10', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
];

const differentiatorDefs = [
    { titleKey: 'diff_support_itsm_title', bodyKey: 'diff_support_itsm_body', badgeKey: 'built_different_badge_itsm', accent: 'from-red-500/15 to-rose-500/5', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { titleKey: 'diff_customer_360_title', bodyKey: 'diff_customer_360_body', accent: 'from-blue-500/15 to-indigo-500/5', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { titleKey: 'diff_asset_discovery_title', bodyKey: 'diff_asset_discovery_body', accent: 'from-emerald-500/15 to-teal-500/5', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
    { titleKey: 'diff_skills_sla_title', bodyKey: 'diff_skills_sla_body', accent: 'from-amber-500/15 to-orange-500/5', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { titleKey: 'diff_multi_brand_title', bodyKey: 'diff_multi_brand_body', accent: 'from-violet-500/15 to-purple-500/5', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { titleKey: 'diff_custom_domain_title', bodyKey: 'diff_custom_domain_body', badgeKey: 'built_different_badge_enterprise', accent: 'from-slate-500/15 to-slate-700/5', icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
    { titleKey: 'diff_semantic_kb_title', bodyKey: 'diff_semantic_kb_body', accent: 'from-cyan-500/15 to-sky-500/5', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { titleKey: 'diff_realtime_title', bodyKey: 'diff_realtime_body', accent: 'from-fuchsia-500/15 to-pink-500/5', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { titleKey: 'diff_performance_title', bodyKey: 'diff_performance_body', accent: 'from-indigo-500/15 to-blue-500/5', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { titleKey: 'diff_catalog_approvals_title', bodyKey: 'diff_catalog_approvals_body', accent: 'from-teal-500/15 to-emerald-500/5', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
];

const differentiators = computed(() => differentiatorDefs.map((item) => ({
    title: t(`central.${item.titleKey}`),
    body: t(`central.${item.bodyKey}`),
    badge: item.badgeKey ? t(`central.${item.badgeKey}`) : null,
    accent: item.accent,
    icon: item.icon,
})));

const builtDifferentSubtitle = computed(() => t('central.built_different_subtitle', { brand: platformName.value }));

const steps = [
    { title: t('central.create_your_workspace'), body: 'Pick a subdomain, register in seconds, and get a dedicated environment for your team — no credit card required.', detail: 'Your workspace lives on its own subdomain with isolated data, roles, and admin access.' },
    { title: t('central.connect_your_channels'), body: 'Follow the guided setup wizard to configure email, chat widget, SMS, portal branding, SLA policies, and service catalog.', detail: 'Inbound email, outbound SMTP, chat embed code, and Twilio SMS — configured step by step.' },
    { title: t('central.invite_your_team_go_live'), body: 'Add agents, assign roles, publish your knowledge base, and start resolving tickets from day one.', detail: 'Full platform access during your free trial. Upgrade to Enterprise for Service Desk ITSM when you need it.' },
];

const integrationGroups = [
    {
        label: 'Channels',
        items: [
            { id: 'email', name: 'Email' },
            { id: 'live-chat', name: 'Live chat' },
            { id: 'sms', name: 'SMS' },
            { id: 'portal', name: 'Portal' },
        ],
    },
    {
        label: 'Collaboration',
        items: [
            { id: 'slack', name: 'Slack' },
            { id: 'teams', name: 'Microsoft Teams' },
            { id: 'jira', name: 'Jira' },
            { id: 'linear', name: 'Linear' },
        ],
    },
    {
        label: 'CRM & commerce',
        items: [
            { id: 'hubspot', name: 'HubSpot' },
            { id: 'salesforce', name: 'Salesforce' },
            { id: 'shopify', name: 'Shopify' },
        ],
    },
    {
        label: 'Platform',
        items: [
            { id: 'webhooks', name: 'Webhooks' },
            { id: 'rest-api', name: 'REST API' },
            { id: 'sso', name: 'SSO' },
        ],
    },
];

const planTaglines = {
    starter: 'Essentials for small teams',
    professional: 'Automation, SLA & channels',
    enterprise: 'ITSM, AI, SSO & custom domain',
};

const trustBadges = [
    'No credit card required',
    'Live in under 2 minutes',
    'Full platform access',
    'Cancel anytime',
];

const heroAiPills = [
    { label: 'Agent Copilot', icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z' },
    { label: 'Smart deflection', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { label: 'AI triage', icon: 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z' },
    { label: 'Reply drafts', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
];

const heroAiStats = [
    { value: '40%', label: 'fewer tickets', detail: 'with AI deflection' },
    { value: '3×', label: 'faster replies', detail: 'with Copilot drafts' },
    { value: 'Included', label: 'in free trial', detail: 'no add-on fees' },
];

const outcomeStats = [
    { value: '3×', label: 'Faster first response', detail: 'vs. scattered inboxes' },
    { value: '40%', label: 'Fewer repeat tickets', detail: 'with KB deflection' },
    { value: '94%', label: 'Average CSAT', detail: 'across active teams' },
    { value: '2 min', label: 'Median setup time', detail: 'from signup to first ticket' },
];

const painPoints = [
    {
        pain: 'Tickets scattered across email, Slack, and spreadsheets',
        gain: 'One unified inbox for every channel',
        icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        oldTools: ['Gmail', 'Slack', 'Sheets'],
    },
    {
        pain: 'Agents switching between 4+ tools to resolve a ticket',
        gain: 'Full context in a single workspace',
        icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z',
        oldTools: ['CRM', 'Chat', 'KB', 'ITSM'],
    },
    {
        pain: 'ITSM bolted on as a separate expensive product',
        gain: 'Service Desk ITSM built in on Enterprise',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        oldTools: ['Separate ITSM tool', 'Extra license'],
    },
    {
        pain: 'Customers waiting hours for a first reply',
        gain: 'SLA timers, AI drafts, and smart routing',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        oldTools: ['Manual triage', 'No SLA'],
    },
];

const stackSavings = [
    { label: 'Tools replaced', value: '3–5' },
    { label: 'Avg. cost saved', value: '$2k+/mo' },
    { label: 'Setup time', value: '<2 min' },
    { label: 'Context lost', value: 'Zero' },
];

const heroAvatars = [
    { initials: 'SC', color: 'from-blue-500 to-indigo-600' },
    { initials: 'MW', color: 'from-emerald-500 to-teal-600' },
    { initials: 'ER', color: 'from-violet-500 to-purple-600' },
];

const comparisons = [
    { feature: 'Unified inbox (email + chat + portal + SMS)', us: true, them: false },
    { feature: 'Built-in knowledge base & portal', us: true, them: 'Add-on' },
    { feature: 'Service catalog with approval workflows', us: true, them: 'Enterprise only' },
    { feature: 'Service Desk ITSM (changes, problems, major incidents)', us: true, them: 'Separate product' },
    { feature: 'Customer 360 with CRM & commerce in the ticket sidebar', us: true, them: 'Add-on' },
    { feature: 'Network asset discovery & CMDB', us: true, them: false },
    { feature: 'Skills routing & tier-based SLA policies', us: true, them: 'Rare' },
    { feature: 'Semantic KB search & AI deflection', us: true, them: 'Extra cost' },
    { feature: 'Major incident war rooms & post-incident review', us: true, them: 'Separate product' },
    { feature: 'SLA policies & business hours', us: true, them: 'Enterprise only' },
    { feature: 'Multi-brand workspaces', us: true, them: false },
    { feature: 'Dedicated workspace URL + custom domain', us: true, them: 'Add-on' },
    { feature: 'SSO (SAML / OIDC) & agent performance scoring', us: true, them: 'Enterprise only' },
    { feature: 'Free trial, no credit card', us: true, them: 'Limited' },
];

const faqs = computed(() => [
    { q: `How does the ${props.trialDays}-day free trial work?`, a: `Sign up and get full platform access for ${props.trialDays} days. No credit card required. When your trial ends, choose a plan that fits your team to keep your workspace active.` },
    { q: 'Can I use my own domain?', a: 'Yes. Every workspace includes a dedicated subdomain (e.g. acme.yourplatform.com). On Enterprise plans, connect your own domain (e.g. support.yourcompany.com) from Settings → Custom domain — add the DNS records we provide, verify ownership, and optionally redirect visitors from your platform subdomain to your branded URL.' },
    { q: 'What channels are supported?', a: 'Email (inbound webhook, IMAP, and OAuth), live chat widget, SMS via Twilio, and a branded customer portal. All channels create tickets in the same shared inbox with multi-brand routing.' },
    { q: 'What is Service Desk ITSM?', a: 'Service Desk is an Enterprise add-on that adds ITIL-style workflows on top of your existing tickets: type queues for incidents, requests, changes, and problems; catalog and change approvals; change calendar with CAB; problem records with linked incidents; and major incident war rooms with post-incident review.' },
    { q: 'Is there an API?', a: 'Yes. A REST API covers authentication, tickets, contacts, service desk records, knowledge base, and billing snapshots — plus OpenAPI documentation for custom integrations and internal tooling.' },
    { q: 'Do you support SSO?', a: 'Enterprise plans include SAML and OIDC single sign-on. Configure your identity provider in Settings → Security and agents sign in with your corporate credentials alongside optional two-factor authentication.' },
    { q: 'How does pricing work after the trial?', a: 'Plans are based on team size (agents) and monthly ticket volume. Professional includes automation, SLA, service catalog, and live chat. Enterprise adds AI, integrations, assets, SSO, custom domain, and Service Desk ITSM. Upgrade anytime from your workspace billing settings.' },
    { q: 'Can I migrate existing data?', a: 'Export tickets and contacts via CSV, or use the API to import data programmatically. Our unified inbox and service catalog make it straightforward to ramp without losing context.' },
]);

const categoryHighlightIcons = {
    operations: [
        'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
        'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    ],
    channels: [
        'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
        'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    ],
    selfservice: [
        'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    ],
    automation: [
        'M13 10V3L4 14h7v7l9-11h-7z',
        'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
        'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    ],
    itsm: [
        'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    ],
    platform: [
        'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
        'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    ],
};

const activeCategory = computed(() => categoryContent[featureCategory.value]);
const categoryThemes = {
    operations: { gradient: 'from-blue-600 via-blue-700 to-indigo-700', iconBg: 'bg-blue-600', iconBgMuted: 'bg-blue-100 text-blue-700 dark:text-blue-300' },
    channels: { gradient: 'from-emerald-600 via-teal-600 to-cyan-700', iconBg: 'bg-emerald-600', iconBgMuted: 'bg-emerald-100 text-emerald-700 dark:text-emerald-300' },
    selfservice: { gradient: 'from-violet-600 via-purple-600 to-indigo-700', iconBg: 'bg-violet-600', iconBgMuted: 'bg-violet-100 text-violet-700 dark:text-violet-300' },
    automation: { gradient: 'from-amber-500 via-orange-500 to-red-600', iconBg: 'bg-amber-500', iconBgMuted: 'bg-amber-100 text-amber-800' },
    itsm: { gradient: 'from-red-600 via-rose-600 to-orange-700', iconBg: 'bg-red-600', iconBgMuted: 'bg-red-100 text-red-700 dark:text-red-300' },
    platform: { gradient: 'from-slate-700 via-slate-800 to-slate-900', iconBg: 'bg-slate-800', iconBgMuted: 'bg-slate-200 text-slate-700 dark:text-slate-300' },
};

const categoryHints = {
    operations: 'Inbox, workspace & collaboration',
    channels: 'Email, chat, SMS & portal',
    selfservice: 'KB, catalog & deflection',
    automation: 'Rules, macros & AI',
    itsm: 'Changes, problems & war rooms',
    platform: 'Integrations, SSO & API',
};

const categoryFeatureMap = {
    operations: [0, 1, 7],
    channels: [2, 10, 3],
    selfservice: [3, 4, 8],
    automation: [7, 8, 6],
    itsm: [5, 4, 12],
    platform: [11, 13, 14],
};

const activeTheme = computed(() => categoryThemes[featureCategory.value] ?? categoryThemes.operations);

const categoryRelatedFeatures = computed(() => {
    const indices = categoryFeatureMap[featureCategory.value] ?? [0, 1, 2];
    return indices.map((index) => allFeatures[index]).filter(Boolean);
});

const primaryHighlights = computed(() => activeCategory.value.highlights.slice(0, 4));
const secondaryHighlights = computed(() => activeCategory.value.highlights.slice(4));

const primaryHighlightItems = computed(() => {
    const icons = categoryHighlightIcons[featureCategory.value] ?? categoryHighlightIcons.operations;

    return primaryHighlights.value.map((text, index) => ({
        text,
        icon: icons[index] ?? icons[0],
    }));
});



const formatLimit = (value) => (value === null || value === 'unlimited' ? 'Unlimited' : value);

const planHighlights = (plan) => {
    const agents = formatLimit(plan.limits?.agents);
    const tickets = formatLimit(plan.limits?.tickets_monthly);
    const items = [
        `${agents} team members`,
        `${tickets} tickets / month`,
    ];

    (plan.features ?? []).forEach((key) => {
        if (featureLabels[key]) {
            items.push(featureLabels[key]);
        }
    });

    return items;
};

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const featureGroups = computed(() => featureGroupDefs.map((group) => ({
    ...group,
    features: group.indices.map((index) => ({
        ...allFeatures[index],
        palette: featurePalette[index % featurePalette.length],
    })).filter((feature) => feature.title),
})));
</script>

<template>
    <CentralSeoHead page="home" :brand="platformName" :trial-days="trialDays" :seo="seo" />
    <CentralLayout :brand="platformName" :trial-days="trialDays">
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-40 top-0 h-[36rem] w-[36rem] rounded-full bg-blue-600/30 blur-3xl" />
                <div class="absolute right-[-10%] top-10 h-[28rem] w-[28rem] rounded-full bg-indigo-500/25 blur-3xl" />
                <div class="absolute bottom-[-10%] left-1/3 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl" />
                <div class="absolute right-1/4 top-1/3 h-72 w-72 rounded-full bg-fuchsia-600/15 blur-3xl" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff06_1px,transparent_1px),linear-gradient(to_bottom,#ffffff06_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pb-20 sm:pt-14 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="max-w-xl lg:max-w-none">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-semibold text-emerald-300 backdrop-blur sm:px-4 sm:text-xs">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                                </span>
                                {{ trialDays }}-day free trial · No credit card · Cancel anytime
                            </div>
                            <a
                                href="#ai"
                                class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/40 bg-gradient-to-r from-violet-600/25 to-fuchsia-600/20 px-3 py-1.5 text-[11px] font-bold text-violet-200 shadow-lg shadow-violet-900/20 backdrop-blur transition hover:border-violet-300/50 hover:text-white sm:px-4 sm:text-xs"
                            >
                                <svg class="h-3.5 w-3.5 text-violet-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                AI Copilot built in
                            </a>
                        </div>

                        <h1 class="mt-6 text-3xl font-extrabold leading-[1.08] tracking-tight sm:mt-8 sm:text-[2.75rem] sm:leading-[1.05] lg:text-5xl xl:text-[3.5rem]">
                            Stop juggling tools.
                            <span class="mt-1 block bg-gradient-to-r from-blue-400 via-indigo-300 to-violet-400 bg-clip-text text-transparent">
                                Start delighting customers.
                            </span>
                        </h1>

                        <p class="mt-5 text-base leading-relaxed text-slate-300 sm:mt-6 sm:text-lg lg:text-xl">
                            <span class="font-semibold text-violet-200">Built-in AI</span> drafts replies, deflects tickets, and triages your queue —
                            plus inbox, chat, knowledge base, SLAs, and ITSM in one workspace your team will actually love.
                        </p>

                        <div class="mt-6 overflow-hidden rounded-2xl border border-violet-500/25 bg-gradient-to-br from-violet-950/50 via-slate-900/80 to-indigo-950/50 p-4 ring-1 ring-violet-400/10 sm:mt-7 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-bold uppercase tracking-wider text-violet-300">AI-native from day one</p>
                                <a href="#ai" class="shrink-0 text-[11px] font-semibold text-violet-300 underline-offset-2 hover:text-white hover:underline sm:text-xs">See it live →</a>
                            </div>
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-0.5 sm:flex-wrap sm:overflow-visible">
                                <span
                                    v-for="pill in heroAiPills"
                                    :key="pill.label"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] font-medium text-slate-200 sm:text-xs"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="pill.icon" /></svg>
                                    {{ pill.label }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 border-t border-white/10 pt-4 sm:gap-3">
                                <div v-for="stat in heroAiStats" :key="stat.label" class="text-center sm:text-left">
                                    <p class="text-base font-extrabold text-white sm:text-lg">{{ stat.value }}</p>
                                    <p class="text-[10px] font-medium text-violet-200 sm:text-xs">{{ stat.label }}</p>
                                    <p class="hidden text-[10px] text-slate-500 dark:text-slate-400 sm:block">{{ stat.detail }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-7 flex flex-col gap-3 sm:mt-9 sm:flex-row sm:items-center">
                            <Link
                                href="/register"
                                class="group relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500 hover:shadow-blue-500/50 sm:px-8 sm:py-4 sm:text-base"
                            >
                                <span class="sm:hidden">Start free trial</span>
                                <span class="hidden sm:inline">Start free trial — it takes 2 minutes</span>
                                <svg class="h-5 w-5 transition group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <a
                                href="#ai"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-6 py-4 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/10"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Try AI live
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2">
                            <span v-for="badge in trustBadges" :key="badge" class="inline-flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500">
                                <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                {{ badge }}
                            </span>
                        </div>

                        <div class="mt-8 flex flex-col gap-4 border-t border-white/10 pt-6 sm:mt-10 sm:flex-row sm:items-center sm:pt-8">
                            <div class="flex -space-x-2.5">
                                <span
                                    v-for="t in heroAvatars"
                                    :key="t.initials"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-950 bg-gradient-to-br text-xs font-bold text-white"
                                    :class="t.color"
                                >
                                    {{ t.initials }}
                                </span>
                            </div>
                            <div>
                                <div class="flex items-center gap-0.5">
                                    <svg v-for="n in 5" :key="n" class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                </div>
                                <p class="mt-0.5 text-sm text-slate-400 dark:text-slate-500">
                                    <span class="font-semibold text-white">Trusted by support & IT teams</span> worldwide
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto w-full max-w-xl md:max-w-none lg:pl-4">
                        <div class="md:hidden rounded-2xl border border-violet-500/25 bg-gradient-to-br from-violet-950/40 to-slate-900/80 p-5 shadow-xl backdrop-blur-xl ring-1 ring-violet-400/15">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/20 text-violet-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-white">AI Copilot on every ticket</p>
                                    <p class="text-xs text-violet-200">Drafts, deflection & triage included</p>
                                </div>
                            </div>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-300">
                                <li v-for="item in ['Agent Copilot side panel with full context', 'Portal & chat deflection before tickets', 'Shared inbox, KB, SLA & ITSM in one place']" :key="item" class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    {{ item }}
                                </li>
                            </ul>
                            <a href="#ai" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-violet-300 hover:text-white">
                                Try AI live
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </a>
                        </div>

                        <div class="relative mt-6 hidden md:block lg:mt-0">
                            <div class="pointer-events-none absolute -right-2 -top-3 z-10 flex items-center gap-1.5 rounded-full border border-violet-400/40 bg-violet-600/90 px-3 py-1.5 text-[11px] font-bold text-white shadow-lg shadow-violet-900/40 backdrop-blur">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white dark:bg-slate-900 opacity-75" />
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white dark:bg-slate-900" />
                                </span>
                                AI Copilot live
                            </div>
                            <div class="pointer-events-none absolute -inset-4 rounded-3xl bg-gradient-to-r from-blue-600/20 via-indigo-500/20 to-violet-600/20 blur-2xl" />
                            <div class="relative rounded-2xl border border-white/15 bg-white/5 p-2 shadow-2xl shadow-black/60 backdrop-blur-xl ring-1 ring-white/10">
                                <div class="overflow-hidden rounded-xl bg-slate-900 ring-1 ring-white/10">
                                <div class="flex items-center gap-2 border-b border-white/10 px-3 py-2.5 sm:px-4 sm:py-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-400/90" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400/90" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/90" />
                                    <span class="ml-2 min-w-0 truncate text-[10px] text-slate-500 dark:text-slate-400 sm:text-xs">{{ workspaceDomainExample }}</span>
                                </div>
                                <div class="flex gap-1 overflow-x-auto border-b border-white/10 bg-slate-950/60 px-2 py-2 sm:flex-wrap sm:px-3">
                                    <button
                                        v-for="tab in previewTabs"
                                        :key="tab.id"
                                        type="button"
                                        class="shrink-0 rounded-lg px-2.5 py-1.5 text-[10px] font-semibold transition sm:px-3 sm:text-[11px]"
                                        :class="previewTab === tab.id ? 'bg-white/15 text-white ring-1 ring-white/20' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'"
                                        @click="previewTab = tab.id"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>

                                <div v-if="previewTab === 'inbox'" class="grid grid-cols-5">
                                    <div class="col-span-2 border-r border-white/10 bg-slate-950/90 p-4">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('central.open_tickets_12') }}</p>
                                        <div class="mt-3 space-y-2">
                                            <div class="rounded-lg bg-blue-500/20 px-3 py-2 ring-1 ring-blue-500/40">
                                                <p class="text-xs font-medium text-white">{{ $t('central.payment_failed_need_help') }}</p>
                                                <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.sarah_sla_18m_assigned_to_you') }}</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">{{ $t('central.chat_shipping_question') }}</p>
                                                <p class="mt-0.5 text-[10px] text-emerald-400">{{ $t('central.live_waiting') }}</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">{{ $t('central.api_rate_limit_error') }}</p>
                                                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.dev_team_14m_ago') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-3 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium">{{ $t('central.payment_failed_need_help') }}</p>
                                            <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-medium text-amber-300">{{ $t('central.high_sla_18m') }}</span>
                                        </div>
                                        <div class="mt-4 space-y-3">
                                            <div class="rounded-lg bg-white/5 p-3"><p class="text-xs text-slate-300">{{ $t('central.hi_my_subscription_payment_failed_but_i_was_still_charged') }}</p></div>
                                            <div class="rounded-lg bg-blue-600/25 p-3 ring-1 ring-blue-500/30"><p class="text-xs text-blue-100">{{ $t('central.i_can_see_the_duplicate_charge_refunding_now_and_extending_your_plan_b') }}</p></div>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="rounded-md bg-violet-500/20 px-2 py-1 text-[10px] text-violet-200">{{ $t('central.ai_draft_ready') }}</span>
                                            <span class="rounded-md bg-white/5 px-2 py-1 text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.billing') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="previewTab === 'ai'" class="grid grid-cols-5">
                                    <div class="col-span-2 border-r border-white/10 bg-slate-950/90 p-4">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ticket #1042</p>
                                        <p class="mt-2 text-xs font-medium text-white">Payment failed — need help</p>
                                        <div class="mt-4 space-y-2">
                                            <div class="rounded-lg bg-white/5 px-3 py-2"><p class="text-[10px] text-slate-300">Customer: charged twice after failed payment</p></div>
                                            <div class="rounded-lg bg-blue-600/20 px-3 py-2 ring-1 ring-blue-500/30"><p class="text-[10px] text-blue-100">Agent: refund initiated, plan extended</p></div>
                                        </div>
                                        <span class="mt-4 inline-flex rounded-md bg-violet-500/20 px-2 py-1 text-[10px] text-violet-200">AI draft ready</span>
                                    </div>
                                    <div class="col-span-3 flex flex-col bg-gradient-to-b from-violet-950/40 to-slate-950/90 p-4">
                                        <div class="flex items-center justify-between border-b border-violet-500/20 pb-3">
                                            <p class="text-xs font-semibold text-violet-200">Agent Copilot</p>
                                            <span class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] text-violet-300">live</span>
                                        </div>
                                        <div class="mt-3 flex-1 space-y-2">
                                            <div class="ml-auto max-w-[90%] rounded-xl rounded-br-sm bg-violet-600/50 px-3 py-2"><p class="text-[10px] text-violet-50">Summarize and suggest next steps</p></div>
                                            <div class="max-w-[95%] rounded-xl rounded-bl-sm border border-violet-500/20 bg-white/5 px-3 py-2"><p class="text-[10px] leading-relaxed text-slate-200">Duplicate charge confirmed. Refund queued; subscription extended 30 days. Send confirmation email and close when refund clears.</p></div>
                                        </div>
                                        <p class="mt-3 text-[9px] text-violet-300/70">3 KB articles matched · Insert draft →</p>
                                    </div>
                                </div>

                                <div v-else-if="previewTab === 'chat'" class="p-4">
                                    <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-xs text-emerald-300">V</span>
                                        <div>
                                            <p class="text-sm font-medium">{{ $t('central.visitor_on_pricing') }}</p>
                                            <p class="text-[10px] text-emerald-400">{{ $t('central.online_san_francisco') }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">Do you offer annual billing?</p></div>
                                        <div class="ml-auto max-w-[80%] rounded-2xl rounded-br-md bg-blue-600/40 px-3 py-2"><p class="text-xs text-blue-50">{{ $t('central.yes_annual_plans_save_20_i_can_send_details_to_your_email') }}</p></div>
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">{{ $t('central.perfect_please_do') }}</p></div>
                                    </div>
                                    <p class="mt-4 text-center text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.conversation_saved_as_ticket_1042') }}</p>
                                </div>

                                <div v-else-if="previewTab === 'servicedesk'" class="p-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-semibold text-red-300">{{ $t('central.major_incident_active') }}</p>
                                        <span class="rounded-full bg-red-500/20 px-2 py-0.5 text-[10px] font-medium text-red-200">{{ $t('central.war_room') }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="rounded-lg bg-red-500/10 p-2 ring-1 ring-red-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.incidents') }}</p>
                                            <p class="text-lg font-bold text-white">8</p>
                                            <p class="text-[10px] text-red-300">{{ $t('central.2_major') }}</p>
                                        </div>
                                        <div class="rounded-lg bg-violet-500/10 p-2 ring-1 ring-violet-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.changes') }}</p>
                                            <p class="text-lg font-bold text-white">3</p>
                                            <p class="text-[10px] text-violet-300">{{ $t('central.1_pending_approval') }}</p>
                                        </div>
                                        <div class="rounded-lg bg-amber-500/10 p-2 ring-1 ring-amber-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.problems') }}</p>
                                            <p class="text-lg font-bold text-white">2</p>
                                        </div>
                                        <div class="rounded-lg bg-blue-500/10 p-2 ring-1 ring-blue-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.approvals') }}</p>
                                            <p class="text-lg font-bold text-white">4</p>
                                            <p class="text-[10px] text-blue-300">{{ $t('central.awaiting_you') }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2">
                                        <p class="text-xs font-medium text-red-100">{{ $t('central.hd-93001_email_outage') }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.coordinators_3_declared_12m_ago') }}</p>
                                    </div>
                                </div>

                                <div v-else class="p-4">
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.first_response') }}</p><p class="mt-1 text-lg font-bold text-emerald-400">{{ $t('central.4_2m') }}</p><p class="text-[10px] text-emerald-400/80">{{ $t('central.18_vs_last_week') }}</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.csat_score') }}</p><p class="mt-1 text-lg font-bold text-white">94%</p><p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.128_responses') }}</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.resolved_today') }}</p><p class="mt-1 text-lg font-bold text-white">47</p><p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('central.6_open') }}</p></div>
                                    </div>
                                    <div class="mt-4 h-24 rounded-lg bg-gradient-to-t from-blue-600/20 to-transparent p-3">
                                        <div class="flex h-full items-end gap-1">
                                            <div v-for="(h, i) in [40, 55, 35, 70, 50, 85, 60, 75, 90, 65]" :key="i" class="flex-1 rounded-t bg-blue-500/60" :style="{ height: `${h}%` }" />
                                        </div>
                                    </div>
                                    <p class="mt-2 text-center text-[10px] text-slate-500 dark:text-slate-400">{{ $t('central.ticket_volume_last_10_days') }}</p>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-10 sm:py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="stat in outcomeStats"
                        :key="stat.label"
                        class="rounded-2xl border border-slate-100 dark:border-slate-800 bg-gradient-to-br from-slate-50 to-white p-6 text-center shadow-sm"
                    >
                        <p class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ stat.value }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ stat.label }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ stat.detail }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-50 via-white to-white" />
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Why teams switch</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl lg:text-5xl">
                        From tool chaos to one calm workspace
                    </h2>
                    <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                        Most teams pay for 3–5 separate products and still lose context. {{ platformName }} replaces the stack — not adds to it.
                    </p>
                </div>

                <div class="relative mt-16">
                    <div class="absolute left-1/2 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 lg:block">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xl ring-4 ring-slate-100 dark:ring-slate-800">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </span>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl shadow-slate-200/60 lg:grid lg:grid-cols-2">
                        <div class="relative border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-8 sm:p-10 lg:border-b-0 lg:border-r">
                            <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle, #64748b 1px, transparent 1px); background-size: 20px 20px;" />
                            <div class="relative">
                                <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-950/40 px-3 py-1 text-xs font-bold uppercase tracking-wide text-red-600 ring-1 ring-red-100">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500" />
                                    The old way
                                </span>
                                <p class="mt-4 text-lg font-semibold text-slate-800 dark:text-slate-200">Five tabs. Zero context.</p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                    Agents hunt across disconnected tools while customers wait — and nothing syncs.
                                </p>

                                <div class="mt-8 flex flex-wrap gap-2 sm:relative sm:h-36">
                                    <span class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-md sm:absolute sm:left-0 sm:top-0 sm:rotate-[-6deg]">Email</span>
                                    <span class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-md sm:absolute sm:left-24 sm:top-6 sm:rotate-[3deg]">Slack</span>
                                    <span class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-md sm:absolute sm:right-8 sm:top-0 sm:rotate-[6deg]">Spreadsheet</span>
                                    <span class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-md sm:absolute sm:bottom-4 sm:left-8 sm:rotate-[-3deg]">Legacy ITSM</span>
                                    <span class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 shadow-md sm:absolute sm:bottom-0 sm:right-12 sm:rotate-[2deg]">Add-on KB</span>
                                    <svg class="hidden h-full w-full text-slate-300 sm:absolute sm:inset-0 sm:block" fill="none" viewBox="0 0 300 120">
                                        <path d="M40 20 L120 50 M120 50 L220 25 M80 90 L160 60 M160 60 L240 85" stroke="currentColor" stroke-dasharray="4 4" stroke-width="1.5" />
                                    </svg>
                                </div>

                                <ul class="mt-6 space-y-3">
                                    <li
                                        v-for="item in painPoints"
                                        :key="item.pain"
                                        class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50 dark:bg-red-950/40/50 px-4 py-3"
                                    >
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-500">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </span>
                                        <span class="text-sm text-slate-600 dark:text-slate-400 line-through decoration-red-300/60">{{ item.pain }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 p-8 text-white sm:p-10">
                            <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/10 blur-2xl" />
                            <div class="pointer-events-none absolute -bottom-8 -left-8 h-40 w-40 rounded-full bg-violet-400/20 blur-2xl" />
                            <div class="relative">
                                <span class="inline-flex items-center gap-2 rounded-full bg-white dark:bg-slate-900/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white ring-1 ring-white/25">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                    With {{ platformName }}
                                </span>
                                <p class="mt-4 text-lg font-semibold">One workspace. Full picture.</p>
                                <p class="mt-2 text-sm leading-relaxed text-blue-100">
                                    Every channel, ticket, and IT workflow lives together — with AI and SLAs built in.
                                </p>

                                <div class="mt-8 overflow-hidden rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm">
                                    <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                                        <span class="h-2 w-2 rounded-full bg-emerald-400" />
                                        <span class="text-[10px] font-medium text-white/70">{{ platformName }} workspace</span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2">
                                        <div class="rounded-lg bg-white/10 px-2 py-2 text-center">
                                            <p class="text-lg font-bold">12</p>
                                            <p class="text-[9px] text-white/60">Open</p>
                                        </div>
                                        <div class="rounded-lg bg-white/10 px-2 py-2 text-center">
                                            <p class="text-lg font-bold text-emerald-300">4m</p>
                                            <p class="text-[9px] text-white/60">Avg reply</p>
                                        </div>
                                        <div class="rounded-lg bg-white/10 px-2 py-2 text-center">
                                            <p class="text-lg font-bold">94%</p>
                                            <p class="text-[9px] text-white/60">CSAT</p>
                                        </div>
                                    </div>
                                </div>

                                <ul class="mt-6 space-y-3">
                                    <li
                                        v-for="item in painPoints"
                                        :key="item.gain"
                                        class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm"
                                    >
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-slate-900/15">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" /></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold leading-snug">{{ item.gain }}</p>
                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                <span
                                                    v-for="tool in item.oldTools"
                                                    :key="tool"
                                                    class="rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] text-white/60 line-through"
                                                >
                                                    {{ tool }}
                                                </span>
                                                <span class="rounded-md bg-emerald-400/20 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-200">→ unified</span>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div
                        v-for="item in stackSavings"
                        :key="item.label"
                        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-5 text-center shadow-sm"
                    >
                        <p class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ item.value }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ item.label }}</p>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        Ditch the stack — start free trial
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ trialDays }}-day trial · No credit card · Migrate in an afternoon</p>
                </div>
            </div>
        </section>

        <section id="ai" class="relative overflow-hidden border-b border-slate-200 dark:border-slate-800 bg-slate-950 py-16 sm:py-24 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full bg-violet-600/25 blur-3xl" />
                <div class="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff04_1px,transparent_1px),linear-gradient(to_bottom,#ffffff04_1px,transparent_1px)] bg-[size:3rem_3rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-start gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-violet-400/30 bg-violet-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-violet-300">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                            AI-powered
                        </p>
                        <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                            AI that works for agents
                            <span class="mt-1 block bg-gradient-to-r from-violet-400 via-fuchsia-300 to-indigo-400 bg-clip-text text-transparent">
                                and your customers
                            </span>
                        </h2>
                        <p class="mt-5 text-lg leading-relaxed text-slate-300">
                            {{ platformName }} ships AI where support actually happens — not as a bolt-on. Deflect tickets before they arrive, draft replies in seconds, and give every agent a Copilot with full ticket context.
                        </p>

                        <div class="mt-10 grid gap-4 sm:grid-cols-2">
                            <article
                                v-for="capability in aiCapabilities"
                                :key="capability.title"
                                class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur transition hover:border-violet-400/30 hover:bg-white/10"
                            >
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-60" :class="capability.accent" />
                                <div class="relative">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-600/30 text-violet-200 ring-1 ring-violet-400/20">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="capability.icon" /></svg>
                                    </div>
                                    <h3 class="mt-4 text-base font-semibold text-white">{{ capability.title }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-400 dark:text-slate-500">{{ capability.body }}</p>
                                </div>
                            </article>
                        </div>

                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href="/register"
                                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-xl shadow-violet-600/30 transition hover:from-violet-500 hover:to-indigo-500"
                            >
                                Start free trial
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Enterprise · AI included · No credit card</p>
                        </div>
                    </div>

                    <div class="lg:sticky lg:top-24 lg:self-start">
                        <CentralAiDemoWidget :enabled="aiDemoEnabled" />
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-10 sm:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $t('central.works_with_your_stack') }}</p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="group in integrationGroups"
                        :key="group.label"
                        class="rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80 p-5 transition hover:border-blue-200 dark:border-blue-900/60 hover:shadow-md"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ group.label }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 px-2 py-3 transition hover:border-blue-200 dark:border-blue-900/60 hover:shadow-sm"
                            >
                                <IntegrationStackIcon :id="item.id" />
                                <span class="text-center text-[10px] font-semibold leading-tight text-slate-600 dark:text-slate-400">{{ item.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="product" class="bg-slate-50 dark:bg-slate-950 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.platform_overview') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        Everything your team needs — nothing they don't
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        From the first customer message to resolution and feedback — {{ platformName }} gives agents the context, tools, and automation they need without switching tabs or paying for add-ons.
                    </p>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in bentoItems"
                        :key="item.title"
                        class="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                        :class="item.span"
                    >
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-60 transition group-hover:opacity-100" :class="item.accent" />
                        <div class="relative">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm transition group-hover:scale-105">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" /></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="differentiators" class="relative overflow-hidden bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50 via-white to-white" />
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.built_different_eyebrow') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl lg:text-5xl">
                        {{ $t('central.built_different_title') }}
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ builtDifferentSubtitle }}
                    </p>
                </div>

                <div class="mt-16 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in differentiators"
                        :key="item.title"
                        class="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700 hover:shadow-lg"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-70 transition group-hover:opacity-100"
                            :class="item.accent"
                        />
                        <div class="relative">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md transition group-hover:scale-105">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                    </svg>
                                </div>
                                <span
                                    v-if="item.badge"
                                    class="shrink-0 rounded-full bg-white dark:bg-slate-900/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                            <h3 class="mt-5 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                        </div>
                    </article>
                </div>

                <div class="mt-14 text-center">
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-blue-600/25 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        Try everything — start free trial
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ trialDays }}-day trial · No credit card · Cloud-hosted workspace</p>
                </div>
            </div>
        </section>

        <section id="features" class="border-y border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.deep_dive') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ $t('central.explore_by_capability') }}</h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        Pick a workflow area to see how {{ platformName }} handles it — from first contact through ITSM resolution.
                    </p>
                </div>

                <div class="mt-14 grid gap-8 lg:grid-cols-12 lg:gap-10">
                    <nav class="lg:col-span-4 xl:col-span-3" aria-label="Feature categories">
                        <div class="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0">
                            <button
                                v-for="cat in featureCategories"
                                :key="cat.id"
                                type="button"
                                class="group flex min-w-[11rem] shrink-0 items-center gap-3 rounded-2xl border px-3 py-3 text-left transition sm:min-w-[220px] sm:px-4 sm:py-3.5 lg:min-w-0 lg:w-full"
                                :class="featureCategory === cat.id
                                    ? 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-md ring-1 ring-slate-200 dark:ring-slate-700/80'
                                    : 'border-transparent bg-slate-50 dark:bg-slate-950/80 dark:bg-slate-900/60 hover:border-slate-200 dark:border-slate-800 dark:hover:border-slate-700 hover:bg-white dark:hover:bg-slate-800'"
                                @click="featureCategory = cat.id"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                                    :class="featureCategory === cat.id ? categoryThemes[cat.id].iconBg + ' text-white shadow-sm' : categoryThemes[cat.id].iconBgMuted"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="cat.icon" /></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ cat.label }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ categoryHints[cat.id] }}</span>
                                </span>
                                <svg
                                    class="hidden h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500 lg:block"
                                    :class="featureCategory === cat.id ? 'text-blue-600' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </nav>

                    <div class="lg:col-span-8 xl:col-span-9">
                        <div class="overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                            <div class="relative overflow-hidden bg-gradient-to-br px-8 py-10 text-white sm:px-10" :class="activeTheme.gradient">
                                <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
                                <div class="relative">
                                    <span v-if="featureCategory === 'itsm'" class="mb-3 inline-flex rounded-full bg-white dark:bg-slate-900/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/90 ring-1 ring-white/20">
                                        Enterprise add-on
                                    </span>
                                    <h3 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ activeCategory.title }}</h3>
                                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-white/85">{{ activeCategory.description }}</p>
                                </div>
                            </div>

                            <div class="p-6 sm:p-8">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <article
                                        v-for="item in primaryHighlightItems"
                                        :key="item.text"
                                        class="group flex items-start gap-3.5 rounded-2xl border border-slate-100 bg-white dark:bg-slate-900 p-4 transition hover:border-slate-200 dark:border-slate-800 hover:shadow-md"
                                    >
                                        <span
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition group-hover:scale-105"
                                            :class="activeTheme.iconBgMuted"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                            </svg>
                                        </span>
                                        <p class="pt-1.5 text-sm font-medium leading-snug text-slate-800 dark:text-slate-200">{{ item.text }}</p>
                                    </article>
                                </div>

                                <div v-if="secondaryHighlights.length" class="mt-5 border-t border-slate-100 dark:border-slate-800 pt-5">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Also included</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="item in secondaryHighlights"
                                            :key="item"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 dark:bg-slate-950 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700"
                                        >
                                            <svg class="h-3.5 w-3.5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            {{ item }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <article
                                v-for="feature in categoryRelatedFeatures"
                                :key="feature.title"
                                class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm"
                            >
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="feature.icon" /></svg>
                                </div>
                                <h4 class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ feature.title }}</h4>
                                <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ feature.description }}</p>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="relative mt-24 overflow-hidden rounded-[2rem] border border-slate-200 dark:border-slate-800/80 bg-gradient-to-b from-white via-slate-50/80 to-white p-6 shadow-xl shadow-slate-200/40 sm:p-10 lg:p-12">
                    <div class="pointer-events-none absolute -left-20 top-0 h-64 w-64 rounded-full bg-blue-400/10 blur-3xl" />
                    <div class="pointer-events-none absolute -right-20 bottom-0 h-64 w-64 rounded-full bg-violet-400/10 blur-3xl" />

                    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 dark:bg-blue-950/40 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300 ring-1 ring-blue-100">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Everything included
                            </span>
                            <h3 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">Full platform feature list</h3>
                            <p class="mt-3 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400">
                                All plans include core ticketing. Professional and Enterprise unlock automation, channels, AI, and ITSM capabilities.
                            </p>
                        </div>
                        <div class="mt-5 flex w-full flex-wrap justify-center gap-4 sm:mt-8 sm:w-auto sm:shrink-0 sm:gap-6 rounded-2xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 px-4 py-3 backdrop-blur-sm sm:px-6 sm:py-4">
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">{{ allFeatures.length }}</p>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Capabilities</p>
                            </div>
                            <div class="w-px bg-slate-200" />
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">4</p>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Workflow areas</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative mt-12 space-y-14">
                        <div v-for="group in featureGroups" :key="group.label">
                            <div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 dark:border-slate-800/80 pb-4">
                                <div>
                                    <h4 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ group.label }}</h4>
                                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ group.hint }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-3 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400">
                                    {{ group.features.length }} features
                                </span>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <article
                                    v-for="feature in group.features"
                                    :key="feature.title"
                                    class="group relative overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-800/80 transition duration-300 hover:-translate-y-1 hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700 hover:shadow-lg"
                                >
                                    <div
                                        class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-gradient-to-br opacity-0 blur-2xl transition duration-300 group-hover:opacity-50"
                                        :class="feature.palette.glow"
                                    />
                                    <div class="relative flex items-start gap-4">
                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-lg transition duration-300 group-hover:scale-110"
                                            :class="feature.palette.icon"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="feature.icon" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h5 class="font-semibold leading-snug text-slate-900 dark:text-slate-100">{{ feature.title }}</h5>
                                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ feature.description }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="compare" class="bg-slate-900 py-16 sm:py-24 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">{{ $t('central.why_teams_switch') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ platformName }} vs. pieced-together tools</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base text-slate-400 dark:text-slate-500">One workspace for support and IT — without stacking separate inbox, KB, and ITSM products.</p>
                </div>
                <div class="mt-12 space-y-3 lg:hidden">
                    <article
                        v-for="row in comparisons"
                        :key="row.feature"
                        class="rounded-2xl border border-white/10 bg-white/5 p-4"
                    >
                        <p class="text-sm font-medium text-slate-200">{{ row.feature }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-center text-xs">
                            <div class="rounded-xl bg-blue-500/10 px-3 py-2 ring-1 ring-blue-500/20">
                                <p class="font-semibold text-blue-300">{{ platformName }}</p>
                                <p class="mt-1 text-lg">
                                    <span v-if="row.us === true" class="text-emerald-400">✓</span>
                                    <span v-else class="text-slate-300">{{ row.us }}</span>
                                </p>
                            </div>
                            <div class="rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10">
                                <p class="font-semibold text-slate-400 dark:text-slate-500">{{ $t('central.typical_stack') }}</p>
                                <p class="mt-1 text-lg">
                                    <span v-if="row.them === false" class="text-slate-600 dark:text-slate-400">—</span>
                                    <span v-else-if="row.them === true" class="text-emerald-400">✓</span>
                                    <span v-else class="text-slate-500 dark:text-slate-400">{{ row.them }}</span>
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="mt-12 hidden overflow-hidden rounded-2xl border border-white/10 lg:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/5">
                                <th class="px-6 py-4 text-left font-medium text-slate-400 dark:text-slate-500">{{ $t('central.capability') }}</th>
                                <th class="px-6 py-4 text-center font-semibold text-blue-400">{{ platformName }}</th>
                                <th class="px-6 py-4 text-center font-medium text-slate-400 dark:text-slate-500">{{ $t('central.typical_stack') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in comparisons" :key="row.feature" class="border-b border-white/5 transition hover:bg-white/5">
                                <td class="px-6 py-4 text-slate-300">{{ row.feature }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="row.us === true" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">✓</span>
                                    <span v-else class="text-slate-400 dark:text-slate-500">{{ row.us }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-500 dark:text-slate-400">
                                    <span v-if="row.them === false" class="text-slate-600 dark:text-slate-400">—</span>
                                    <span v-else-if="row.them === true" class="text-emerald-400">✓</span>
                                    <span v-else>{{ row.them }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-14 text-center">
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        Get everything above — start free trial
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ trialDays }}-day trial · No credit card · Full access</p>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.how_it_works') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ $t('central.go_live_in_three_steps') }}</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 dark:text-slate-400">Most teams complete setup during their {{ trialDays }}-day trial — guided every step of the way.</p>
                </div>
                <div class="mt-16 grid gap-8 lg:grid-cols-3 lg:gap-6">
                    <article
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="relative rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-8 transition hover:border-blue-200 dark:border-blue-900/60 hover:shadow-lg"
                    >
                        <div
                            v-if="index < steps.length - 1"
                            class="pointer-events-none absolute left-[calc(50%+2rem)] top-14 hidden h-px w-[calc(100%-4rem)] bg-gradient-to-r from-blue-300 to-blue-100 lg:block"
                            aria-hidden="true"
                        />
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white shadow-lg shadow-blue-600/30">{{ index + 1 }}</div>
                        <h3 class="mt-6 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ step.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ step.body }}</p>
                        <p class="mt-4 rounded-lg bg-white dark:bg-slate-900 px-3 py-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700">{{ step.detail }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="pricing" class="relative overflow-hidden bg-slate-950 py-16 sm:py-24 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute left-1/2 top-0 h-96 w-96 -translate-x-1/2 rounded-full bg-blue-600/20 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">{{ $t('central.pricing') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                        Simple pricing. Serious value.
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400 dark:text-slate-500">
                        Start with a {{ trialDays }}-day free trial on any plan. Most teams save thousands by replacing multiple tools with one workspace.
                    </p>
                    <div class="mt-8 inline-flex rounded-xl border border-white/10 bg-white/5 p-1 backdrop-blur">
                        <button
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                            :class="billingInterval === 'month' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                            @click="billingInterval = 'month'"
                        >{{ $t('central.monthly') }}</button>
                        <button
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                            :class="billingInterval === 'year' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                            @click="billingInterval = 'year'"
                        >{{ $t('central.yearly') }}</button>
                    </div>
                    <p v-if="billingInterval === 'year'" class="mt-3 text-sm font-semibold text-emerald-400">{{ $t('central.save_up_to_2_months_with_annual_billing') }}</p>
                </div>
                <div class="mt-14 grid gap-8 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.slug"
                        class="relative flex flex-col rounded-3xl border p-6 sm:p-8 transition"
                        :class="plan.slug === 'professional'
                            ? 'border-blue-500/50 bg-gradient-to-b from-blue-600/20 to-slate-900/80 shadow-2xl shadow-blue-600/20 ring-2 ring-blue-500/40 lg:scale-105'
                            : 'border-white/10 bg-white/5 backdrop-blur hover:border-white/20'"
                    >
                        <span v-if="plan.slug === 'professional'" class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-1 text-xs font-bold text-white shadow-lg">{{ $t('central.most_popular') }}</span>
                        <h3 class="text-xl font-bold text-white">{{ plan.name }}</h3>
                        <p v-if="planTaglines[plan.slug]" class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ planTaglines[plan.slug] }}</p>
                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ formatPrice(planPrice(plan)) }}</span>
                            <span class="text-slate-400 dark:text-slate-500">{{ intervalSuffix }}</span>
                        </p>
                        <p v-if="billingInterval === 'year' && yearlySavingsPercent(plan) > 0" class="mt-2 text-sm font-semibold text-emerald-400">
                            Save {{ yearlySavingsPercent(plan) }}% vs monthly
                        </p>
                        <ul class="mt-8 flex-1 space-y-3">
                            <li v-for="item in planHighlights(plan)" :key="item" class="flex items-start gap-2.5 text-sm text-slate-300">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ item }}
                            </li>
                        </ul>
                        <Link
                            href="/register"
                            class="mt-8 block rounded-2xl py-3.5 text-center text-sm font-bold transition"
                            :class="plan.slug === 'professional'
                                ? 'bg-white text-slate-900 shadow-xl hover:bg-slate-100'
                                : 'border border-white/20 text-white hover:bg-white/10'"
                        >
                            Start {{ trialDays }}-day free trial
                        </Link>
                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">{{ $t('central.no_credit_card_required') }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="faq" class="bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.faq') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $t('central.common_questions') }}</h2>
                </div>
                <div class="mt-12 space-y-3">
                    <div v-for="(item, index) in faqs" :key="item.q" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 transition hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left sm:gap-4 sm:px-6 sm:py-5" @click="toggleFaq(index)">
                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">{{ item.q }}</span>
                            <svg class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500 transition" :class="openFaq === index ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-show="openFaq === index" class="border-t border-slate-200 dark:border-slate-800 px-4 pb-4 pt-2 sm:px-6 sm:pb-5">
                            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden bg-slate-950 py-20 sm:py-28">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-blue-900/50 via-slate-950 to-slate-950" />
                <div class="absolute left-1/2 top-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-600/10 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-white/10 to-white/5 p-6 text-center backdrop-blur-xl sm:p-10 lg:p-14">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-300">Limited-time offer</p>
                    <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                        Your customers are waiting.<br class="hidden sm:block" />
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">Give them answers today.</span>
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">
                        Join teams who replaced scattered inboxes and separate ITSM tools with one modern workspace. Setup takes minutes — not weeks.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href="/register"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-10 py-4 text-base font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500 sm:w-auto"
                        >
                            Start {{ trialDays }}-day free trial
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </Link>
                        <Link
                            href="/login"
                            class="inline-flex w-full items-center justify-center rounded-2xl border border-white/20 px-10 py-4 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto"
                        >
                            Sign in to workspace
                        </Link>
                    </div>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-slate-400 dark:text-slate-500">
                        <span v-for="badge in trustBadges" :key="badge" class="inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            {{ badge }}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </CentralLayout>
</template>
