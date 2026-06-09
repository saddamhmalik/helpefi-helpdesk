<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppChipSelect from '../../Components/AppChipSelect.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    webhooks: Array,
    meta: Object,
    connections: Array,
});

const page = usePage();
const showForm = ref(false);
const editingWebhook = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const revealedSecret = computed(() => page.props.flash?.webhook_secret ?? null);
const revealedIntegrationSecret = computed(() => page.props.flash?.integration_secret ?? null);

const { activeSection } = useSettingsSection({
    defaultSection: 'webhooks',
    sections: ['webhooks', 'slack', 'jira', 'linear'],
});

const connection = (provider) => props.connections.find((item) => item.provider === provider) ?? {};

const webhookEventOptions = computed(() => (props.meta.events ?? []).map((event) => ({
    value: event.value,
    label: event.label,
})));

const slackEventOptions = computed(() => (props.meta.slack_events ?? []).map((event) => ({
    value: event.value,
    label: event.label,
})));

const blankWebhook = () => ({
    name: '',
    url: '',
    events: [props.meta.events?.[0]?.value ?? 'ticket.created'],
    is_active: true,
});

const form = useForm(blankWebhook());

const slackForm = useForm({
    webhook_url: connection('slack').config?.webhook_url ?? '',
    channel: connection('slack').config?.channel ?? '',
    events: connection('slack').events ?? [],
    is_active: connection('slack').is_active ?? false,
});

const jiraForm = useForm({
    site_url: connection('jira').config?.site_url ?? '',
    email: connection('jira').config?.email ?? '',
    api_token: '',
    project_key: connection('jira').config?.project_key ?? '',
    issue_type: connection('jira').config?.issue_type ?? 'Task',
    done_transition: connection('jira').config?.done_transition ?? 'Done',
    reopen_transition: connection('jira').config?.reopen_transition ?? 'To Do',
    is_active: connection('jira').is_active ?? false,
});

const linearForm = useForm({
    api_key: '',
    team_id: connection('linear').config?.team_id ?? '',
    done_state: connection('linear').config?.done_state ?? 'Done',
    open_state: connection('linear').config?.open_state ?? 'Todo',
    is_active: connection('linear').is_active ?? false,
});

const eventLabel = (value) => props.meta.events?.find((item) => item.value === value)?.label ?? value;

const closeForm = () => {
    showForm.value = false;
};

const openCreate = () => {
    editingWebhook.value = null;
    form.defaults(blankWebhook());
    form.reset();
    showForm.value = true;
};

const openEdit = (webhook) => {
    editingWebhook.value = webhook;
    form.defaults({
        name: webhook.name,
        url: webhook.url,
        events: [...webhook.events],
        is_active: webhook.is_active,
    });
    form.reset();
    showForm.value = true;
};

const save = () => {
    if (editingWebhook.value) {
        form.put(`/settings/integrations/webhooks/${editingWebhook.value.id}`, {
            onSuccess: closeForm,
        });
    } else {
        form.post('/settings/integrations/webhooks', {
            onSuccess: () => {
                form.reset();
                closeForm();
            },
        });
    }
};

const destroyWebhook = (webhook) => {
    askConfirm({
        title: 'Delete webhook',
        message: `Remove "${webhook.name}"? Deliveries will stop immediately.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/integrations/webhooks/${webhook.id}`, { preserveScroll: true }),
    });
};

const testWebhook = (webhookId) => {
    router.post(`/settings/integrations/webhooks/${webhookId}/test`, {}, { preserveScroll: true });
};

const regenerateSecret = (webhookId) => {
    router.post(`/settings/integrations/webhooks/${webhookId}/regenerate-secret`, {}, { preserveScroll: true });
};

const saveSlack = () => {
    slackForm.put('/settings/integrations/slack', { preserveScroll: true });
};

const saveJira = () => {
    jiraForm.put('/settings/integrations/jira', { preserveScroll: true });
};

const saveLinear = () => {
    linearForm.put('/settings/integrations/linear', { preserveScroll: true });
};

const testSlack = () => {
    router.post('/settings/integrations/slack/test', {}, { preserveScroll: true });
};

