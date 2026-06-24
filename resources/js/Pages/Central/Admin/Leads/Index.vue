<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    leads: Object,
    stats: Object,
    filters: Object,
    sources: Object,
    intents: Object,
    statuses: Object,
});

const { formatDateTime } = useDateTime();
const { can } = usePlatformAdmin();
const canManage = can('leads.manage');

const search = ref(props.filters.q ?? '');
const source = ref(props.filters.source ?? '');
const intent = ref(props.filters.intent ?? '');
const status = ref(props.filters.status ?? '');
const consent = ref(props.filters.consent ?? '');

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch([source, intent, status, consent], applyFilters);

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});

function applyFilters() {
    router.get('/admin/leads', {
        q: search.value !== '' ? search.value : undefined,
        source: source.value || undefined,
        intent: intent.value || undefined,
        status: status.value || undefined,
        consent: consent.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const label = (map, key) => map?.[key] ?? key;

const statusClass = (value) => {
    if (value === 'new') return 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900';
    if (value === 'qualified') return 'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900';
    if (value === 'converted') return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900';
    if (value === 'spam') return 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900';

    return 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700';
};

const hasFilters = computed(() => Boolean(
    props.filters.q
    || props.filters.source
    || props.filters.intent
    || props.filters.status
    || props.filters.consent,
));
</script>

<template>
    <Head title="Marketing leads" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Marketing leads"
                description="Inbound interest from the homepage, chatbot, contact form, and incomplete signups."
            />

            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <PlatformStatCard label="Total" :value="stats.total" />
                <PlatformStatCard label="New" :value="stats.new" tone="blue" />
                <PlatformStatCard label="Contacted" :value="stats.contacted" />
                <PlatformStatCard label="Qualified" :value="stats.qualified" tone="emerald" />
                <PlatformStatCard label="With consent" :value="stats.with_consent" tone="emerald" />
                <PlatformStatCard label="Last 7 days" :value="stats.last_7_days" tone="blue" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Search</label>
                        <input v-model="search" type="text" :class="adminInputClass" placeholder="Email, name, company, message" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Source</label>
                        <select v-model="source" :class="adminInputClass">
                            <option value="">All</option>
                            <option v-for="(labelText, key) in sources" :key="key" :value="key">{{ labelText }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Intent</label>
                        <select v-model="intent" :class="adminInputClass">
                            <option value="">All</option>
                            <option v-for="(labelText, key) in intents" :key="key" :value="key">{{ labelText }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                        <select v-model="status" :class="adminInputClass">
                            <option value="">All</option>
                            <option v-for="(labelText, key) in statuses" :key="key" :value="key">{{ labelText }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Consent</label>
                        <select v-model="consent" :class="adminInputClass">
                            <option value="">All</option>
                            <option value="yes">Marketing opt-in</option>
                            <option value="no">No opt-in</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950/80">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Lead</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Intent</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-950/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ lead.name || 'Unknown' }}</p>
                                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ lead.email }}</p>
                                    <p v-if="lead.company" class="text-xs text-slate-500">{{ lead.company }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ label(sources, lead.source) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ label(intents, lead.intent) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClass(lead.status)">
                                        {{ label(statuses, lead.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ formatDateTime(lead.created_at) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/admin/leads/${lead.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!leads.data.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ hasFilters ? 'No leads match your filters.' : 'No marketing leads captured yet.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="leads.links?.length > 3" class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                    <PaginationLinks :links="leads.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
