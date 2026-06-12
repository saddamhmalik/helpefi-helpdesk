<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useAssetDeleteConfirm } from '../../composables/useAssetDeleteConfirm.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    asset: Object,
    meta: Object,
    contacts: Array,
    organizations: Array,
    parentOptions: Array,
});

const { t } = useI18n();

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
        return { label: t('assets.warranty_expired'), class: 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300' };
    }

    if (expires <= soon) {
        return { label: t('assets.warranty_expiring_soon'), class: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' };
    }

    return { label: t('assets.under_warranty'), class: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' };
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
            <Link href="/assets" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Back to assets</Link>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="ticketCreateUrl" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Report issue
                </Link>
                <AppDeleteAction :label="$t('assets.delete_asset')" @click="destroyAsset" />
            </div>
        </div>

        <AssetsNav />

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ asset.asset_tag }}</p>
                            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ asset.name }}</h1>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ asset.type?.name }} · {{ statusLabel(asset.status) }}</p>
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
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.type') }}</label>
                                <select v-model="form.asset_type_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                                    <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.status') }}</label>
                                <select v-model="form.status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                                    <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.name') }}</label>
                            <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.serial_number') }}</label>
                                <input v-model="form.serial_number" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.location') }}</label>
                                <input v-model="form.location" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.ip_address') }}</label>
                                <input v-model="form.ip_address" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.mac_address') }}</label>
                                <input v-model="form.mac_address" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.hostname') }}</label>
                                <input v-model="form.hostname" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.manufacturer') }}</label>
                                <input v-model="form.manufacturer" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.model') }}</label>
                                <input v-model="form.model" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.vendor') }}</label>
                                <input v-model="form.vendor" type="text" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.assigned_contact') }}</label>
                                <select v-model="form.contact_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                                    <option value="">{{ $t('assets.unassigned') }}</option>
                                    <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.organization') }}</label>
                                <select v-model="form.organization_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                                    <option value="">{{ $t('assets.none') }}</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.parent_asset') }}</label>
                            <select v-model="form.parent_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                                <option value="">{{ $t('assets.none') }}</option>
                                <option v-for="parent in parentOptions.filter((item) => item.id !== asset.id)" :key="parent.id" :value="parent.id">{{ parent.asset_tag }} — {{ parent.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.purchase_cost') }}</label>
                                <input v-model="form.purchase_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.purchased') }}</label>
                                <input v-model="form.purchased_at" type="date" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.warranty_expires') }}</label>
                                <input v-model="form.warranty_expires_at" type="date" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('assets.notes') }}</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('assets.save_changes') }}</button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div v-if="asset.discovery_source || asset.last_seen_at" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('assets.discovery') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div v-if="asset.discovery_source" class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('assets.source') }}</dt>
                            <dd class="text-slate-900 dark:text-slate-100">{{ asset.discovery_source }}</dd>
                        </div>
                        <div v-if="asset.last_seen_at" class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('assets.last_seen') }}</dt>
                            <dd class="text-slate-900 dark:text-slate-100">{{ asset.last_seen_at.slice(0, 16).replace('T', ' ') }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="asset.parent" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('assets.parent_asset') }}</h2>
                    <Link :href="`/assets/${asset.parent.id}`" class="mt-2 block text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                        {{ asset.parent.asset_tag }} — {{ asset.parent.name }}
                    </Link>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('assets.child_assets') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="child in asset.children" :key="child.id">
                            <Link :href="`/assets/${child.id}`" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ child.asset_tag }} — {{ child.name }}</Link>
                        </li>
                        <li v-if="!asset.children?.length" class="text-slate-500 dark:text-slate-400">{{ $t('assets.no_child_assets') }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('assets.linked_tickets') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="ticket in asset.tickets" :key="ticket.id">
                            <Link :href="`/tickets/${ticket.id}`" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ ticket.number }}</Link>
                            — {{ ticket.subject }}
                        </li>
                        <li v-if="!asset.tickets?.length" class="text-slate-500 dark:text-slate-400">{{ $t('assets.no_linked_tickets') }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('assets.assignment_history') }}</h2>
                    <ul class="mt-3 space-y-3 text-sm">
                        <li v-for="log in asset.assignment_logs" :key="log.id" class="border-b border-slate-100 dark:border-slate-800 pb-3 last:border-0 last:pb-0">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ assignmentActionLabel(log.action) }}</p>
                            <p class="text-slate-600 dark:text-slate-400">
                                <span v-if="log.contact">{{ log.contact.name }}</span>
                                <span v-else>{{ $t('assets.unassigned') }}</span>
                                <span v-if="log.organization"> · {{ log.organization.name }}</span>
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ log.created_at?.slice(0, 16).replace('T', ' ') }}
                                <span v-if="log.changed_by"> · {{ log.changed_by.name }}</span>
                            </p>
                        </li>
                        <li v-if="!asset.assignment_logs?.length" class="text-slate-500 dark:text-slate-400">{{ $t('assets.no_assignment_changes_yet') }}</li>
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
