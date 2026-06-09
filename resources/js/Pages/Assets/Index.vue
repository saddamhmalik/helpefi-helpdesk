<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';

const props = defineProps({
    assets: Object,
    meta: Object,
    filters: Object,
});

const inputClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const statusLabel = (value) => props.meta.statuses.find((item) => item.value === value)?.label ?? value;

const applyFilters = (event) => {
    router.get('/assets', {
        search: event.target.form.search.value || undefined,
        status: event.target.form.status.value || undefined,
        asset_type_id: event.target.form.asset_type_id.value || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Assets" />
    <AgentLayout>
        <PageHeader description="CMDB inventory with assignment and ticket linkage.">
            <template #actions>
                <Link href="/assets/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">New asset</Link>
            </template>
        </PageHeader>

        <ListPanel class="mb-4" title="Find assets">
            <form @submit.prevent="applyFilters">
                <div class="grid gap-4 md:grid-cols-3">
                    <FilterField label="Search">
                        <input name="search" type="search" :value="filters.search || ''" placeholder="Tag, name, serial..." :class="inputClass" />
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
                </div>
                <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Apply filters
                </button>
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
                    <td class="px-4 py-3 text-sm text-slate-600">{{ asset.location || '—' }}</td>
                </tr>
                <tr v-if="!assets.data?.length">
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No assets found.</td>
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
