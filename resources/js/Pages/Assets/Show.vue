<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import { useAssetDeleteConfirm } from '../../composables/useAssetDeleteConfirm.js';

const props = defineProps({
    asset: Object,
    meta: Object,
    contacts: Array,
    organizations: Array,
    parentOptions: Array,
});

const form = useForm({
    asset_type_id: props.asset.asset_type_id,
    parent_id: props.asset.parent_id || '',
    name: props.asset.name,
    serial_number: props.asset.serial_number || '',
    status: props.asset.status,
    contact_id: props.asset.contact_id || '',
    organization_id: props.asset.organization_id || '',
    location: props.asset.location || '',
    ip_address: props.asset.ip_address || '',
    mac_address: props.asset.mac_address || '',
    hostname: props.asset.hostname || '',
    manufacturer: props.asset.manufacturer || '',
    model: props.asset.model || '',
    vendor: props.asset.vendor || '',
    purchase_cost: props.asset.purchase_cost || '',
    purchased_at: props.asset.purchased_at?.slice(0, 10) || '',
    warranty_expires_at: props.asset.warranty_expires_at?.slice(0, 10) || '',
    notes: props.asset.notes || '',
});

const statusLabel = (value) => props.meta.statuses.find((item) => item.value === value)?.label ?? value;

const warrantyStatus = computed(() => {
    if (!props.asset.warranty_expires_at) {
        return null;
    }

    const expires = new Date(props.asset.warranty_expires_at);
    const now = new Date();
    const soon = new Date();
    soon.setDate(soon.getDate() + 30);

    if (expires < now) {
        return { label: 'Warranty expired', class: 'bg-red-50 text-red-700' };
    }

    if (expires <= soon) {
        return { label: 'Warranty expiring soon', class: 'bg-amber-50 text-amber-700' };
    }

    return { label: 'Under warranty', class: 'bg-emerald-50 text-emerald-700' };
});

const ticketCreateUrl = computed(() => {
    const params = new URLSearchParams({
        subject: `Issue with ${props.asset.asset_tag}`,
    });

    if (props.asset.contact_id) {
        params.set('contact_id', props.asset.contact_id);
    }

    return `/tickets/create?${params.toString()}`;
});

const assignmentActionLabel = (action) => {
    const labels = {
        assigned: 'Assigned',
        unassigned: 'Unassigned',
        organization_changed: 'Organization changed',
    };

    return labels[action] ?? action;
};

const { state: confirm, close: closeConfirm, confirm: onConfirm, confirmDelete } = useAssetDeleteConfirm();

const submit = () => form.put(`/assets/${props.asset.id}`);

const destroyAsset = () => {
    confirmDelete(props.asset, () => router.delete(`/assets/${props.asset.id}`));
};
</script>

<template>
    <Head :title="asset.asset_tag" />
    <AgentLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/assets" class="text-sm text-blue-600 hover:text-blue-700">← Back to assets</Link>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="ticketCreateUrl" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Report issue
                </Link>
                <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="destroyAsset">Delete asset</button>
            </div>
        </div>

        <AssetsNav />

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ asset.asset_tag }}</p>
                            <h1 class="text-2xl font-semibold text-slate-900">{{ asset.name }}</h1>
                            <p class="mt-1 text-sm text-slate-600">{{ asset.type?.name }} · {{ statusLabel(asset.status) }}</p>
                        </div>
                        <span
                            v-if="warrantyStatus"
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="warrantyStatus.class"
                        >
                            {{ warrantyStatus.label }}
                        </span>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                                <select v-model="form.asset_type_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                                <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                            <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Serial number</label>
                                <input v-model="form.serial_number" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
                                <input v-model="form.location" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">IP address</label>
                                <input v-model="form.ip_address" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">MAC address</label>
                                <input v-model="form.mac_address" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Hostname</label>
                                <input v-model="form.hostname" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Manufacturer</label>
                                <input v-model="form.manufacturer" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Model</label>
                                <input v-model="form.model" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Vendor</label>
                                <input v-model="form.vendor" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Assigned contact</label>
                                <select v-model="form.contact_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Unassigned</option>
                                    <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Organization</label>
                                <select v-model="form.organization_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Parent asset</label>
                            <select v-model="form.parent_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">None</option>
                                <option v-for="parent in parentOptions.filter((item) => item.id !== asset.id)" :key="parent.id" :value="parent.id">{{ parent.asset_tag }} — {{ parent.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Purchase cost</label>
                                <input v-model="form.purchase_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Purchased</label>
                                <input v-model="form.purchased_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Warranty expires</label>
                                <input v-model="form.warranty_expires_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">Save changes</button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div v-if="asset.discovery_source || asset.last_seen_at" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Discovery</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div v-if="asset.discovery_source" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Source</dt>
                            <dd class="text-slate-900">{{ asset.discovery_source }}</dd>
                        </div>
                        <div v-if="asset.last_seen_at" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Last seen</dt>
                            <dd class="text-slate-900">{{ asset.last_seen_at.slice(0, 16).replace('T', ' ') }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="asset.parent" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Parent asset</h2>
                    <Link :href="`/assets/${asset.parent.id}`" class="mt-2 block text-sm text-blue-600 hover:text-blue-700">
                        {{ asset.parent.asset_tag }} — {{ asset.parent.name }}
                    </Link>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Child assets</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="child in asset.children" :key="child.id">
                            <Link :href="`/assets/${child.id}`" class="text-blue-600 hover:text-blue-700">{{ child.asset_tag }} — {{ child.name }}</Link>
                        </li>
                        <li v-if="!asset.children?.length" class="text-slate-500">No child assets.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Linked tickets</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="ticket in asset.tickets" :key="ticket.id">
                            <Link :href="`/tickets/${ticket.id}`" class="text-blue-600 hover:text-blue-700">{{ ticket.number }}</Link>
                            — {{ ticket.subject }}
                        </li>
                        <li v-if="!asset.tickets?.length" class="text-slate-500">No linked tickets.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Assignment history</h2>
                    <ul class="mt-3 space-y-3 text-sm">
                        <li v-for="log in asset.assignment_logs" :key="log.id" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <p class="font-medium text-slate-900">{{ assignmentActionLabel(log.action) }}</p>
                            <p class="text-slate-600">
                                <span v-if="log.contact">{{ log.contact.name }}</span>
                                <span v-else>Unassigned</span>
                                <span v-if="log.organization"> · {{ log.organization.name }}</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ log.created_at?.slice(0, 16).replace('T', ' ') }}
                                <span v-if="log.changed_by"> · {{ log.changed_by.name }}</span>
                            </p>
                        </li>
                        <li v-if="!asset.assignment_logs?.length" class="text-slate-500">No assignment changes yet.</li>
                    </ul>
                </div>
            </div>
        </div>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </AgentLayout>
</template>
