<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { useCurrency } from '../../composables/useCurrency.js';

const props = defineProps({
    brand: { type: String, default: 'Helpdesk' },
    trialDays: { type: Number, default: 14 },
    plans: { type: Array, default: () => [] },
    currency: { type: Object, default: () => ({ code: 'USD', symbol: '$', name: 'US Dollar' }) },
});

const { formatPrice } = useCurrency(() => props.currency);

const previewTab = ref('inbox');
const featureCategory = ref('operations');
const openFaq = ref(null);

const featureLabels = {
    automation: 'Automation & macros',
    service_catalog: 'Service catalog',
    channels: 'Live chat & channels',
    sla: 'SLA & business hours',
    workspace: 'Multi-brand workspace',
    ai: 'AI assist',
    integrations: 'Integrations & webhooks',
    assets: 'Asset management',
};

const stats = [
    { value: '3×', label: 'Faster first response with unified inbox' },
    { value: '40%', label: 'Fewer repeat tickets with knowledge base' },
    { value: '<2 min', label: 'Average workspace setup time' },
    { value: '24/7', label: 'Self-service portal for customers' },
];

const previewTabs = [
    { id: 'inbox', label: 'Shared inbox' },
    { id: 'chat', label: 'Live chat' },
    { id: 'analytics', label: 'Analytics' },
];

