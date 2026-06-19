<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';
import { useDateTime } from '../../../../composables/useDateTime.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    slowQueries: Object,
    filters: Object,
    summary: Object,
    thresholdMs: Number,
});

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const selectedIds = ref([]);
const bulkDeleteForm = useForm({ ids: [] });
const filteredDeleteForm = useForm({
    tenant_id: props.filters.tenant_id ?? '',
    connection: props.filters.connection ?? '',
    min_time_ms: props.filters.min_time_ms ?? '',
    search: props.filters.search ?? '',
});

const filterForm = useForm({
    tenant_id: props.filters.tenant_id ?? '',
    connection: props.filters.connection ?? '',
    min_time_ms: props.filters.min_time_ms ?? '',
    search: props.filters.search ?? '',
});

const pageIds = computed(() => props.slowQueries.data?.map((entry) => entry.id) ?? []);
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)));
const hasSelection = computed(() => selectedIds.value.length > 0);
const hasActiveFilters = computed(() => Object.values(props.filters ?? {}).some((value) => value !== null && value !== ''));

const toggleAllOnPage = (event) => {
    selectedIds.value = event.target.checked ? [...pageIds.value] : [];
};

const toggleRow = (id, event) => {
    if (event.target.checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value = [...selectedIds.value, id];
        }

        return;
    }

    selectedIds.value = selectedIds.value.filter((value) => value !== id);
};

const applyFilters = () => {
    selectedIds.value = [];
    router.get('/admin/slow-queries', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    filterForm.tenant_id = '';
    filterForm.connection = '';
    filterForm.min_time_ms = '';
    filterForm.search = '';
    applyFilters();
};

const deleteSelected = () => {
    if (!hasSelection.value) {
        return;
    }

    if (!window.confirm(t('central.slow_queries_delete_selected_confirm', { count: selectedIds.value.length }))) {
        return;
    }

    bulkDeleteForm.ids = selectedIds.value;
    bulkDeleteForm.delete('/admin/slow-queries/bulk', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
};

const deleteMatching = () => {
    if (!window.confirm(t('central.slow_queries_delete_filtered_confirm'))) {
        return;
    }

    filteredDeleteForm.tenant_id = filterForm.tenant_id;
    filteredDeleteForm.connection = filterForm.connection;
    filteredDeleteForm.min_time_ms = filterForm.min_time_ms;
    filteredDeleteForm.search = filterForm.search;

    filteredDeleteForm.delete('/admin/slow-queries', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
};

const truncateSql = (sql, limit = 120) => {
    if (!sql || sql.length <= limit) {
        return sql;
    }

    return `${sql.slice(0, limit)}…`;
};
</script>

<template>
    <Head :title="$t('central.slow_queries')" />
    <AdminLayout>
        <PageHeader
            :title="$t('central.slow_queries')"
            :description="$t('central.slow_queries_description', { threshold: thresholdMs })"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <PlatformStatCard :label="$t('central.slow_queries_7d')" :value="summary.total ?? 0" tone="amber" />
            <PlatformStatCard :label="$t('central.slow_queries_avg_ms')" :value="summary.avg_time_ms ?? 0" tone="blue" />
            <PlatformStatCard :label="$t('central.slow_queries_max_ms')" :value="summary.max_time_ms ?? 0" tone="red" />
            <PlatformStatCard :label="$t('central.slow_queries_workspaces')" :value="summary.tenant_count ?? 0" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.workspace_id') }}</label>
                    <input v-model="filterForm.tenant_id" type="text" :class="adminInputClass" :placeholder="$t('central.tenant_uuid')" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.connection') }}</label>
                    <input v-model="filterForm.connection" type="text" :class="adminInputClass" placeholder="tenant" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.min_duration_ms') }}</label>
                    <input v-model="filterForm.min_time_ms" type="number" min="1" :class="adminInputClass" placeholder="500" />
                </div>
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.search') }}</label>
                    <input v-model="filterForm.search" type="text" :class="adminInputClass" :placeholder="$t('central.slow_queries_search_placeholder')" />
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" @click="applyFilters">
                    {{ $t('central.filter') }}
                </button>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" @click="clearFilters">
                    {{ $t('central.clear') }}
                </button>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50"
                    :disabled="!hasSelection || bulkDeleteForm.processing"
                    @click="deleteSelected"
                >
                    {{ $t('central.slow_queries_delete_selected', { count: selectedIds.length }) }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50"
                    :disabled="filteredDeleteForm.processing"
                    @click="deleteMatching"
                >
                    {{ hasActiveFilters ? $t('central.slow_queries_delete_filtered') : $t('central.slow_queries_delete_all') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <th class="px-3 py-2 font-medium">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900"
                                    :checked="allPageSelected"
                                    @change="toggleAllOnPage"
                                />
                            </th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.when') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.duration') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings.groups.workspace') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.connection') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.route') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.source') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.request') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.query') }}</th>
                            <th class="px-3 py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in slowQueries.data"
                            :key="entry.id"
                            class="border-b border-slate-100 align-top dark:border-slate-800/80"
                        >
                            <td class="px-3 py-3">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900"
                                    :checked="selectedIds.includes(entry.id)"
                                    @change="toggleRow(entry.id, $event)"
                                />
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-700 dark:text-slate-300">
                                {{ formatDateTime(entry.created_at) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 font-semibold text-amber-700 dark:text-amber-300">
                                {{ entry.time_ms }} ms
                            </td>
                            <td class="px-3 py-3 text-slate-700 dark:text-slate-300">
                                <span v-if="entry.tenant">{{ entry.tenant.name }}</span>
                                <span v-else-if="entry.tenant_id" class="font-mono text-xs">{{ entry.tenant_id }}</span>
                                <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                            </td>
                            <td class="px-3 py-3 text-slate-700 dark:text-slate-300">
                                <p>{{ entry.connection }}</p>
                                <p v-if="entry.database_host || entry.database_name" class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ entry.database_host }}<span v-if="entry.database_name"> / {{ entry.database_name }}</span>
                                </p>
                            </td>
                            <td class="px-3 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ entry.route_name || '—' }}
                            </td>
                            <td class="max-w-[14rem] px-3 py-3 text-xs text-slate-600 dark:text-slate-400">
                                <p v-if="entry.source_callable" class="font-medium text-slate-700 dark:text-slate-300">{{ entry.source_callable }}</p>
                                <p v-if="entry.source_file" class="mt-0.5 font-mono">{{ entry.source_file }}<span v-if="entry.source_line">:{{ entry.source_line }}</span></p>
                                <span v-if="!entry.source_callable && !entry.source_file">—</span>
                            </td>
                            <td class="max-w-[14rem] px-3 py-3 text-slate-600 dark:text-slate-400">
                                <span v-if="entry.method" class="font-medium text-slate-700 dark:text-slate-300">{{ entry.method }}</span>
                                <span v-if="entry.url" class="mt-0.5 block truncate text-xs" :title="entry.url">{{ entry.url }}</span>
                                <span v-if="!entry.method && !entry.url" class="text-slate-400 dark:text-slate-500">—</span>
                            </td>
                            <td class="max-w-[24rem] px-3 py-3">
                                <p class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ truncateSql(entry.sql) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link
                                    :href="`/admin/slow-queries/${entry.id}`"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200"
                                >
                                    {{ $t('central.view_details') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!slowQueries.data?.length">
                            <td colspan="10" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ $t('central.slow_queries_empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks v-if="slowQueries.links?.length > 3" class="mt-6" :links="slowQueries.links" />
        </div>
    </AdminLayout>
</template>
