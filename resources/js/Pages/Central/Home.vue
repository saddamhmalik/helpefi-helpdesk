<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import IntegrationStackIcon from '../../Components/IntegrationStackIcon.vue';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';
import { formatMarketingTemplate, useMarketingEnglish } from '../../composables/useMarketingEnglish.js';

const CentralAiDemoWidget = defineAsyncComponent(() => import('../../Components/CentralAiDemoWidget.vue'));
const CentralMarketingLeadCapture = defineAsyncComponent(() => import('../../Components/CentralMarketingLeadCapture.vue'));
const CentralHomeConversionModules = defineAsyncComponent(() => import('../../Components/Central/CentralHomeConversionModules.vue'));
const FaqAccordion = defineAsyncComponent(() => import('../../Components/Central/FaqAccordion.vue'));

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    homeContent: { type: Object, default: () => ({}) },
    marketingLabels: { type: Object, default: () => ({}) },
    marketingChrome: { type: Object, default: () => ({}) },
    plans: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    currency: { type: Object, default: () => ({ code: 'USD', symbol: '$', name: 'US Dollar' }) },
    baseCurrency: { type: Object, default: () => ({ code: 'USD', symbol: '$', name: 'US Dollar' }) },
    indiaCurrency: { type: Object, default: () => ({ code: 'INR', symbol: '₹', name: 'Indian Rupee' }) },
    indiaEnabled: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
    centralDomain: { type: String, default: '' },
    contactEmail: { type: String, default: '' },
    aiDemoEnabled: { type: Boolean, default: true },
    socialLinks: { type: Array, default: () => [] },
    testimonialsEnabled: { type: Boolean, default: true },
    testimonials: { type: Array, default: () => [] },
    comparePages: { type: Array, default: () => [] },
    featurePages: { type: Array, default: () => [] },
});

const platformName = computed(() => props.brand || 'helpefi');
const { label } = useMarketingEnglish(platformName, computed(() => props.marketingLabels));
const home = computed(() => props.homeContent ?? {});

const resolveLabelKey = (key) => {
    if (!key) {
        return '';
    }

    if (key.startsWith('central.')) {
        return label(key.slice('central.'.length));
    }

    if (key.startsWith('settings.groups.')) {
        return label(key.slice('settings.groups.'.length));
    }

    if (key.startsWith('settings.')) {
        return label(key.slice('settings.'.length));
    }

    if (key.startsWith('nav.')) {
        return label(key.slice('nav.'.length));
    }

    return label(key);
};

const homePath = (path) => path.split('.').reduce((value, key) => (value && typeof value === 'object' ? value[key] : undefined), home.value);

const homeArray = (suffix) => {
    const value = homePath(suffix);
    return Array.isArray(value) ? value : [];
};

const homeObject = (suffix) => {
    const value = homePath(suffix);
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
};

const socialProofLogos = computed(() => props.marketingChrome.social_proof_logos ?? []);

const workspaceDomainExample = computed(() => {
    const domain = props.centralDomain || 'helpefi.com';

    return `your-company.${domain}`;
});

const contactHref = computed(() => (props.contactEmail ? `mailto:${props.contactEmail}` : '/register'));

const pricedPlans = computed(() => props.plans.filter((plan) => !plan.custom_pricing));

const customPlans = computed(() => props.plans.filter((plan) => plan.custom_pricing));

const selectedCurrencyCode = ref(props.currency?.code ?? props.baseCurrency.code);

const activeCurrency = computed(() => (
    selectedCurrencyCode.value === props.indiaCurrency.code
        ? props.indiaCurrency
        : props.baseCurrency
));

const isIndia = computed(() => (
    props.indiaEnabled && selectedCurrencyCode.value === props.indiaCurrency.code
));

const setCurrency = (code) => {
    selectedCurrencyCode.value = code;
    document.cookie = `pricing_currency=${code};path=/;max-age=${60 * 60 * 24 * 365};samesite=lax`;
};

const { formatPrice } = useCurrency(() => activeCurrency.value);

const { billingInterval, intervalSuffix, planPrice, yearlySavingsPercent } = useBillingInterval();

const addonPrice = (addon) => (
    isIndia.value
        ? (addon.price_monthly_india ?? addon.price_monthly ?? 0)
        : (addon.price_monthly ?? 0)
);

const previewTab = ref('ai');
const featureCategory = ref('operations');
const faqs = computed(() => homeArray('faqs'));
const staticHeroActive = ref(false);

const featureLabels = computed(() => homeObject('feature_labels'));

const previewTabs = computed(() => [
    { id: 'ai', label: (homePath('preview_tab_ai') ?? '') },
    { id: 'inbox', label: label('shared_inbox') },
    { id: 'chat', label: label('live_chat') },
    { id: 'servicedesk', label: label('service_desk') },
    { id: 'analytics', label: label('analytics') },
]);

const aiCapabilityMeta = [
    {
        icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        accent: 'from-violet-500/20 to-purple-500/10',
        iconWrap: 'bg-violet-500/15 text-violet-200 ring-violet-400/25',
    },
    {
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        accent: 'from-indigo-500/20 to-blue-500/10',
        iconWrap: 'bg-indigo-500/15 text-indigo-200 ring-indigo-400/25',
    },
    {
        icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        accent: 'from-fuchsia-500/20 to-pink-500/10',
        iconWrap: 'bg-fuchsia-500/15 text-fuchsia-200 ring-fuchsia-400/25',
    },
    {
        icon: 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
        accent: 'from-cyan-500/20 to-sky-500/10',
        iconWrap: 'bg-cyan-500/15 text-cyan-200 ring-cyan-400/25',
    },
];

const aiCapabilities = computed(() => homeArray('ai_capabilities').map((item, index) => ({
    ...item,
    ...(aiCapabilityMeta[index] ?? aiCapabilityMeta[0]),
})));

const aiStats = computed(() => homeArray('ai_stats'));

const aiHighlights = computed(() => homeArray('ai_highlights'));

const aiSectionSubtitle = computed(() => (homePath('ai_section.subtitle') ?? ''));