const featureCategories = [
    { id: 'operations', label: 'Ticket operations', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { id: 'channels', label: 'Channels', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { id: 'selfservice', label: 'Self-service', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { id: 'automation', label: 'Automation & AI', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { id: 'platform', label: 'Platform', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
];

const categoryContent = {
    operations: {
        title: 'Run support like a well-oiled machine',
        description: 'Every conversation becomes a trackable ticket with ownership, priority, SLA timers, and full history — no more lost emails or duplicate replies.',
        highlights: [
            'Unified ticket inbox with filters, views, and bulk actions',
            'Assignments, teams, departments, and collision detection',
            'Priorities, statuses, tags, and custom ticket fields',
            'Side conversations and internal notes',
            'Time tracking and audit trail on every ticket',
            'Exports and scheduled reports for leadership',
        ],
    },
    channels: {
        title: 'Meet customers where they are',
        description: 'Email, live chat, and your branded portal all feed the same queue. Agents reply once; customers get answers everywhere.',
        highlights: [
            'Inbound email with multiple addresses and routing rules',
            'Embeddable live chat widget with visitor context',
            'Branded customer portal for ticket submission and tracking',
            'Multi-brand support with separate portals and inboxes',
            'CSAT surveys on resolved tickets and email threads',
            'Real-time notifications when customers reply',
        ],
    },
    selfservice: {
        title: 'Deflect tickets before they arrive',
        description: 'Publish a searchable knowledge base, organize articles by collection, and let customers solve problems on their own.',
        highlights: [
            'Rich-text articles with categories and collections',
            'Public help center linked from your portal',
            'Article search and suggested content for agents',
            'Service catalog for structured request types',
            'Guest and authenticated customer ticket tracking',
            'CSAT feedback to measure article effectiveness',
        ],
    },
    automation: {
        title: 'Automate the repetitive work',
        description: 'Route tickets automatically, apply macros, trigger workflows, and let AI draft replies so agents focus on complex issues.',
        highlights: [
            'Assignment rules based on channel, brand, or keywords',
            'Canned responses and macro actions',
            'Automation triggers on ticket events',
            'AI-suggested replies and thread summaries',
            'SLA policies with business hours and escalations',
            'Webhook and integration-driven workflows',
        ],
    },
    platform: {
        title: 'Built for teams that scale',
        description: 'Role-based access, multi-workspace tenancy, integrations with your stack, and billing that grows with your team.',
        highlights: [
            'Admin, agent, and customer roles with permissions',
            'Asset management and service catalog (Enterprise)',
            'Slack, Jira, and Linear integrations',
            'Security settings, 2FA, and audit logs',
            'Per-workspace subdomain with isolated data',
            'Usage-based plan limits with clear upgrade paths',
        ],
    },
};

const allFeatures = [
    { title: 'Shared inbox & tickets', description: 'Manage email, chat, and portal requests in one workspace with assignments and collision detection.', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    { title: 'Live chat widget', description: 'Embed a lightweight chat widget on your site. Visitors become tickets your team can reply to instantly.', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { title: 'Knowledge base & portal', description: 'Publish help articles, deflect common questions, and give customers a branded self-service portal.', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { title: 'SLA & business hours', description: 'Set response targets, escalation rules, and operating hours so your team stays accountable.', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { title: 'Automation & macros', description: 'Route tickets automatically, apply canned responses, and eliminate repetitive manual work.', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { title: 'AI assist', description: 'Draft replies, summarize threads, and surface relevant knowledge without leaving the ticket.', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { title: 'CSAT & reporting', description: 'Measure customer satisfaction and track team performance with real-time dashboards.', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { title: 'Multi-brand workspaces', description: 'Run multiple brands, portals, and inboxes from a single workspace with isolated routing.', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { title: 'Integrations', description: 'Connect Slack, Jira, Linear, and webhooks to keep support in sync with the rest of your stack.', icon: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z' },
    { title: 'Contacts & organizations', description: 'Track customers, companies, VIP tags, and full conversation history in one place.', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { title: 'Security & compliance', description: 'Two-factor authentication, role permissions, data retention policies, and audit logs.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: 'Workforce management', description: 'Organize agents into teams and departments with performance insights and workload visibility.', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
];

const bentoItems = [
    { title: 'One inbox, every channel', body: 'Email, chat, and portal tickets land in a single queue with smart routing.', span: 'lg:col-span-2', accent: 'from-blue-600/20 to-indigo-600/10' },
    { title: 'SLA timers', body: 'Never miss a deadline with visual countdowns and breach alerts.', span: '', accent: 'from-amber-500/20 to-orange-500/10' },
    { title: 'AI reply drafts', body: 'Agents get suggested responses grounded in your knowledge base.', span: '', accent: 'from-violet-600/20 to-purple-600/10' },
    { title: 'Customer portal', body: 'Branded self-service hub for articles, ticket submission, and tracking.', span: 'lg:col-span-2', accent: 'from-emerald-600/20 to-teal-600/10' },
];

const steps = [
    { title: 'Create your workspace', body: 'Pick a subdomain, register in seconds, and get a dedicated environment for your team — no credit card required.', detail: 'Your workspace lives at your-company.helpdesk.test with isolated data and admin access.' },
    { title: 'Connect your channels', body: 'Follow the guided setup wizard to configure email, chat widget, portal branding, and SLA policies.', detail: 'Inbound email, outbound SMTP, and chat embed code — all configured step by step.' },
    { title: 'Invite your team & go live', body: 'Add agents, assign roles, publish your knowledge base, and start resolving tickets from day one.', detail: 'Full platform access during your free trial. Choose a plan when your trial ends.' },
];

const integrations = ['Email', 'Live chat', 'Slack', 'Jira', 'Linear', 'Webhooks', 'REST API', 'CSAT surveys'];

const comparisons = [
    { feature: 'Unified inbox (email + chat + portal)', us: true, them: false },
    { feature: 'Built-in knowledge base & portal', us: true, them: 'Add-on' },
    { feature: 'SLA policies & business hours', us: true, them: 'Enterprise only' },
    { feature: 'AI-assisted replies', us: true, them: 'Extra cost' },
    { feature: 'Multi-brand workspaces', us: true, them: false },
    { feature: 'Self-hosted / your subdomain', us: true, them: false },
    { feature: 'Free trial, no credit card', us: true, them: 'Limited' },
];

const faqs = computed(() => [
    { q: `How does the ${props.trialDays}-day free trial work?`, a: `Sign up and get full platform access for ${props.trialDays} days. No credit card required. When your trial ends, choose a plan that fits your team to keep your workspace active.` },
    { q: 'Can I use my own domain?', a: 'Each workspace gets its own subdomain (e.g. acme.yourplatform.com). Custom domain mapping can be configured at the infrastructure level for self-hosted deployments.' },
    { q: 'What channels are supported?', a: 'Email (inbound and outbound), live chat widget, and a branded customer portal. All channels create tickets in the same shared inbox.' },
    { q: 'Is there an API?', a: 'Yes. A REST API is available for authentication, tickets, contacts, and billing snapshots — ideal for custom integrations and internal tooling.' },
    { q: 'How does pricing work after the trial?', a: 'Plans are based on team size (agents) and monthly ticket volume. Upgrade anytime from your workspace billing settings. Platform admins can also adjust plans centrally.' },
    { q: 'Can I migrate from another helpdesk?', a: 'Export tickets and contacts via CSV, or use the API to import data programmatically. Our team-style inbox makes it easy to ramp without losing context.' },
]);

const activeCategory = computed(() => categoryContent[featureCategory.value]);

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
</script>

<template>
    <Head :title="`${brand} — Customer support software`" />
    <CentralLayout :brand="brand" :trial-days="trialDays" transparent-nav>
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-40 top-0 h-[32rem] w-[32rem] rounded-full bg-blue-600/25 blur-3xl" />
                <div class="absolute right-0 top-20 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl" />
                <div class="absolute bottom-0 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-violet-600/15 blur-3xl" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:4rem_4rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 pb-24 pt-16 sm:px-6 lg:px-8 lg:pb-32 lg:pt-24">
                <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-medium text-blue-200 backdrop-blur">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                            </span>
                            {{ trialDays }}-day free trial · No credit card · Full access
                        </div>
                        <h1 class="mt-8 text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                            The modern helpdesk for teams who care about customers
                        </h1>
                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-300">
                            {{ brand }} unifies tickets, live chat, knowledge base, SLAs, and AI into one workspace — so your team stops juggling tools and starts resolving issues faster.
                        </p>
                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <Link href="/register" class="group inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3.5 text-sm font-semibold text-white shadow-xl shadow-blue-600/30 transition hover:bg-blue-500 hover:shadow-blue-500/40">
                                Start free trial
                                <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <a href="#product" class="rounded-xl border border-white/15 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/5">
                                Explore product
                            </a>
                        </div>
                        <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="stat in stats" :key="stat.label" class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur">
                                <p class="text-xl font-bold text-white">{{ stat.value }}</p>
                                <p class="mt-1 text-[11px] leading-snug text-slate-400">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="mb-4 flex gap-2">
                            <button
                                v-for="tab in previewTabs"
                                :key="tab.id"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                :class="previewTab === tab.id ? 'bg-white text-slate-900 shadow' : 'bg-white/10 text-slate-300 hover:bg-white/15'"
                                @click="previewTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-2 shadow-2xl shadow-black/50 backdrop-blur-xl">
                            <div class="overflow-hidden rounded-xl bg-slate-900 ring-1 ring-white/10">
                                <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-400/90" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400/90" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/90" />
                                    <span class="ml-2 text-xs text-slate-500">your-team.{{ brand.toLowerCase() }}.com</span>
                                </div>

                                <div v-if="previewTab === 'inbox'" class="grid grid-cols-5">
                                    <div class="col-span-2 border-r border-white/10 bg-slate-950/90 p-4">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Open tickets · 12</p>
                                        <div class="mt-3 space-y-2">
                                            <div class="rounded-lg bg-blue-500/20 px-3 py-2 ring-1 ring-blue-500/40">
                                                <p class="text-xs font-medium text-white">Payment failed — need help</p>
                                                <p class="mt-0.5 text-[10px] text-slate-400">Sarah · SLA 18m · Assigned to you</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">Chat: shipping question</p>
                                                <p class="mt-0.5 text-[10px] text-emerald-400">Live · waiting</p>
                                            </div>
                                            <div class="rounded-lg px-3 py-2 hover:bg-white/5">
                                                <p class="text-xs text-slate-300">API rate limit error</p>
                                                <p class="mt-0.5 text-[10px] text-slate-500">Dev team · 14m ago</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-3 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium">Payment failed — need help</p>
                                            <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-medium text-amber-300">High · SLA 18m</span>
                                        </div>
                                        <div class="mt-4 space-y-3">
                                            <div class="rounded-lg bg-white/5 p-3"><p class="text-xs text-slate-300">Hi, my subscription payment failed but I was still charged...</p></div>
                                            <div class="rounded-lg bg-blue-600/25 p-3 ring-1 ring-blue-500/30"><p class="text-xs text-blue-100">I can see the duplicate charge — refunding now and extending your plan by 7 days.</p></div>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="rounded-md bg-violet-500/20 px-2 py-1 text-[10px] text-violet-200">AI draft ready</span>
                                            <span class="rounded-md bg-white/5 px-2 py-1 text-[10px] text-slate-400">Billing</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="previewTab === 'chat'" class="p-4">
                                    <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-xs text-emerald-300">V</span>
                                        <div>
                                            <p class="text-sm font-medium">Visitor on /pricing</p>
                                            <p class="text-[10px] text-emerald-400">Online · San Francisco</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">Do you offer annual billing?</p></div>
                                        <div class="ml-auto max-w-[80%] rounded-2xl rounded-br-md bg-blue-600/40 px-3 py-2"><p class="text-xs text-blue-50">Yes! Annual plans save 20%. I can send details to your email.</p></div>
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-white/10 px-3 py-2"><p class="text-xs text-slate-200">Perfect, please do.</p></div>
                                    </div>
                                    <p class="mt-4 text-center text-[10px] text-slate-500">Conversation saved as ticket #1042</p>
                                </div>

                                <div v-else class="p-4">
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500">First response</p><p class="mt-1 text-lg font-bold text-emerald-400">4.2m</p><p class="text-[10px] text-emerald-400/80">↓ 18% vs last week</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500">CSAT score</p><p class="mt-1 text-lg font-bold text-white">94%</p><p class="text-[10px] text-slate-400">128 responses</p></div>
                                        <div class="rounded-lg bg-white/5 p-3"><p class="text-[10px] text-slate-500">Resolved today</p><p class="mt-1 text-lg font-bold text-white">47</p><p class="text-[10px] text-slate-400">6 open</p></div>
                                    </div>
                                    <div class="mt-4 h-24 rounded-lg bg-gradient-to-t from-blue-600/20 to-transparent p-3">
                                        <div class="flex h-full items-end gap-1">
                                            <div v-for="(h, i) in [40, 55, 35, 70, 50, 85, 60, 75, 90, 65]" :key="i" class="flex-1 rounded-t bg-blue-500/60" :style="{ height: `${h}%` }" />
                                        </div>
                                    </div>
                                    <p class="mt-2 text-center text-[10px] text-slate-500">Ticket volume · last 10 days</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 bg-white py-8">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-4 px-4 sm:px-6 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Works with your stack</p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <span v-for="name in integrations" :key="name" class="rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-sm font-medium text-slate-600">{{ name }}</span>
                </div>
            </div>
        </section>

        <section id="product" class="bg-slate-50 py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Platform overview</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        One platform for every support workflow
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600">
                        From the first customer message to resolution and feedback — {{ brand }} gives agents the context, tools, and automation they need without switching tabs.
                    </p>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="item in bentoItems"
                        :key="item.title"
                        class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-lg"
                        :class="item.span"
                    >
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-60 transition group-hover:opacity-100" :class="item.accent" />
                        <div class="relative">
                            <h3 class="text-lg font-semibold text-slate-900">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ item.body }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="features" class="border-y border-slate-200 bg-white py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Deep dive</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Explore by capability</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">Click a category to see how {{ brand }} handles each part of modern customer support.</p>
                </div>

                <div class="mt-10 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="cat in featureCategories"
                        :key="cat.id"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition"
                        :class="featureCategory === cat.id ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/25' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="featureCategory = cat.id"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="cat.icon" /></svg>
                        {{ cat.label }}
                    </button>
                </div>

                <div class="mt-12 grid gap-10 lg:grid-cols-2 lg:items-start">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900">{{ activeCategory.title }}</h3>
                        <p class="mt-4 text-lg leading-relaxed text-slate-600">{{ activeCategory.description }}</p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="item in activeCategory.highlights" :key="item" class="flex items-start gap-3 text-sm text-slate-700">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-blue-50/50 p-8">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Included in {{ brand }}</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <div v-for="feature in allFeatures.slice(0, 6)" :key="feature.title" class="rounded-xl border border-white bg-white/80 p-4 shadow-sm">
                                <p class="text-sm font-semibold text-slate-900">{{ feature.title }}</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ feature.description.slice(0, 80) }}…</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-20">
                    <h3 class="text-center text-xl font-semibold text-slate-900">Full feature list</h3>
                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <article
                            v-for="feature in allFeatures"
                            :key="feature.title"
                            class="group rounded-2xl border border-slate-200 bg-slate-50/50 p-5 transition hover:-translate-y-0.5 hover:border-blue-200 hover:bg-white hover:shadow-md"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm transition group-hover:scale-105">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="feature.icon" /></svg>
                            </div>
                            <h4 class="mt-4 font-semibold text-slate-900">{{ feature.title }}</h4>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ feature.description }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-900 py-24 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">Why teams switch</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ brand }} vs. pieced-together tools</h2>
                </div>
                <div class="mt-12 overflow-hidden rounded-2xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/5">
                                <th class="px-6 py-4 text-left font-medium text-slate-400">Capability</th>
                                <th class="px-6 py-4 text-center font-semibold text-blue-400">{{ brand }}</th>
                                <th class="px-6 py-4 text-center font-medium text-slate-400">Typical stack</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in comparisons" :key="row.feature" class="border-b border-white/5 transition hover:bg-white/5">
                                <td class="px-6 py-4 text-slate-300">{{ row.feature }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="row.us === true" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">✓</span>
                                    <span v-else class="text-slate-400">{{ row.us }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-500">
                                    <span v-if="row.them === false" class="text-slate-600">—</span>
                                    <span v-else-if="row.them === true" class="text-emerald-400">✓</span>
                                    <span v-else>{{ row.them }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="bg-white py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">How it works</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Go live in three steps</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">Most teams complete setup during their {{ trialDays }}-day trial — guided every step of the way.</p>
                </div>
                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    <article v-for="(step, index) in steps" :key="step.title" class="relative rounded-2xl border border-slate-200 bg-slate-50 p-8 transition hover:border-blue-200 hover:shadow-lg">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white shadow-lg shadow-blue-600/30">{{ index + 1 }}</div>
                        <h3 class="mt-6 text-xl font-semibold text-slate-900">{{ step.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ step.body }}</p>
                        <p class="mt-4 rounded-lg bg-white px-3 py-2 text-xs leading-relaxed text-slate-500 ring-1 ring-slate-200">{{ step.detail }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="pricing" class="bg-slate-50 py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Pricing</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Plans that grow with your team</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">
                        Every workspace starts with a {{ trialDays }}-day free trial — full access, no credit card. Pick a plan when your trial ends.
                    </p>
                </div>
                <div class="mt-14 grid gap-8 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.slug"
                        class="relative flex flex-col rounded-2xl border bg-white p-8 transition hover:shadow-xl"
                        :class="plan.slug === 'professional' ? 'border-blue-600 shadow-xl shadow-blue-600/10 ring-2 ring-blue-600/20 lg:scale-105' : 'border-slate-200'"
                    >
                        <span v-if="plan.slug === 'professional'" class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-blue-600 px-4 py-1 text-xs font-semibold text-white">Most popular</span>
                        <h3 class="text-xl font-semibold text-slate-900">{{ plan.name }}</h3>
                        <p class="mt-4 flex items-baseline gap-1">
                            <span class="text-5xl font-bold tracking-tight text-slate-900">{{ formatPrice(plan.price) }}</span>
                            <span class="text-slate-500">/month</span>
                        </p>
                        <ul class="mt-8 flex-1 space-y-3">
                            <li v-for="item in planHighlights(plan)" :key="item" class="flex items-start gap-2.5 text-sm text-slate-600">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ item }}
                            </li>
                        </ul>
                        <Link
                            href="/register"
                            class="mt-8 block rounded-xl py-3 text-center text-sm font-semibold transition"
                            :class="plan.slug === 'professional' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-200 text-slate-900 hover:bg-slate-50'"
                        >
                            Start {{ trialDays }}-day free trial
                        </Link>
                        <p class="mt-3 text-center text-xs text-slate-500">No credit card required</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="faq" class="bg-white py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">FAQ</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Common questions</h2>
                </div>
                <div class="mt-12 space-y-3">
                    <div v-for="(item, index) in faqs" :key="item.q" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 transition hover:border-slate-300">
                        <button type="button" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left" @click="toggleFaq(index)">
                            <span class="font-semibold text-slate-900">{{ item.q }}</span>
                            <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="openFaq === index ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-show="openFaq === index" class="border-t border-slate-200 px-6 pb-5 pt-2">
                            <p class="text-sm leading-relaxed text-slate-600">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden bg-slate-950 py-24">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-blue-900/40 via-slate-950 to-slate-950" />
            <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Ready to transform your customer support?
                </h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-400">
                    Join teams who replaced scattered inboxes with one modern workspace. Start your {{ trialDays }}-day trial — setup takes minutes, not weeks.
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link href="/register" class="rounded-xl bg-blue-600 px-8 py-4 text-sm font-semibold text-white shadow-xl shadow-blue-600/30 transition hover:bg-blue-500">
                        Start free trial →
                    </Link>
                    <Link href="/login" class="rounded-xl border border-white/15 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/5">
                        Sign in to workspace
                    </Link>
                </div>
            </div>
        </section>
    </CentralLayout>
</template>
