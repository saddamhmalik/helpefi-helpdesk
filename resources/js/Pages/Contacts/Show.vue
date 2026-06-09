<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRichTextEditor from '../../Components/AppRichTextEditor.vue';
import CustomFields from '../../Components/CustomFields.vue';
import TicketMessageContent from '../../Components/TicketMessageContent.vue';
import { isEmptyRichText } from '../../composables/useRichText.js';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';

const props = defineProps({
    contact: Object,
    organizations: Array,
    tags: Array,
    customFieldDefinitions: Array,
});

const page = usePage();
const isAdmin = () => page.props.auth.user?.is_admin;

const form = useForm({
    name: props.contact.name,
    email: props.contact.email,
    phone: props.contact.phone,
    organization_id: props.contact.organization_id || '',
    tag_ids: props.contact.tags?.map((t) => t.id) ?? [],
    custom_fields: { ...(props.contact.custom_fields ?? {}) },
});

const noteForm = useForm({ body: '' });

const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const submit = () => form.put(`/contacts/${props.contact.id}`);
const addNote = () => noteForm.post(`/contacts/${props.contact.id}/notes`, { onSuccess: () => noteForm.reset() });

const removePortalAccess = () => {
    askConfirm({
        title: 'Revoke portal access',
        message: `Remove portal login for ${props.contact.name}? They can still email support, but cannot sign in.`,
        confirmLabel: 'Revoke access',
        variant: 'danger',
        action: () => router.delete(`/customers/accounts/${props.contact.portal_user.id}`),
    });
};
</script>

<template>
    <Head :title="contact.name" />
    <AgentLayout>
        <div class="mb-4">
            <Link href="/contacts" class="text-sm text-blue-600 hover:text-blue-700">← Back to customers</Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-semibold text-slate-900">{{ contact.name }}</h1>
                            <p v-if="contact.email" class="mt-1 text-sm text-slate-500">{{ contact.email }}</p>
                        </div>
                        <span
                            v-if="contact.portal_user"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/15"
                        >
                            Portal access
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
                        >
                            Guest only
                        </span>
                    </div>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                                <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                                <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Organization</label>
                                <select v-model="form.organization_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    <option value="">None</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Tags</label>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="tag in tags" :key="tag.id" class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-sm">
                                    <input v-model="form.tag_ids" type="checkbox" :value="tag.id" class="rounded" />
                                    {{ tag.name }}
                                </label>
                            </div>
                        </div>
                        <CustomFields
                            v-model="form.custom_fields"
                            :definitions="customFieldDefinitions"
                            :errors="form.errors"
                        />
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">Update</button>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Notes</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="addNote">
                        <AppRichTextEditor
                            v-model="noteForm.body"
                            form
                            placeholder="Add an internal note…"
                        />
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="noteForm.processing || isEmptyRichText(noteForm.body)">Add note</button>
                    </form>
                    <ul class="mt-4 space-y-3">
                        <li v-for="note in contact.notes" :key="note.id" class="rounded-lg border border-slate-100 p-3">
                            <TicketMessageContent :body="note.body" />
                            <p class="mt-1 text-xs text-slate-500">{{ note.user?.name }} · {{ new Date(note.created_at).toLocaleString() }}</p>
                        </li>
                        <li v-if="!contact.notes?.length" class="text-sm text-slate-500">No notes yet.</li>
                    </ul>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Portal access</h2>
                    <template v-if="contact.portal_user">
                        <p class="mt-2 text-sm text-slate-600">
                            This customer can sign in at the customer portal to view tickets, submit requests, and leave feedback.
                        </p>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Login email</dt>
                                <dd class="font-medium text-slate-800">{{ contact.portal_user.email }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Registered</dt>
                                <dd class="text-slate-800">{{ new Date(contact.portal_user.created_at).toLocaleDateString() }}</dd>
                            </div>
                        </dl>
                        <button
                            v-if="isAdmin()"
                            type="button"
                            class="mt-4 w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
                            @click="removePortalAccess"
                        >
                            Revoke portal access
                        </button>
                    </template>
                    <template v-else>
                        <p class="mt-2 text-sm text-slate-600">
                            This customer has no login. They reach support by email or the guest portal only.
                        </p>
                        <p class="mt-3 text-xs text-slate-500">
                            Portal accounts are created when a customer registers at <span class="font-medium">/portal/register</span>.
                        </p>
                    </template>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Activity</h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="activity in contact.activities" :key="activity.id" class="border-l-2 border-slate-200 pl-3">
                            <p class="text-sm text-slate-800">{{ activity.description }}</p>
                            <p class="text-xs text-slate-500">{{ activity.user?.name || 'System' }} · {{ new Date(activity.created_at).toLocaleString() }}</p>
                        </li>
                        <li v-if="!contact.activities?.length" class="text-sm text-slate-500">No activity yet.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Assigned assets</h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="asset in contact.assets" :key="asset.id">
                            <Link :href="`/assets/${asset.id}`" class="text-sm text-blue-600 hover:text-blue-700">{{ asset.asset_tag }} — {{ asset.name }}</Link>
                            <span class="ml-2 text-xs text-slate-500">{{ asset.type?.name }}</span>
                        </li>
                        <li v-if="!contact.assets?.length" class="text-sm text-slate-500">No assigned assets.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Tickets</h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="ticket in contact.tickets" :key="ticket.id">
                            <Link :href="`/tickets/${ticket.id}`" class="text-sm text-blue-600 hover:text-blue-700">{{ ticket.number }} — {{ ticket.subject }}</Link>
                            <span class="ml-2 text-xs text-slate-500">{{ ticket.status?.name }}</span>
                        </li>
                        <li v-if="!contact.tickets?.length" class="text-sm text-slate-500">No tickets yet.</li>
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
