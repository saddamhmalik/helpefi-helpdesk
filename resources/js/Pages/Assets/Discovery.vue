<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    scans: Object,
    meta: Object,
});

const { t } = useI18n();

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
    <Head :title="$t('assets.asset_discovery')" />
    <AgentLayout>
        <PageHeader :description="$t('assets.scan_your_local_subnet_to_find_devices_match_existing_assets_and_impor')" />

        <AssetsNav />

        <ListPanel class="mb-6" :title="$t('assets.start_a_network_scan')">
            <form class="space-y-2" @submit.prevent="submit">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="subnet">{{ $t('assets.subnet_or_ip') }}</label>
                        <input
                            id="subnet"
                            v-model="form.subnet"
                            type="text"
                            required
                            :placeholder="$t('assets.192_168_1_0_24_or_10_0_0_42')"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                        <p v-if="form.errors.subnet" class="mt-1 text-sm text-red-600">{{ form.errors.subnet }}</p>
                    </div>
                    <button
                        type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 sm:h-[38px]"
                        :disabled="form.processing"
                    >{{ $t('assets.start_scan') }}</button>
                </div>
                <p class="text-sm text-slate-500">
                    {{ $t('assets.scan_runs_in_the_background_a_24_subnet_scan_may_take_several_minutes') }}
                </p>
            </form>
        </ListPanel>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.subnet') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.devices_found') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.started_by') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.started') }}</th>
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