const featureCategoryDefs = [
    { id: 'operations', labelKey: 'central.ticket_operations', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { id: 'channels', labelKey: 'settings.groups.channels', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { id: 'selfservice', labelKey: 'central.self-service', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { id: 'automation', labelKey: 'central.automation_ai', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { id: 'itsm', labelKey: 'central.service_desk_itsm', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { id: 'platform', labelKey: 'central.platform', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
];

const featureCategories = computed(() => featureCategoryDefs.map((category) => ({
    ...category,
    label: resolveLabelKey(category.labelKey),
})));

const categoryMeta = {
    operations: {
        titleKey: 'central.run_support_like_a_well-oiled_machine',
        descriptionKey: 'central.every_conversation_becomes_a_trackable_ticket_with_ownership_priority_',
    },
    channels: {
        titleKey: 'central.meet_customers_where_they_are',
        descriptionKey: 'central.email_live_chat_sms_and_your_branded_portal_all_feed_the_same_queue_mu',
    },
    selfservice: {
        titleKey: 'central.deflect_tickets_before_they_arrive',
        descriptionKey: 'central.publish_a_searchable_knowledge_base_route_structured_requests_through_',
    },
    automation: {
        titleKey: 'central.automate_the_repetitive_work',
        descriptionKey: 'central.route_tickets_automatically_apply_macros_chain_multi-step_workflows_an',
    },
    itsm: {
        titleKey: 'central.full_itsm_on_top_of_your_helpdesk',
        descriptionKey: 'central.enterprise_teams_get_itil-style_workflows_type_queues_approvals_change',
    },
    platform: {
        titleKey: 'central.built_for_teams_that_scale',
        descriptionKey: 'central.role-based_access_crm_context_enterprise_integrations_sso_and_billing_',
    },
};

const categoryContent = computed(() => {
    const highlightsByCategory = homeObject('category_highlights');

    return Object.fromEntries(Object.entries(categoryMeta).map(([id, meta]) => {
        const highlights = highlightsByCategory[id];

        return [id, {
            title: resolveLabelKey(meta.titleKey),
            description: resolveLabelKey(meta.descriptionKey),
            highlights: Array.isArray(highlights) ? highlights : [],
        }];
    }));
});

const allFeatures = computed(() => [
    { title: label('shared_inbox_tickets'), description: label('manage_email_chat_sms_and_portal_requests_in_one_workspace_with_merge_'), icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { title: label('agent_workspace'), description: label('split-pane_queue_conversation_and_details_sidebar_with_real-time_updat'), icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z' },
    { title: label('live_chat_sms'), description: label('embed_a_chat_widget_on_your_site_and_receive_sms_via_twilio_every_conv'), icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { title: label('knowledge_base_portal'), description: label('publish_help_articles_with_semantic_search_locale_support_and_a_brande'), icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { title: label('service_catalog'), description: label('structured_request_types_on_your_portal_with_per-item_approval_workflo'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    { title: label('service_desk_itsm'), description: label('itil_type_queues_change_calendar_problem_linking_catalog_approvals_and'), icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: label('sla_business_hours'), description: label('set_response_targets_escalation_rules_team_slas_and_operating_hours_wi'), icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { title: label('automation_macros'), description: label('multi-step_automation_chains_canned_responses_auto-assignment_webhooks'), icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { title: label('ai_assist_deflection'), description: label('draft_replies_summarize_threads_surface_kb_articles_for_agents_and_def'), icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { title: label('csat_reporting'), description: label('measure_satisfaction_on_portal_and_email_build_saved_reports_and_sched'), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { title: label('multi-brand_workspaces'), description: label('run_multiple_brands_with_separate_portals_inboxes_kb_skins_and_routing'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { title: label('integrations_crm'), description: label('connect_slack_jira_linear_hubspot_salesforce_shopify_teams_and_custom_'), icon: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z' },
    { title: label('asset_management'), description: label('track_hardware_and_software_assets_link_them_to_contacts_and_tickets_a'), icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
    { title: label('contacts_organizations'), description: label('track_customers_companies_vip_tags_activity_timelines_and_crm_context_'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { title: label('security_sso'), description: label('two-factor_authentication_saml_oidc_single_sign-on_role_permissions_au'), icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { title: label('workforce_management'), description: label('organize_agents_into_teams_and_departments_with_skills_routing_perform'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
]);

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
    { indices: [0, 1, 13, 15] },
    { indices: [2, 3, 4, 8] },
    { indices: [6, 7, 9, 10] },
    { indices: [5, 11, 12, 14] },
];

const bentoDefs = [
    { titleKey: 'central.one_inbox_every_channel', bodyKey: 'one_inbox', span: 'lg:col-span-2', accent: 'from-blue-600/20 to-indigo-600/10', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { titleKey: 'central.sla_timers', bodyKey: 'sla', span: '', accent: 'from-amber-500/20 to-orange-500/10', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { titleKey: 'central.ai_reply_drafts', bodyKey: 'ai', span: '', accent: 'from-violet-600/20 to-purple-600/10', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { titleKey: 'central.customer_portal', bodyKey: 'portal', span: 'lg:col-span-2', accent: 'from-emerald-600/20 to-teal-600/10', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { titleKey: 'central.service_desk_itsm', bodyKey: null, span: 'lg:col-span-2', accent: 'from-red-500/20 to-rose-500/10', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { titleKey: 'central.real-time_workspace', bodyKey: 'realtime', span: '', accent: 'from-cyan-500/20 to-sky-500/10', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
];

const bentoItems = computed(() => bentoDefs.map((item) => ({
    title: resolveLabelKey(item.titleKey),
    body: item.bodyKey
        ? (homePath(`bento_bodies.${item.bodyKey}`) ?? '')
        : resolveLabelKey('service_desk_itsm_card_body'),
    span: item.span,
    accent: item.accent,
    icon: item.icon,
})));

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

const differentiators = computed(() => differentiatorDefs.map((item, index) => ({
    title: label(item.titleKey),
    body: label(item.bodyKey),
    badge: item.badgeKey ? label(item.badgeKey) : null,
    accent: item.accent,
    icon: item.icon,
    featured: index === differentiatorDefs.length - 1,
})));

const builtDifferentSubtitle = computed(() => label('built_different_subtitle'));
const socialProofSubtitle = computed(() => label('social_proof_subtitle'));
const compareLinkLabel = (page) => `${platformName.value} vs ${page.nav_label}`;

const painPointMeta = [
    { icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z' },
    { icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const painPoints = computed(() => homeArray('pain_points').map((item, index) => ({
    ...item,
    icon: painPointMeta[index]?.icon ?? painPointMeta[0].icon,
})));

const stackSavings = computed(() => homeArray('stack_savings'));

const switchOldTools = computed(() => homeArray('switch_section.old_tools'));

const switchMockNav = [
    { icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', active: true },
    { icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z' },
    { icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
];

const comparisons = computed(() => homeArray('comparisons'));

const planTaglines = computed(() => homeObject('plan_taglines'));

const integrationGroups = computed(() => homeArray('integration_groups'));

const categoryHints = computed(() => homeObject('category_hints'));

const switchSectionSubtitle = computed(() => (homePath('switch_section.subtitle') ?? ''));

const compareSectionTitle = computed(() => (homePath('compare_section.title') ?? ''));

const productSectionSubtitle = computed(() => (homePath('product_section.subtitle') ?? ''));

const featuresSectionSubtitle = computed(() => (homePath('features_section.subtitle') ?? ''));

const steps = computed(() => homeArray('steps'));

const heroAvatars = [
    { initials: 'SC', color: 'from-blue-500 to-indigo-600' },
    { initials: 'MW', color: 'from-emerald-500 to-teal-600' },
    { initials: 'ER', color: 'from-violet-500 to-purple-600' },
];

const trustBadges = computed(() => homeArray('trust_badges'));

const heroAiPillIcons = [
    'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
    'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
    'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
];

const heroAiPills = computed(() => homeArray('hero_ai_pills').map((item, index) => ({
    label: item.label,
    icon: heroAiPillIcons[index] ?? heroAiPillIcons[0],
})));

const heroAiStats = computed(() => homeArray('hero_ai_stats'));

const outcomeStats = computed(() => homeArray('outcome_stats'));

const heroMobileAiPoints = computed(() => homeArray('hero_mobile_ai_points'));

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

const activeCategory = computed(() => categoryContent.value[featureCategory.value]);
const categoryThemes = {
    operations: { gradient: 'from-blue-600 via-blue-700 to-indigo-700', iconBg: 'bg-blue-600', iconBgMuted: 'bg-blue-100 text-blue-700 dark:text-blue-300' },
    channels: { gradient: 'from-emerald-600 via-teal-600 to-cyan-700', iconBg: 'bg-emerald-600', iconBgMuted: 'bg-emerald-100 text-emerald-700 dark:text-emerald-300' },
    selfservice: { gradient: 'from-violet-600 via-purple-600 to-indigo-700', iconBg: 'bg-violet-600', iconBgMuted: 'bg-violet-100 text-violet-700 dark:text-violet-300' },
    automation: { gradient: 'from-amber-500 via-orange-500 to-red-600', iconBg: 'bg-amber-500', iconBgMuted: 'bg-amber-100 text-amber-800' },
    itsm: { gradient: 'from-red-600 via-rose-600 to-orange-700', iconBg: 'bg-red-600', iconBgMuted: 'bg-red-100 text-red-700 dark:text-red-300' },
    platform: { gradient: 'from-slate-700 via-slate-800 to-slate-900', iconBg: 'bg-slate-800', iconBgMuted: 'bg-slate-200 text-slate-700 dark:text-slate-300' },
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
    return indices.map((index) => allFeatures.value[index]).filter(Boolean);
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



const formatLimit = (value) => (
    value === null || value === 'unlimited'
        ? (homePath('plan_limits.unlimited') ?? '')
        : value
);

const planHighlights = (plan) => {
    const labels = featureLabels.value;
    const agents = formatLimit(plan.limits?.agents);
    const tickets = formatLimit(plan.limits?.tickets_monthly);
    const items = [
        formatMarketingTemplate(homePath('plan_limits.team_members') ?? '', { count: agents }),
        formatMarketingTemplate(homePath('plan_limits.tickets_per_month') ?? '', { count: tickets }),
    ];

    (plan.features ?? []).forEach((key) => {
        if (labels[key]) {
            items.push(labels[key]);
        }
    });

    return items;
};

onMounted(() => {
    const shell = document.getElementById('marketing-first-paint');
    const main = document.querySelector('#app main#main-content');
    const heroMain = shell?.querySelector('[data-static-hero-root]');

    if (! shell || ! main || ! heroMain) {
        if (shell) {
            document.body.classList.remove('marketing-fp-pending');
            shell.remove();
        }

        return;
    }

    staticHeroActive.value = true;
    main.insertBefore(heroMain, main.firstChild);
    shell.remove();
    document.body.classList.remove('marketing-fp-pending');
    document.body.classList.add('marketing-fp-ready');
});

const featureGroups = computed(() => {
    const groups = homeArray('feature_groups');

    return featureGroupDefs.map((group, index) => ({
        label: groups[index]?.label ?? '',
        hint: groups[index]?.hint ?? '',
        features: group.indices.map((featureIndex) => ({
            ...allFeatures.value[featureIndex],
            palette: featurePalette[featureIndex % featurePalette.length],
        })).filter((feature) => feature.title),
    }));
});
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section v-if="!staticHeroActive" class="relative min-h-[32rem] overflow-hidden bg-slate-950 text-white sm:min-h-[36rem] lg:min-h-[40rem]">
            <div class="pointer-events-none absolute inset-0 overflow-hidden [contain:strict]" aria-hidden="true">
                <div class="absolute -left-40 top-0 h-[36rem] w-[36rem] rounded-full bg-blue-600/30 blur-3xl will-change-transform" />
                <div class="absolute right-[-10%] top-10 h-[28rem] w-[28rem] rounded-full bg-indigo-500/25 blur-3xl will-change-transform" />
                <div class="absolute bottom-[-10%] left-1/3 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl will-change-transform" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff06_1px,transparent_1px),linear-gradient(to_bottom,#ffffff06_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pb-20 sm:pt-14 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="min-w-0 max-w-xl lg:max-w-none">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-semibold text-emerald-300 backdrop-blur sm:px-4 sm:text-xs">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                                </span>
                                {{ home.hero_trial_badge }}
                            </div>
                            <a
                                href="#ai"
                                class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/40 bg-gradient-to-r from-violet-600/25 to-fuchsia-600/20 px-3 py-1.5 text-[11px] font-bold text-violet-200 shadow-lg shadow-violet-900/20 backdrop-blur transition hover:border-violet-300/50 hover:text-white sm:px-4 sm:text-xs"
                            >
                                <svg class="h-3.5 w-3.5 text-violet-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                {{ home.hero_ai_badge }}
                            </a>
                        </div>

                        <h1 class="mt-6 text-3xl font-extrabold leading-[1.08] tracking-tight sm:mt-8 sm:text-[2.75rem] sm:leading-[1.05] lg:text-5xl xl:text-[3.5rem]">
                            {{ home.hero_title_line1 }}
                            <span class="mt-1 block bg-gradient-to-r from-blue-400 via-indigo-300 to-violet-400 bg-clip-text text-transparent">
                                {{ home.hero_title_line2 }}
                            </span>
                        </h1>

                        <p class="mt-5 text-base leading-relaxed text-slate-300 sm:mt-6 sm:text-lg lg:text-xl">
                            {{ home.hero_subtitle }}
                        </p>

                        <div class="mt-6 rounded-2xl border border-violet-500/25 bg-gradient-to-br from-violet-950/50 via-slate-900/80 to-indigo-950/50 p-4 ring-1 ring-violet-400/10 sm:mt-7 sm:p-5">
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <p class="text-xs font-bold uppercase tracking-wider text-violet-300">{{ home.hero_ai_heading }}</p>
                                <a href="#ai" class="shrink-0 text-[11px] font-semibold text-violet-300 underline-offset-2 hover:text-white hover:underline sm:text-xs">{{ home.hero_ai_link }}</a>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="pill in heroAiPills"
                                    :key="pill.label"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] font-medium text-slate-200 sm:text-xs"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="pill.icon" /></svg>
                                    {{ pill.label }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-1.5 border-t border-white/10 pt-4 sm:gap-3">
                                <div v-for="stat in heroAiStats" :key="stat.label" class="min-w-0 px-0.5 text-center sm:px-0 sm:text-start">
                                    <p class="text-sm font-extrabold text-white sm:text-lg" dir="ltr">{{ stat.value }}</p>
                                    <p class="text-[10px] font-medium leading-tight text-violet-200 sm:text-xs">{{ stat.label }}</p>
                                    <p class="hidden text-[10px] text-slate-500 dark:text-slate-400 sm:block">{{ stat.detail }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-7 flex flex-col gap-3 sm:mt-9 sm:flex-row sm:items-center">
                            <Link
                                href="/register"
                                class="group relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500 hover:shadow-blue-500/50 sm:px-8 sm:py-4 sm:text-base"
                            >
                                <span class="sm:hidden">{{ home.hero_cta_short }}</span>
                                <span class="hidden sm:inline">{{ home.hero_cta_long }}</span>
                                <svg class="h-5 w-5 transition group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <a
                                href="#ai"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-6 py-4 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/10"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ home.hero_try_ai }}
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2">
                            <span v-for="badge in trustBadges" :key="badge" class="inline-flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500">
                                <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                {{ badge }}
                            </span>
                        </div>

                        <div class="mt-8 flex min-w-0 flex-col gap-4 border-t border-white/10 pt-6 sm:mt-10 sm:flex-row sm:items-center sm:pt-8">
                            <div class="flex shrink-0 -space-x-2.5">
                                <span
                                    v-for="t in heroAvatars"
                                    :key="t.initials"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-950 bg-gradient-to-br text-xs font-bold text-white"
                                    :class="t.color"
                                >
                                    {{ t.initials }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-0.5">
                                    <svg v-for="n in 5" :key="n" class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                </div>
                                <p class="mt-0.5 text-sm leading-snug text-slate-400 dark:text-slate-500">
                                    <span class="font-semibold text-white">{{ home.hero_trusted_emphasis }}</span>
                                    {{ ' ' }}{{ home.hero_trusted_suffix }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto w-full max-w-xl md:max-w-none lg:ps-4">
                        <div class="md:hidden rounded-2xl border border-violet-500/25 bg-gradient-to-br from-violet-950/40 to-slate-900/80 p-5 shadow-xl backdrop-blur-xl ring-1 ring-violet-400/15">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/20 text-violet-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ home.hero_mobile_ai_title }}</p>
                                    <p class="text-xs text-violet-200">{{ home.hero_mobile_ai_body }}</p>
                                </div>
                            </div>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-300">
                                <li v-for="item in heroMobileAiPoints" :key="item" class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    {{ item }}
                                </li>
                            </ul>
                            <a href="#ai" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-violet-300 hover:text-white">
                                {{ home.hero_try_ai }}
                                <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </a>
                        </div>

                        <div class="relative mt-6 hidden md:block lg:mt-0">
                            <div class="pointer-events-none absolute -end-2 -top-3 z-10 flex items-center gap-1.5 rounded-full border border-violet-400/40 bg-violet-600/90 px-3 py-1.5 text-[11px] font-bold text-white shadow-lg shadow-violet-900/40 backdrop-blur">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white dark:bg-slate-900 opacity-75" />
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white dark:bg-slate-900" />
                                </span>
                                {{ home.hero_mockup_badge }}
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
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ marketingLabels.open_tickets_12 }}</p>
                                        <div class="mt-3 space-y-2">
                                            <div class="rounded-lg bg-blue-500/20 px-3 py-2 ring-1 ring-blue-500/40">
                                                <p class="text-xs font-medium text-white">{{ marketingLabels.payment_failed_need_help }}</p>
                                                <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.sarah_sla_18m_assigned_to_you }}</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">{{ marketingLabels.chat_shipping_question }}</p>
                                                <p class="mt-0.5 text-[10px] text-emerald-400">{{ marketingLabels.live_waiting }}</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">{{ marketingLabels.api_rate_limit_error }}</p>
                                                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.dev_team_14m_ago }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-3 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium">{{ marketingLabels.payment_failed_need_help }}</p>
                                            <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-medium text-amber-300">{{ marketingLabels.high_sla_18m }}</span>
                                        </div>
                                        <div class="mt-4 space-y-3">
                                            <div class="rounded-lg bg-white/5 p-3"><p class="text-xs text-slate-300">{{ marketingLabels.hi_my_subscription_payment_failed_but_i_was_still_charged }}</p></div>
                                            <div class="rounded-lg bg-blue-600/25 p-3 ring-1 ring-blue-500/30"><p class="text-xs text-blue-100">{{ marketingLabels.i_can_see_the_duplicate_charge_refunding_now_and_extending_your_plan_b }}</p></div>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="rounded-md bg-violet-500/20 px-2 py-1 text-[10px] text-violet-200">{{ marketingLabels.ai_draft_ready }}</span>
                                            <span class="rounded-md bg-white/5 px-2 py-1 text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.billing }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="previewTab === 'ai'" class="grid grid-cols-5">
                                    <div class="col-span-2 border-r border-white/10 bg-slate-950/90 p-4">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ home.mockup_ai_ticket_id }}</p>
                                        <p class="mt-2 text-xs font-medium text-white">{{ marketingLabels.payment_failed_need_help }}</p>
                                        <div class="mt-4 space-y-2">
                                            <div class="rounded-lg bg-white/5 px-3 py-2"><p class="text-[10px] text-slate-300">{{ home.mockup_ai_customer_msg }}</p></div>
                                            <div class="rounded-lg bg-blue-600/20 px-3 py-2 ring-1 ring-blue-500/30"><p class="text-[10px] text-blue-100">{{ home.mockup_ai_agent_msg }}</p></div>
                                        </div>
                                        <span class="mt-4 inline-flex rounded-md bg-violet-500/20 px-2 py-1 text-[10px] text-violet-200">{{ marketingLabels.ai_draft_ready }}</span>
                                    </div>
                                    <div class="col-span-3 flex flex-col bg-gradient-to-b from-violet-950/40 to-slate-950/90 p-4">
                                        <div class="flex items-center justify-between border-b border-violet-500/20 pb-3">
                                            <p class="text-xs font-semibold text-violet-200">{{ home.mockup_ai_copilot_title }}</p>
                                            <span class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] text-violet-300">{{ home.mockup_ai_live }}</span>
                                        </div>
                                        <div class="mt-3 flex-1 space-y-2">
                                            <div class="ml-auto max-w-[90%] rounded-xl rounded-br-sm bg-violet-600/50 px-3 py-2"><p class="text-[10px] text-violet-50">{{ home.mockup_ai_user_prompt }}</p></div>
                                            <div class="max-w-[95%] rounded-xl rounded-bl-sm border border-violet-500/20 bg-white/5 px-3 py-2"><p class="text-[10px] leading-relaxed text-slate-200">{{ home.mockup_ai_copilot_reply }}</p></div>
                                        </div>
                                        <p class="mt-3 text-[9px] text-violet-300/70">{{ home.mockup_ai_kb_matched }}</p>
                                    </div>
                                </div>

                                <div v-else-if="previewTab === 'chat'" class="p-4">
                                    <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-xs text-emerald-300">V</span>
                                        <div>
                                            <p class="text-sm font-medium">{{ marketingLabels.visitor_on_pricing }}</p>
                                            <p class="text-[10px] text-emerald-400">{{ marketingLabels.online_san_francisco }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">{{ home.mockup_chat_visitor_question }}</p></div>
                                        <div class="ml-auto max-w-[80%] rounded-2xl rounded-br-md bg-blue-600/40 px-3 py-2"><p class="text-xs text-blue-50">{{ marketingLabels.yes_annual_plans_save_20_i_can_send_details_to_your_email }}</p></div>
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">{{ marketingLabels.perfect_please_do }}</p></div>
                                    </div>
                                    <p class="mt-4 text-center text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.conversation_saved_as_ticket_1042 }}</p>
                                </div>

                                <div v-else-if="previewTab === 'servicedesk'" class="p-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-semibold text-red-300">{{ marketingLabels.major_incident_active }}</p>
                                        <span class="rounded-full bg-red-500/20 px-2 py-0.5 text-[10px] font-medium text-red-200">{{ marketingLabels.war_room }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="rounded-lg bg-red-500/10 p-2 ring-1 ring-red-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.incidents }}</p>
                                            <p class="text-lg font-bold text-white">8</p>
                                            <p class="text-[10px] text-red-300">{{ label('2_major') }}</p>
                                        </div>
                                        <div class="rounded-lg bg-violet-500/10 p-2 ring-1 ring-violet-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.changes }}</p>
                                            <p class="text-lg font-bold text-white">3</p>
                                            <p class="text-[10px] text-violet-300">{{ label('1_pending_approval') }}</p>
                                        </div>
                                        <div class="rounded-lg bg-amber-500/10 p-2 ring-1 ring-amber-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.problems }}</p>
                                            <p class="text-lg font-bold text-white">2</p>
                                        </div>
                                        <div class="rounded-lg bg-blue-500/10 p-2 ring-1 ring-blue-500/20">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.approvals }}</p>
                                            <p class="text-lg font-bold text-white">4</p>
                                            <p class="text-[10px] text-blue-300">{{ marketingLabels.awaiting_you }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-2">
                                        <p class="text-xs font-medium text-red-100">{{ label('hd-93001_email_outage') }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">{{ marketingLabels.coordinators_3_declared_12m_ago }}</p>
                                    </div>
                                </div>

                                <div v-else class="p-4">
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.first_response }}</p><p class="mt-1 text-lg font-bold text-emerald-400">{{ label('4_2m') }}</p><p class="text-[10px] text-emerald-400/80">{{ label('18_vs_last_week') }}</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.csat_score }}</p><p class="mt-1 text-lg font-bold text-white">94%</p><p class="text-[10px] text-slate-400 dark:text-slate-500">{{ label('128_responses') }}</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.resolved_today }}</p><p class="mt-1 text-lg font-bold text-white">47</p><p class="text-[10px] text-slate-400 dark:text-slate-500">{{ label('6_open') }}</p></div>
                                    </div>
                                    <div class="mt-4 h-24 rounded-lg bg-gradient-to-t from-blue-600/20 to-transparent p-3">
                                        <div class="flex h-full items-end gap-1">
                                            <div v-for="(h, i) in [40, 55, 35, 70, 50, 85, 60, 75, 90, 65]" :key="i" class="flex-1 rounded-t bg-blue-500/60" :style="{ height: `${h}%` }" />
                                        </div>
                                    </div>
                                    <p class="mt-2 text-center text-[10px] text-slate-500 dark:text-slate-400">{{ marketingLabels.ticket_volume_last_10_days }}</p>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 lg:mt-14">
                    <CentralHomeConversionModules :base-domain="centralDomain || 'helpefi.com'" :plans="plans" />
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
                        <p class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl" dir="ltr">{{ stat.value }}</p>
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
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ home.switch_section.eyebrow }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl lg:text-5xl">
                        {{ home.switch_section.title }}
                    </h2>
                    <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                        {{ switchSectionSubtitle }}
                    </p>
                </div>

                <div class="relative mt-14 lg:mt-16">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none lg:grid lg:min-h-[22rem] lg:grid-cols-2">
                        <div class="relative flex flex-col border-b border-slate-200 bg-gradient-to-b from-slate-50 to-white p-8 sm:p-10 lg:border-b-0 lg:border-e dark:border-slate-800 dark:from-slate-950 dark:to-slate-900">
                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-red-600 ring-1 ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500" />
                                {{ home.switch_section.old_way_badge }}
                            </span>
                            <h3 class="mt-5 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-[1.65rem]">
                                {{ home.switch_section.old_way_title }}
                            </h3>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                {{ home.switch_section.old_way_body }}
                            </p>

                            <div class="mt-auto rounded-2xl border border-dashed border-red-200/80 bg-white p-5 dark:border-red-900/40 dark:bg-slate-950/60">
                                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                                    <span
                                        v-for="tool in switchOldTools"
                                        :key="tool"
                                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-center text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        {{ tool }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="relative flex flex-col overflow-hidden bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 p-8 text-white sm:p-10">
                            <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
                            <div class="pointer-events-none absolute -bottom-12 -left-12 h-44 w-44 rounded-full bg-fuchsia-400/20 blur-3xl" />
                            <div class="relative">
                                <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white ring-1 ring-white/25">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                    </span>
                                    {{ home.switch_section.with_brand_badge }}
                                </span>
                                <h3 class="mt-5 text-2xl font-bold tracking-tight sm:text-[1.65rem]">
                                    {{ home.switch_section.new_way_title }}
                                </h3>
                                <p class="mt-3 max-w-md text-sm leading-relaxed text-blue-100">
                                    {{ home.switch_section.new_way_body }}
                                </p>

                                <div class="mt-auto overflow-hidden rounded-2xl border border-white/20 bg-slate-950/50 shadow-2xl shadow-indigo-950/30 backdrop-blur-md">
                                    <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
                                        <span class="flex gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-full bg-red-400/70" />
                                            <span class="h-2.5 w-2.5 rounded-full bg-amber-400/70" />
                                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70" />
                                        </span>
                                        <span class="mx-auto truncate text-[10px] font-medium text-white/55">{{ home.switch_section.helpdesk_label }}</span>
                                    </div>
                                    <div class="grid grid-cols-[2.75rem_1fr]">
                                        <div class="flex flex-col gap-1.5 border-e border-white/10 bg-[#111827]/80 p-2">
                                            <span
                                                v-for="(nav, navIndex) in switchMockNav"
                                                :key="navIndex"
                                                class="flex h-7 w-full items-center justify-center rounded-md transition"
                                                :class="nav.active ? 'bg-white/15 text-white ring-1 ring-white/25' : 'text-white/35'"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="nav.icon" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="p-3.5">
                                            <div class="grid grid-cols-3 gap-1.5">
                                                <div class="rounded-lg bg-white/10 px-1.5 py-2.5 text-center ring-1 ring-white/10">
                                                    <p class="text-lg font-bold leading-none">12</p>
                                                    <p class="mt-1 text-[8px] font-semibold uppercase tracking-wide text-white/50">{{ home.switch_section.stat_open }}</p>
                                                </div>
                                                <div class="rounded-lg bg-emerald-500/25 px-1.5 py-2.5 text-center ring-1 ring-emerald-400/35">
                                                    <p class="text-lg font-bold leading-none text-emerald-200">4m</p>
                                                    <p class="mt-1 text-[8px] font-semibold uppercase tracking-wide text-emerald-100/65">{{ home.switch_section.stat_avg_reply }}</p>
                                                </div>
                                                <div class="rounded-lg bg-white/10 px-1.5 py-2.5 text-center ring-1 ring-white/10">
                                                    <p class="text-lg font-bold leading-none">94%</p>
                                                    <p class="mt-1 text-[8px] font-semibold uppercase tracking-wide text-white/50">{{ home.switch_section.stat_csat }}</p>
                                                </div>
                                            </div>
                                            <div class="mt-3 space-y-1.5">
                                                <div class="flex items-center gap-2 rounded-md bg-white/5 px-2 py-1.5 ring-1 ring-white/5">
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400" />
                                                    <span class="h-1.5 flex-1 rounded-full bg-white/15" />
                                                </div>
                                                <div class="flex items-center gap-2 rounded-md bg-white/5 px-2 py-1.5 ring-1 ring-white/5">
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                                                    <span class="h-1.5 w-4/5 rounded-full bg-white/15" />
                                                </div>
                                                <div class="flex items-center gap-2 rounded-md bg-white/10 px-2 py-1.5 ring-1 ring-white/15">
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" />
                                                    <span class="h-1.5 w-3/5 rounded-full bg-gradient-to-r from-blue-400/70 to-violet-400/70" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="hidden border-b border-slate-100 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 sm:grid sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:gap-6 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                            <span>{{ home.switch_section.comparison_before }}</span>
                            <span class="w-9" />
                            <span>{{ home.switch_section.comparison_after }}</span>
                        </div>
                        <div
                            v-for="(item, index) in painPoints"
                            :key="item.pain"
                            class="flex flex-col gap-3 px-5 py-4 sm:grid sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center sm:gap-6 sm:px-6 sm:py-5"
                            :class="[
                                index > 0 ? 'border-t border-slate-100 dark:border-slate-800' : '',
                                index % 2 === 1 ? 'bg-slate-50/70 dark:bg-slate-950/30' : '',
                            ]"
                        >
                            <div class="flex items-start gap-3 sm:items-center">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-500 ring-1 ring-red-100 dark:bg-red-950/50 dark:ring-red-900/40 sm:mt-0">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                </span>
                                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.pain }}</p>
                            </div>
                            <div class="flex justify-center sm:justify-center">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-100 dark:bg-blue-950/50 dark:text-blue-300 dark:ring-blue-900/50 sm:h-9 sm:w-9">
                                    <svg class="h-3.5 w-3.5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                                    <svg class="hidden h-4 w-4 sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                </span>
                            </div>
                            <div class="flex items-start gap-3 sm:items-center">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-600/20 sm:mt-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" /></svg>
                                </span>
                                <p class="text-sm font-semibold leading-relaxed text-slate-900 dark:text-slate-100">{{ item.gain }}</p>
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
                        {{ home.switch_section.cta }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ home.switch_section.cta_footnote }}</p>
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
                            {{ home.ai_section.badge }}
                        </p>
                        <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                            {{ home.ai_section.title_line1 }}
                            <span class="mt-1 block bg-gradient-to-r from-violet-400 via-fuchsia-300 to-indigo-400 bg-clip-text text-transparent">
                                {{ home.ai_section.title_line2 }}
                            </span>
                        </h2>
                        <p class="mt-5 text-lg leading-relaxed text-slate-300">
                            {{ aiSectionSubtitle }}
                        </p>

                        <div class="mt-10 grid gap-4 sm:grid-cols-2">
                            <article
                                v-for="capability in aiCapabilities"
                                :key="capability.title"
                                class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur transition duration-300 hover:-translate-y-0.5 hover:border-violet-400/40 hover:bg-white/10 hover:shadow-xl hover:shadow-violet-950/40"
                            >
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-60 transition group-hover:opacity-100" :class="capability.accent" />
                                <div class="relative">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl ring-1 transition group-hover:scale-105" :class="capability.iconWrap">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="capability.icon" /></svg>
                                    </div>
                                    <h3 class="mt-4 text-base font-semibold text-white">{{ capability.title }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ capability.body }}</p>
                                </div>
                            </article>
                        </div>

                        <ul class="mt-8 space-y-2.5">
                            <li
                                v-for="highlight in aiHighlights"
                                :key="highlight"
                                class="flex items-start gap-2.5 text-sm text-slate-300"
                            >
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                {{ highlight }}
                            </li>
                        </ul>

                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href="/register"
                                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-xl shadow-violet-600/30 transition hover:from-violet-500 hover:to-indigo-500"
                            >
                                {{ home.ai_section.cta }}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <p class="text-sm text-slate-400">{{ home.ai_section.footnote }}</p>
                        </div>
                    </div>

                    <div class="lg:sticky lg:top-24 lg:self-start">
                        <CentralAiDemoWidget :enabled="aiDemoEnabled" :brand="brand" />

                        <dl class="mt-5 grid grid-cols-3 gap-3">
                            <div
                                v-for="stat in aiStats"
                                :key="stat.label"
                                class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center backdrop-blur transition hover:border-violet-400/30 hover:bg-white/10"
                            >
                                <dt class="bg-gradient-to-r from-violet-300 to-indigo-300 bg-clip-text text-lg font-extrabold tracking-tight text-transparent">{{ stat.value }}</dt>
                                <dd class="mt-1.5 text-[11px] leading-snug text-slate-400">{{ stat.label }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 flex items-center justify-center gap-2 text-xs text-slate-500">
                            <svg class="h-3.5 w-3.5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            {{ home.ai_section.privacy_note }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-10 sm:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ marketingLabels.works_with_your_stack }}</p>
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
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ marketingLabels.platform_overview }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        {{ home.product_section.title }}
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ productSectionSubtitle }}
                    </p>
                </div>
                <div class="mt-12 grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in bentoItems"
                        :key="item.title"
                        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                        :class="item.span"
                    >
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-50 transition duration-300 group-hover:opacity-80" :class="item.accent" />
                        <div class="relative flex h-full flex-col">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md ring-1 ring-white/10 transition duration-300 group-hover:scale-105 dark:bg-slate-950">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" /></svg>
                            </div>
                            <h3 class="mt-5 text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="differentiators" class="relative overflow-hidden bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50 via-white to-white" />
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ marketingLabels.built_different_eyebrow }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl lg:text-5xl">
                        {{ marketingLabels.built_different_title }}
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ builtDifferentSubtitle }}
                    </p>
                </div>

                <div class="mt-16 grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in differentiators"
                        :key="item.title"
                        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                        :class="item.featured ? 'sm:col-span-2 xl:col-span-3 xl:mx-auto xl:max-w-4xl' : ''"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-50 transition duration-300 group-hover:opacity-80"
                            :class="item.accent"
                        />
                        <div class="relative flex h-full flex-col" :class="item.featured ? 'xl:flex-row xl:items-start xl:gap-8' : ''">
                            <div class="flex items-start justify-between gap-3" :class="item.featured ? 'xl:shrink-0 xl:flex-col xl:items-start xl:gap-4' : ''">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md ring-1 ring-white/10 transition duration-300 group-hover:scale-105 dark:bg-slate-950">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                    </svg>
                                </div>
                                <span
                                    v-if="item.badge"
                                    class="shrink-0 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900/90 dark:text-slate-400 dark:ring-slate-700"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                            <div :class="item.featured ? 'xl:min-w-0 xl:flex-1' : ''">
                                <h3 class="mt-5 text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100 xl:mt-0">{{ item.title }}</h3>
                                <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-14 text-center">
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-blue-600/25 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ home.differentiators_cta }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ home.differentiators_footnote }}</p>
                </div>
            </div>
        </section>

        <section id="features" class="overflow-x-clip border-y border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ marketingLabels.deep_dive }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ marketingLabels.explore_by_capability }}</h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ featuresSectionSubtitle }}
                    </p>
                </div>

                <div class="mt-14 grid gap-8 lg:grid-cols-12 lg:gap-10">
                    <nav class="min-w-0 lg:col-span-4 xl:col-span-3" aria-label="Feature categories">
                        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [-webkit-overflow-scrolling:touch] lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
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

                    <div class="min-w-0 lg:col-span-8 xl:col-span-9">
                        <div class="overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                            <div class="relative overflow-hidden bg-gradient-to-br px-6 py-8 text-white sm:px-10 sm:py-10" :class="activeTheme.gradient">
                                <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
                                <div class="relative">
                                    <span v-if="featureCategory === 'itsm'" class="mb-3 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/90 ring-1 ring-white/20">
                                        {{ marketingLabels.built_different_badge_itsm }}
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
                                        <p class="min-w-0 pt-1.5 text-sm font-medium leading-snug text-slate-800 dark:text-slate-200">{{ item.text }}</p>
                                    </article>
                                </div>

                                <div v-if="secondaryHighlights.length" class="mt-5 border-t border-slate-100 dark:border-slate-800 pt-5">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ home.features_section.also_included }}</p>
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

                <div v-if="featurePages.length" class="mt-12 text-center">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ marketingLabels.feature_landing_links }}</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <Link
                            v-for="page in featurePages"
                            :key="page.slug"
                            :href="page.path"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            {{ page.nav_label }}
                        </Link>
                    </div>
                </div>

                <div class="relative mt-24 overflow-hidden rounded-[2rem] border border-slate-200 dark:border-slate-800/80 bg-gradient-to-b from-white via-slate-50/80 to-white p-6 shadow-xl shadow-slate-200/40 sm:p-10 lg:p-12">
                    <div class="pointer-events-none absolute -left-20 top-0 h-64 w-64 rounded-full bg-blue-400/10 blur-3xl" />
                    <div class="pointer-events-none absolute -right-20 bottom-0 h-64 w-64 rounded-full bg-violet-400/10 blur-3xl" />

                    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 dark:bg-blue-950/40 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300 ring-1 ring-blue-100">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ home.features_section.everything_included }}
                            </span>
                            <h3 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ home.features_section.full_platform_title }}</h3>
                            <p class="mt-3 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400">
                                {{ home.features_section.full_platform_body }}
                            </p>
                        </div>
                        <div class="mt-5 flex w-full flex-wrap justify-center gap-4 sm:mt-8 sm:w-auto sm:shrink-0 sm:gap-6 rounded-2xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 px-4 py-3 backdrop-blur-sm sm:px-6 sm:py-4">
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">{{ allFeatures.length }}</p>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ home.features_section.capabilities_label }}</p>
                            </div>
                            <div class="w-px bg-slate-200" />
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">4</p>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ home.features_section.workflow_areas_label }}</p>
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
                                    {{ home.features_section.features_count }}
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

        <section v-if="testimonialsEnabled && testimonials.length" id="social-proof" class="border-y border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-16 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ marketingLabels.social_proof_eyebrow }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ marketingLabels.social_proof_title }}</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 dark:text-slate-400">{{ socialProofSubtitle }}</p>
                </div>

                <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    <span
                        v-for="logo in socialProofLogos"
                        :key="logo"
                        class="rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300"
                    >
                        {{ logo }}
                    </span>
                </div>

                <div class="mt-12 grid gap-6 lg:grid-cols-3">
                    <article
                        v-for="item in testimonials"
                        :key="item.id"
                        class="flex flex-col rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-6 shadow-sm"
                    >
                        <div class="flex gap-0.5">
                            <svg v-for="n in 5" :key="n" class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                        </div>
                        <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                            “{{ item.quote }}”
                        </blockquote>
                        <div class="mt-6 border-t border-slate-200 dark:border-slate-800 pt-4">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.role }} · {{ item.company_type }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="compare" class="bg-slate-900 py-16 sm:py-24 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">{{ marketingLabels.why_teams_switch }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ compareSectionTitle }}</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base text-slate-400 dark:text-slate-500">{{ home.compare_section.subtitle }}</p>
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
                                <p class="font-semibold text-slate-400 dark:text-slate-500">{{ marketingLabels.typical_stack }}</p>
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
                                <th class="px-6 py-4 text-left font-medium text-slate-400 dark:text-slate-500">{{ marketingLabels.capability }}</th>
                                <th class="px-6 py-4 text-center font-semibold text-blue-400">{{ platformName }}</th>
                                <th class="px-6 py-4 text-center font-medium text-slate-400 dark:text-slate-500">{{ marketingLabels.typical_stack }}</th>
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
                <div v-if="comparePages.length" class="mt-12 text-center">
                    <p class="text-sm font-medium text-slate-400">{{ marketingLabels.compare_detailed_pages }}</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <Link
                            v-for="page in comparePages"
                            :key="page.slug"
                            :href="page.path"
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-blue-400/40 hover:bg-blue-500/10 hover:text-white"
                        >
                            {{ compareLinkLabel(page) }}
                            <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </Link>
                    </div>
                </div>
                <div class="mt-14 text-center">
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ home.compare_section.cta }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ home.compare_section.cta_footnote }}</p>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="bg-white dark:bg-slate-900 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ marketingLabels.how_it_works }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ marketingLabels.go_live_in_three_steps }}</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 dark:text-slate-400">{{ home.how_it_works_subtitle }}</p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6 lg:items-stretch">
                    <article
                        v-for="(step, index) in steps"
                        :key="step.title ?? index"
                        class="relative flex h-full min-h-[18rem] flex-col rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-8 transition hover:border-blue-200 dark:hover:border-blue-900/60 hover:shadow-lg"
                    >
                        <div
                            v-if="index < steps.length - 1"
                            class="pointer-events-none absolute left-[calc(50%+2rem)] top-14 hidden h-px w-[calc(100%-4rem)] bg-gradient-to-r from-blue-300 to-blue-100 lg:block"
                            aria-hidden="true"
                        />
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white shadow-lg shadow-blue-600/30">{{ index + 1 }}</div>
                        <h3 class="mt-6 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ step.title }}</h3>
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ step.body }}</p>
                        <p class="mt-4 shrink-0 rounded-lg bg-white dark:bg-slate-900 px-3 py-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700">{{ step.detail }}</p>
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
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">{{ marketingLabels.pricing }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                        {{ home.pricing_section.title }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400 dark:text-slate-500">
                        {{ home.pricing_section.subtitle }}
                    </p>
                    <div class="mt-8 flex justify-center">
                        <div class="inline-flex rounded-xl border border-white/10 bg-white/5 p-1 backdrop-blur">
                            <button
                                type="button"
                                class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                                :class="billingInterval === 'month' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                                @click="billingInterval = 'month'"
                            >{{ marketingLabels.monthly }}</button>
                            <button
                                type="button"
                                class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                                :class="billingInterval === 'year' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                                @click="billingInterval = 'year'"
                            >{{ marketingLabels.yearly }}</button>
                        </div>
                    </div>
                    <p v-if="billingInterval === 'year'" class="mt-3 text-sm font-semibold text-emerald-400">{{ marketingLabels.save_up_to_2_months_with_annual_billing }}</p>
                    <div v-if="indiaEnabled" class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-400 dark:text-slate-500">
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
                        </svg>
                        <span>{{ marketingLabels.show_prices_in }}</span>
                        <div class="inline-flex overflow-hidden rounded-lg border border-white/10">
                            <button
                                type="button"
                                class="px-3 py-1 text-xs font-semibold transition"
                                :class="selectedCurrencyCode === baseCurrency.code ? 'bg-white text-slate-900' : 'text-slate-300 hover:text-white'"
                                @click="setCurrency(baseCurrency.code)"
                            >{{ baseCurrency.symbol }} {{ baseCurrency.code }}</button>
                            <button
                                type="button"
                                class="px-3 py-1 text-xs font-semibold transition"
                                :class="selectedCurrencyCode === indiaCurrency.code ? 'bg-white text-slate-900' : 'text-slate-300 hover:text-white'"
                                @click="setCurrency(indiaCurrency.code)"
                            >{{ indiaCurrency.symbol }} {{ indiaCurrency.code }}</button>
                        </div>
                    </div>
                </div>
                <div class="mt-14 flex flex-wrap justify-center gap-8">
                    <article
                        v-for="plan in pricedPlans"
                        :key="plan.slug"
                        class="relative flex w-full flex-col rounded-3xl border p-6 sm:w-80 sm:p-8 transition"
                        :class="plan.slug === 'professional'
                            ? 'border-blue-500/50 bg-gradient-to-b from-blue-600/20 to-slate-900/80 shadow-2xl shadow-blue-600/20 ring-2 ring-blue-500/40 lg:scale-105'
                            : 'border-white/10 bg-white/5 backdrop-blur hover:border-white/20'"
                    >
                        <span v-if="plan.slug === 'professional'" class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-1 text-xs font-bold text-white shadow-lg">{{ marketingLabels.most_popular }}</span>
                        <h3 class="text-xl font-bold text-white">{{ plan.name }}</h3>
                        <p v-if="planTaglines[plan.slug]" class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ planTaglines[plan.slug] }}</p>
                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ formatPrice(planPrice(plan, isIndia)) }}</span>
                            <span class="text-slate-400 dark:text-slate-500">{{ intervalSuffix }}</span>
                        </p>
                        <p v-if="billingInterval === 'year' && yearlySavingsPercent(plan, isIndia) > 0" class="mt-2 text-sm font-semibold text-emerald-400">
                            {{ formatMarketingTemplate(home.pricing_section.save_vs_monthly ?? '', { percent: yearlySavingsPercent(plan, isIndia) }) }}
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
                            {{ home.pricing_section.start_trial }}
                        </Link>
                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">{{ marketingLabels.no_credit_card_required }}</p>
                    </article>
                </div>

                <div
                    v-for="plan in customPlans"
                    :key="plan.slug"
                    class="mx-auto mt-8 max-w-5xl overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-6 backdrop-blur sm:p-8"
                >
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="lg:max-w-2xl">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-2xl font-bold text-white">{{ plan.name }}</h3>
                                <span class="rounded-full border border-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">{{ marketingLabels.custom_pricing_price }}</span>
                            </div>
                            <p v-if="planTaglines[plan.slug]" class="mt-2 text-sm text-slate-400 dark:text-slate-500">{{ planTaglines[plan.slug] }}</p>
                            <ul class="mt-5 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                                <li v-for="item in planHighlights(plan)" :key="item" class="flex items-start gap-2.5 text-sm text-slate-300">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                        <div class="shrink-0 text-center lg:text-right">
                            <a
                                :href="contactHref"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-8 py-3.5 text-sm font-bold text-slate-900 shadow-xl transition hover:bg-slate-100 sm:w-auto"
                            >
                                {{ marketingLabels.contact_us }}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </a>
                            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">{{ marketingLabels.custom_pricing_cta_hint }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="addons.length" class="mx-auto mt-16 max-w-5xl">
                    <div class="text-center">
                        <p class="text-sm font-semibold uppercase tracking-wider text-violet-400">{{ marketingLabels.pricing_addons_label }}</p>
                        <h3 class="mt-2 text-2xl font-bold text-white sm:text-3xl">{{ marketingLabels.pricing_addons_title }}</h3>
                        <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400">{{ marketingLabels.pricing_addons_subtitle }}</p>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="addon in addons"
                            :key="addon.key"
                            class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur transition hover:border-white/20"
                        >
                            <h4 class="text-lg font-bold text-white">{{ addon.name }}</h4>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-400">{{ addon.description }}</p>
                            <p class="mt-5 text-2xl font-extrabold text-white">
                                {{ formatPrice(addonPrice(addon)) }}
                                <span class="text-sm font-medium text-slate-400">{{ marketingLabels.pricing_addon_per_month }}</span>
                            </p>
                        </article>
                    </div>
                    <p class="mt-8 text-center text-sm text-slate-400">
                        <Link href="/features/data-residency" class="font-semibold text-sky-300 transition hover:text-sky-200">
                            {{ featurePages.find((page) => page.slug === 'data-residency')?.nav_label ?? 'Data Residency' }} →
                        </Link>
                    </p>
                </div>
            </div>
        </section>

        <CentralMarketingLeadCapture variant="dark" />

        <FaqAccordion
            id="faq"
            :items="faqs"
            :eyebrow="marketingLabels.faq"
            :title="marketingLabels.common_questions"
            section-class="bg-white dark:bg-slate-900 py-16 sm:py-24"
            list-class="mt-12 space-y-3"
        />

        <section class="relative overflow-hidden bg-slate-950 py-20 sm:py-28">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-blue-900/50 via-slate-950 to-slate-950" />
                <div class="absolute left-1/2 top-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-600/10 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-white/10 to-white/5 p-6 text-center backdrop-blur-xl sm:p-10 lg:p-14">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-300">{{ home.final_cta.eyebrow }}</p>
                    <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                        {{ home.final_cta.title_line1 }}<br class="hidden sm:block" />
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">{{ home.final_cta.title_highlight }}</span>
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">
                        {{ home.final_cta.body }}
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href="/register"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-10 py-4 text-base font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500 sm:w-auto"
                        >
                            {{ home.final_cta.start_trial }}
                        </Link>
                        <Link
                            href="/login"
                            class="inline-flex w-full items-center justify-center rounded-2xl border border-white/20 px-10 py-4 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto"
                        >
                            {{ home.final_cta.sign_in }}
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
