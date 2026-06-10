<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import DataTable from '../../Components/DataTable.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    scan: Object,
    meta: Object,
});

const { t } = useI18n();

const isRunning = computed(() => ['pending', 'running'].includes(props.scan.status));

let pollTimer = null;

watch(isRunning, (running) => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    if (running) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['scan'], preserveScroll: true });
        }, 3000);
    }
}, { immediate: true });

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

const suggestedName = (device) => {
    if (device.hostname) {
        return device.hostname;
    }

    if (device.vendor && device.vendor !== 'Private / randomized MAC') {
        return `${device.vendor} (${device.ip_address})`;
    }

    return `Device ${device.ip_address}`;
};

const deviceNames = reactive({});

const syncDeviceNames = () => {
    props.scan.devices?.forEach((device) => {
        if (deviceNames[device.id] === undefined) {
            deviceNames[device.id] = suggestedName(device);
        }
    });
};

watch(() => props.scan.devices, syncDeviceNames, { immediate: true, deep: true });

const selected = useForm({
    device_ids: [],
    asset_type_id: props.meta.types[0]?.id ?? '',
    device_names: {},
});

const importableDevices = computed(() =>
    props.scan.devices?.filter((device) => ['new', 'matched'].includes(device.status)) ?? [],
);

const toggleDevice = (deviceId) => {
    if (selected.device_ids.includes(deviceId)) {
        selected.device_ids = selected.device_ids.filter((id) => id !== deviceId);
    } else {
        selected.device_ids = [...selected.device_ids, deviceId];
    }
};

const toggleAll = () => {
    if (selected.device_ids.length === importableDevices.value.length) {
        selected.device_ids = [];
    } else {
        selected.device_ids = importableDevices.value.map((device) => device.id);
    }
};

const importDevices = () => {
    selected.device_names = Object.fromEntries(
        selected.device_ids.map((id) => [id, deviceNames[id] || suggestedName(props.scan.devices.find((device) => device.id === id))]),
    );

    selected.post(`/assets/discovery/scans/${props.scan.id}/import`, {
        preserveScroll: true,
        onSuccess: () => selected.device_ids = [],
    });
};

const refresh = () => router.reload({ only: ['scan'], preserveScroll: true });

const statusLabel = (status) => {
    const labels = {
        new: 'New',
        matched: 'Matched',
        imported: 'Imported',
        skipped: 'Skipped',
    };

    return labels[status] ?? status;
};
</script>

<template>
    <Head :title="`Scan ${scan.subnet}`" />
    <AgentLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/assets/discovery" class="text-sm text-blue-600 hover:text-blue-700">← Back to discovery</Link>
            <button
                v-if="isRunning"
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                @click="refresh"
            >{{ $t('assets.refresh') }}</button>
        </div>

        <AssetsNav />

        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-mono text-2xl font-semibold text-slate-900">{{ scan.subnet }}</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ scan.devices_found }} devices found
                        <span v-if="scan.started_by"> · started by {{ scan.started_by.name }}</span>
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium capitalize text-slate-700">{{ scan.status }}</span>
            </div>
            <p v-if="scan.error_message" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ scan.error_message }}</p>
            <p v-else class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                {{ $t('assets.device_names_come_from_dns_bonjour_and_mac_vendor_lookup_edit_names_be') }}
            </p>
        </div>

        <form v-if="importableDevices.length" class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="importDevices">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('assets.import_as_type') }}</label>
                <select v-model="selected.asset_type_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
            </div>
            <button
                type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                :disabled="selected.processing || !selected.device_ids.length"
            >
                Import selected ({{ selected.device_ids.length }})
            </button>
        </form>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <input
                            v-if="importableDevices.length"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="selected.device_ids.length === importableDevices.length && importableDevices.length > 0"
                            @change="toggleAll"
                        >
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.ip') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.device_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.mac') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.vendor') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.matched_asset') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="device in scan.devices" :key="device.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <input
                            v-if="['new', 'matched'].includes(device.status)"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="selected.device_ids.includes(device.id)"
                            @change="toggleDevice(device.id)"
                        >
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-sm text-slate-900">{{ device.ip_address }}</td>
                    <td class="px-4 py-3 text-sm">
                        <input
                            v-if="['new', 'matched'].includes(device.status)"
                            v-model="deviceNames[device.id]"
                            type="text"
                            class="w-full min-w-[12rem] rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm"
                            :placeholder="suggestedName(device)"
                        >
                        <span v-else class="text-slate-700">{{ deviceNames[device.id] || suggestedName(device) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{{ device.mac_address || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ device.vendor || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ statusLabel(device.status) }}</td>
                    <td class="px-4 py-3 text-sm">
                        <Link
                            v-if="device.matched_asset"
                            :href="`/assets/${device.matched_asset.id}`"
                            class="text-blue-600 hover:text-blue-700"
                        >
                            {{ device.matched_asset.asset_tag }} — {{ device.matched_asset.name }}
                        </Link>
                        <Link
                            v-else-if="device.imported_asset"
                            :href="`/assets/${device.imported_asset.id}`"
                            class="text-blue-600 hover:text-blue-700"
                        >
                            {{ device.imported_asset.asset_tag }} — {{ device.imported_asset.name }}
                        </Link>
                        <span v-else class="text-slate-500">—</span>
                    </td>
                </tr>
                <tr v-if="!scan.devices?.length">
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">
                        <span v-if="isRunning">{{ $t('assets.scan_in_progress_ellipsis') }}</span>
                        <span v-else>{{ $t('assets.no_reachable_devices_found') }}</span>
                    </td>
                </tr>
            </tbody>
        </DataTable>
    </AgentLayout>
</template>