const formatTime = (value) => value ? new Date(value).toLocaleString() : 'Never';
const inputClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <SettingsLayout title="Integrations" description="Webhooks, Slack notifications, and Jira/Linear issue sync.">
        <template #actions>
            <button
                v-if="activeSection === 'webhooks'"
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                @click="openCreate"
            >
                Add webhook
            </button>
        </template>

        <div v-if="revealedSecret" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">Webhook signing secret (copy now)</p>
            <p class="mt-1 break-all font-mono text-xs">{{ revealedSecret }}</p>
        </div>

        <div v-if="revealedIntegrationSecret" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">Inbound webhook secret (copy now)</p>
            <p class="mt-1 break-all font-mono text-xs">{{ revealedIntegrationSecret }}</p>
        </div>

        <div v-show="activeSection === 'webhooks'" class="space-y-4">
                    <div v-for="webhook in webhooks" :key="webhook.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-slate-300">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-semibold text-slate-900">{{ webhook.name }}</h2>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="webhook.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                        {{ webhook.is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ webhook.url }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Last delivery: {{ formatTime(webhook.last_delivered_at) }}
                                    <span v-if="webhook.last_status_code"> · HTTP {{ webhook.last_status_code }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="testWebhook(webhook.id)">Send test</button>
                                <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="regenerateSecret(webhook.id)">New secret</button>
                                <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="openEdit(webhook)">Edit</button>
                                <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 transition hover:bg-red-50" @click="destroyWebhook(webhook)">Delete</button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="event in webhook.events" :key="event" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700">
                                {{ eventLabel(event) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!webhooks.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                        No webhooks configured yet.
                    </div>
                </div>

        <form v-show="activeSection === 'slack'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveSlack">
                    <h2 class="text-lg font-medium text-slate-900">Slack notifications</h2>
                    <p class="mt-1 text-sm text-slate-500">Post ticket events to a Slack channel using an incoming webhook.</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Incoming webhook URL</label>
                            <input v-model="slackForm.webhook_url" type="url" :class="inputClass" placeholder="https://hooks.slack.com/services/..." />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Channel override</label>
                            <input v-model="slackForm.channel" type="text" :class="inputClass" placeholder="#support (optional)" />
                        </div>
                        <AppChipSelect v-model="slackForm.events" label="Events" :options="slackEventOptions" />
                        <AppToggle v-model="slackForm.is_active" label="Active" />
                        <p v-if="connection('slack').last_delivered_at" class="text-xs text-slate-500">
                            Last delivery: {{ formatTime(connection('slack').last_delivered_at) }}
                        </p>
                        <p v-if="connection('slack').last_error" class="text-xs text-red-600">{{ connection('slack').last_error }}</p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="slackForm.processing">Save</button>
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="testSlack">Send test</button>
                    </div>
                </form>

        <form v-show="activeSection === 'jira'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveJira">
                    <h2 class="text-lg font-medium text-slate-900">Jira</h2>
                    <p class="mt-1 text-sm text-slate-500">Create and link Jira issues from tickets. Status changes sync both ways.</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Site URL</label>
                            <input v-model="jiraForm.site_url" type="url" :class="inputClass" placeholder="https://yourcompany.atlassian.net" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                            <input v-model="jiraForm.email" type="email" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">API token</label>
                            <input v-model="jiraForm.api_token" type="password" :class="inputClass" :placeholder="connection('jira').config?.has_api_token ? 'Saved — leave blank to keep' : ''" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Project key</label>
                            <input v-model="jiraForm.project_key" type="text" :class="inputClass" placeholder="SUP" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Issue type</label>
                            <input v-model="jiraForm.issue_type" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Done transition</label>
                            <input v-model="jiraForm.done_transition" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Reopen transition</label>
                            <input v-model="jiraForm.reopen_transition" type="text" :class="inputClass" />
                        </div>
                        <div class="md:col-span-2">
                            <AppToggle v-model="jiraForm.is_active" label="Active" />
                        </div>
                        <div class="md:col-span-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                            <p class="font-medium text-slate-700">Inbound webhook URL</p>
                            <p class="mt-1 break-all font-mono">{{ meta.inbound_urls?.jira }}</p>
                            <p class="mt-2">Send header <span class="font-mono">X-Integration-Secret</span> with the secret shown after save.</p>
                        </div>
                    </div>

                    <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="jiraForm.processing">Save</button>
                </form>

        <form v-show="activeSection === 'linear'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveLinear">
                    <h2 class="text-lg font-medium text-slate-900">Linear</h2>
                    <p class="mt-1 text-sm text-slate-500">Create and link Linear issues from tickets. Status changes sync both ways.</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">API key</label>
                            <input v-model="linearForm.api_key" type="password" :class="inputClass" :placeholder="connection('linear').config?.has_api_key ? 'Saved — leave blank to keep' : ''" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Team ID</label>
                            <input v-model="linearForm.team_id" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Done state</label>
                            <input v-model="linearForm.done_state" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Open state</label>
                            <input v-model="linearForm.open_state" type="text" :class="inputClass" />
                        </div>
                        <div class="md:col-span-2">
                            <AppToggle v-model="linearForm.is_active" label="Active" />
                        </div>
                        <div class="md:col-span-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                            <p class="font-medium text-slate-700">Inbound webhook URL</p>
                            <p class="mt-1 break-all font-mono">{{ meta.inbound_urls?.linear }}</p>
                            <p class="mt-2">Linear sends <span class="font-mono">Linear-Signature</span> HMAC signed with the secret shown after save.</p>
                        </div>
                    </div>

                    <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="linearForm.processing">Save</button>
                </form>

        <AppModal
            :open="showForm"
            :title="editingWebhook ? 'Edit webhook' : 'Add webhook'"
            description="Receive signed POST requests when ticket events occur."
            size="md"
            @close="closeForm"
        >
            <form id="webhook-form" class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">URL</label>
                    <input v-model="form.url" type="url" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="https://example.com/webhooks/helpdesk" />
                </div>
                <AppChipSelect v-model="form.events" label="Events" :options="webhookEventOptions" />
                <p v-if="!form.events.length" class="text-xs text-amber-600">Select at least one event.</p>
                <AppToggle v-model="form.is_active" label="Active" />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeForm">Cancel</button>
                    <button type="submit" form="webhook-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing || !form.events.length">Save</button>
                </div>
            </template>
        </AppModal>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </SettingsLayout>
</template>
