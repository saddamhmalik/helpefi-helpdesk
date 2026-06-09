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

const props = defineProps({
    assets: Object,
    stats: Object,
    meta: Object,
    organizations: Array,
    filters: Object,
});

const inputClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
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

    return 'text-slate-600';
};

const statCards = computed(() => [
    { label: 'Total assets', value: props.stats?.total ?? 0 },
    { label: 'In use', value: props.stats?.in_use ?? 0 },
    { label: 'In stock', value: props.stats?.in_stock ?? 0 },
    { label: 'Unassigned', value: props.stats?.unassigned ?? 0 },
    { label: 'Warranty expiring', value: props.stats?.warranty_expiring ?? 0, accent: 'amber' },
    { label: 'Warranty expired', value: props.stats?.warranty_expired ?? 0, accent: 'red' },
]);
</script>

<template>
    <Head title="Assets" />
    <AgentLayout>
        <PageHeader description="CMDB inventory, warranty tracking, discovery, and ticket linkage.">
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="showImport = !showImport"
                >
                    Import CSV
                </button>
                <a
                    :href="exportUrl"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Export CSV
                </a>
                <Link href="/assets/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">New asset</Link>
            </template>
        </PageHeader>

        <AssetsNav />

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="card.accent === 'amber' ? 'text-amber-600' : card.accent === 'red' ? 'text-red-600' : 'text-slate-900'"
                >
                    {{ card.value }}
                </p>
            </div>
        </div>

        <ListPanel v-if="showImport" class="mb-4" title="Import assets from CSV">
            <p class="mb-3 text-sm text-slate-600">
                Required columns: <span class="font-medium">Name</span>, <span class="font-medium">Type</span>.
                Optional: asset tag, status, serial number, contact email, organization, location, IP, MAC, hostname, manufacturer, model, vendor, purchase cost, purchased, warranty expires, notes.
            </p>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitImport">
                <FilterField label="CSV file" class="min-w-[16rem] flex-1" :error="importForm.errors.file">
                    <input type="file" accept=".csv,text/csv" required :class="inputClass" @change="onImportFileChange" />
                </FilterField>
                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    :disabled="importForm.processing"
                >
                    Upload and import
                </button>
            </form>
        </ListPanel>

        <ListPanel class="mb-4" title="Find assets">
            <form @submit.prevent="applyFilters">
                <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <FilterField label="Search">
                        <input name="search" type="search" :value="filters.search || ''" placeholder="Tag, name, serial, IP..." :class="inputClass" />
                    </FilterField>
                    <FilterField label="Status">
                        <select name="status" :value="filters.status || ''" :class="inputClass">
                            <option value="">All statuses</option>
                            <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </FilterField>
                    <FilterField label="Type">
                        <select name="asset_type_id" :value="filters.asset_type_id || ''" :class="inputClass">
                            <option value="">All types</option>
                            <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </FilterField>
                    <FilterField label="Organization">
                        <select name="organization_id" :value="filters.organization_id || ''" :class="inputClass">
                            <option value="">All organizations</option>
                            <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
                        </select>
                    </FilterField>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input name="unassigned" type="checkbox" class="rounded border-slate-300" :checked="filters.unassigned">
                        Unassigned only
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input name="warranty_expiring" type="checkbox" class="rounded border-slate-300" :checked="filters.warranty_expiring">
                        Warranty expiring in 30 days
                    </label>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Apply filters
                    </button>
                </div>
            </form>
        </ListPanel>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tag</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned to</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">IP</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Warranty</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Location</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="asset in assets.data" :key="asset.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium">
                        <Link :href="`/assets/${asset.id}`" class="text-blue-600 hover:text-blue-700">{{ asset.asset_tag }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-900">{{ asset.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ asset.type?.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ statusLabel(asset.status) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ asset.contact?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ asset.ip_address || '—' }}</td>
                    <td class="px-4 py-3 text-sm" :class="warrantyClass(asset)">
                        {{ asset.warranty_expires_at?.slice(0, 10) || '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ asset.location || '—' }}</td>
                </tr>
                <tr v-if="!assets.data?.length">
                    <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">No assets found.</td>
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
