<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';

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
    purchased_at: props.asset.purchased_at?.slice(0, 10) || '',
    warranty_expires_at: props.asset.warranty_expires_at?.slice(0, 10) || '',
    notes: props.asset.notes || '',
});

const statusLabel = (value) => props.meta.statuses.find((item) => item.value === value)?.label ?? value;

const submit = () => form.put(`/assets/${props.asset.id}`);
const destroyAsset = () => router.delete(`/assets/${props.asset.id}`);
</script>

<template>
    <Head :title="asset.asset_tag" />
    <AgentLayout>
        <div class="mb-4 flex items-center justify-between">
            <Link href="/assets" class="text-sm text-blue-600 hover:text-blue-700">← Back to assets</Link>
            <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="destroyAsset">Delete asset</button>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <p class="text-sm font-medium text-slate-500">{{ asset.asset_tag }}</p>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ asset.name }}</h1>
                        <p class="mt-1 text-sm text-slate-600">{{ asset.type?.name }} · {{ statusLabel(asset.status) }}</p>
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
                        <div class="grid gap-4 sm:grid-cols-2">
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
            </div>
        </div>
    </AgentLayout>
</template>
