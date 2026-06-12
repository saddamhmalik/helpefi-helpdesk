<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    assets: Object,
    stats: Object,
    meta: Object,
    organizations: Array,
    filters: Object,
});

const { t } = useI18n();

const inputClass = 'w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
const showImport = ref(false);
const importForm = useForm({ file: null });

const submitImport = () => {
    importForm.post('/assets/import', {
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            showImport.value = false;
        },
    });
};

const onImportFileChange = (event) => {
    importForm.file = event.target.files[0] ?? null;
};

const statusLabel = (value) => props.meta.statuses.find((item) => item.value === value)?.label ?? value;

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    Object.entries(props.filters || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '' && value !== false) {
            params.set(key, value);
        }
    });

    const query = params.toString();

    return query ? `/assets/export?${query}` : '/assets/export';
});

const applyFilters = (event) => {
    const form = event.target;

    router.get('/assets', {
        search: form.search.value || undefined,
        status: form.status.value || undefined,
        asset_type_id: form.asset_type_id.value || undefined,
        organization_id: form.organization_id.value || undefined,
        unassigned: form.unassigned.checked || undefined,
        warranty_expiring: form.warranty_expiring.checked || undefined,
    }, { preserveState: true, replace: true });
};

const warrantyClass = (asset) => {
    if (!asset.warranty_expires_at) {
        return '';
    }

    const expires = new Date(asset.warranty_expires_at);
    const now = new Date();
    const soon = new Date();
    soon.setDate(soon.getDate() + 30);

    if (expires < now) {
        return 'text-red-600';
    }

    if (expires <= soon) {
        return 'text-amber-600';
    }

    return 'text-slate-600 dark:text-slate-400';
};

const statCards = computed(() => [
    { label: t('assets.total_assets'), value: props.stats?.total ?? 0 },
    { label: t('assets.in_use'), value: props.stats?.in_use ?? 0 },
    { label: t('assets.in_stock'), value: props.stats?.in_stock ?? 0 },
    { label: t('assets.unassigned'), value: props.stats?.unassigned ?? 0 },
    { label: t('assets.warranty_expiring'), value: props.stats?.warranty_expiring ?? 0, accent: 'amber' },
    { label: t('assets.warranty_expired'), value: props.stats?.warranty_expired ?? 0, accent: 'red' },
]);
</script>

<template>
    <Head :title="$t('assets.assets')" />
    <AgentLayout>
        <PageHeader :description="$t('assets.cmdb_inventory_warranty_tracking_discovery_and_ticket_linkage')">
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                    @click="showImport = !showImport"
                >{{ $t('assets.import_csv') }}</button>
                <a
                    :href="exportUrl"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    {{ $t('assets.export_csv') }}
                </a>
                <Link href="/assets/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('assets.new_asset') }}</Link>
            </template>
        </PageHeader>

        <AssetsNav />

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="card.accent === 'amber' ? 'text-amber-600' : card.accent === 'red' ? 'text-red-600' : 'text-slate-900 dark:text-slate-100'"
                >
                    {{ card.value }}
                </p>
            </div>
        </div>

        <ListPanel v-if="showImport" class="mb-4" :title="$t('assets.import_assets_from_csv')">
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
                Required columns: <span class="font-medium">{{ $t('assets.name') }}</span>, <span class="font-medium">{{ $t('assets.type') }}</span>.
                Optional: asset tag, status, serial number, contact email, organization, location, IP, MAC, hostname, manufacturer, model, vendor, purchase cost, purchased, warranty expires, notes.
            </p>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitImport">
                <FilterField :label="$t('assets.csv_file')" class="min-w-[16rem] flex-1" :error="importForm.errors.file">
                    <input type="file" accept=".csv,text/csv" required :class="inputClass" @change="onImportFileChange" />
                </FilterField>
                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    :disabled="importForm.processing"
                >{{ $t('assets.upload_and_import') }}</button>
            </form>
        </ListPanel>

        <ListPanel class="mb-4" :title="$t('assets.find_assets')">
            <form @submit.prevent="applyFilters">
                <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <FilterField :label="$t('assets.search')">
                        <input name="search" type="search" :value="filters.search || ''" :placeholder="$t('assets.tag_name_serial_ip')" :class="inputClass" />
                    </FilterField>
                    <FilterField :label="$t('assets.status')">
                        <select name="status" :value="filters.status || ''" :class="inputClass">
                            <option value="">{{ $t('assets.all_statuses') }}</option>
                            <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </FilterField>
                    <FilterField :label="$t('assets.type')">
                        <select name="asset_type_id" :value="filters.asset_type_id || ''" :class="inputClass">
                            <option value="">{{ $t('assets.all_types') }}</option>
                            <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </FilterField>
                    <FilterField :label="$t('assets.organization')">
                        <select name="organization_id" :value="filters.organization_id || ''" :class="inputClass">
                            <option value="">{{ $t('assets.all_organizations') }}</option>
                            <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
                        </select>
                    </FilterField>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input name="unassigned" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" :checked="filters.unassigned">
                        Unassigned only
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input name="warranty_expiring" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" :checked="filters.warranty_expiring">
                        Warranty expiring in 30 days
                    </label>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('assets.apply_filters') }}</button>
                </div>
            </form>
        </ListPanel>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.tag') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.assigned_to') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.ip') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.warranty') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('assets.location') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="asset in assets.data" :key="asset.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <td class="px-4 py-3 text-sm font-medium">
                        <Link :href="`/assets/${asset.id}`" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ asset.asset_tag }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">{{ asset.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ asset.type?.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ statusLabel(asset.status) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ asset.contact?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ asset.ip_address || '—' }}</td>
                    <td class="px-4 py-3 text-sm" :class="warrantyClass(asset)">
                        {{ asset.warranty_expires_at?.slice(0, 10) || '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ asset.location || '—' }}</td>
                </tr>
                <tr v-if="!assets.data?.length">
                    <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No assets found.</td>
                </tr>
            </tbody>
            <template #footer>
                <PaginationLinks
                    :links="assets.links"
                    :from="assets.from"
                    :to="assets.to"
                    :total="assets.total"
                />
            </template>
        </DataTable>
    </AgentLayout>
</template>
