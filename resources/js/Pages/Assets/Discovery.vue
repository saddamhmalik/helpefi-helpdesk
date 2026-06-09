<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';

defineProps({
    scans: Object,
    meta: Object,
});

const form = useForm({
    subnet: '',
});

const submit = () => form.post('/assets/discovery/scans');

const statusLabel = (status) => {
    const labels = {
        pending: 'Pending',
        running: 'Running',
        completed: 'Completed',
        failed: 'Failed',
    };

    return labels[status] ?? status;
};

const statusClass = (status) => {
    const classes = {
        pending: 'bg-slate-100 text-slate-700',
        running: 'bg-blue-50 text-blue-700',
        completed: 'bg-emerald-50 text-emerald-700',
        failed: 'bg-red-50 text-red-700',
    };

    return classes[status] ?? 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="Asset discovery" />
    <AgentLayout>
        <PageHeader description="Scan your local subnet to find devices, match existing assets, and import new inventory." />

        <AssetsNav />

        <ListPanel class="mb-6" title="Start a network scan">
            <form class="space-y-2" @submit.prevent="submit">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="subnet">Subnet or IP</label>
                        <input
                            id="subnet"
                            v-model="form.subnet"
                            type="text"
                            required
                            placeholder="192.168.1.0/24 or 10.0.0.42"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                        <p v-if="form.errors.subnet" class="mt-1 text-sm text-red-600">{{ form.errors.subnet }}</p>
                    </div>
                    <button
                        type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 sm:h-[38px]"
                        :disabled="form.processing"
                    >
                        Start scan
                    </button>
                </div>
                <p class="text-sm text-slate-500">
                    Scan runs in the background. A /24 subnet scan may take several minutes.
                </p>
            </form>
        </ListPanel>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Subnet</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Devices found</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Started by</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Started</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="scan in scans.data" :key="scan.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium">
                        <Link :href="`/assets/discovery/scans/${scan.id}`" class="text-blue-600 hover:text-blue-700">{{ scan.subnet }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(scan.status)">
                            {{ statusLabel(scan.status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ scan.devices_found }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ scan.started_by?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ scan.started_at?.slice(0, 16).replace('T', ' ') || scan.created_at?.slice(0, 16).replace('T', ' ') }}</td>
                </tr>
                <tr v-if="!scans.data?.length">
                    <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No discovery scans yet.</td>
                </tr>
            </tbody>
            <template #footer>
                <PaginationLinks
                    :links="scans.links"
                    :from="scans.from"
                    :to="scans.to"
                    :total="scans.total"
                />
            </template>
        </DataTable>
    </AgentLayout>
</template>
