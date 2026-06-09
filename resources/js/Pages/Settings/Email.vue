<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppToggle from '../../Components/AppToggle.vue';
import EmailInboxCard from '../../Components/Email/EmailInboxCard.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    inboxes: Array,
    outbound: Object,
    mailboxProviders: Object,
    oauthProviders: Object,
    brands: Array,
    departments: Array,
    teams: Array,
    emailAdvanced: Object,
});

const { activeSection } = useSettingsSection({
    defaultSection: 'incoming',
    sections: ['incoming', 'outgoing', 'advanced'],
});

const showAddInbox = ref(false);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const smtpProvider = ref('gmail');
const inboxTestPassword = ref('');

const defaultBrandId = props.brands?.[0]?.id ?? null;

const inboxForm = useForm({
    name: '',
    address: '',
    brand_id: defaultBrandId,
    inbound_method: 'webhook',
    is_active: true,
});

const defaultInboxId = props.outbound.email_inbox_id
    ?? props.outbound.inbox_smtp_options?.[0]?.inbox_id
    ?? props.inboxes?.[0]?.id
    ?? null;

const outboundForm = useForm({
    enabled: props.outbound.enabled,
    reply_enabled: props.outbound.reply_enabled,
    delivery_mode: props.outbound.delivery_mode ?? 'sync',
    queue_connection: props.outbound.queue_connection ?? 'sync',
    use_inbox_smtp: props.outbound.use_inbox_smtp ?? false,
    email_inbox_id: defaultInboxId,
    driver: props.outbound.driver,
    from_address: props.outbound.from_address ?? '',
    from_name: props.outbound.from_name ?? '',
    reply_to_address: props.outbound.reply_to_address ?? '',
    automatic_bcc: props.outbound.automatic_bcc ?? '',
    use_agent_name_in_from: props.outbound.use_agent_name_in_from ?? false,
    host: props.outbound.host ?? '',
    port: props.outbound.port ?? 587,
    encryption: props.outbound.encryption ?? 'tls',
    username: props.outbound.username ?? '',
    password: '',
});

const advancedForm = useForm({
    email_allow_agent_initiated: props.emailAdvanced.email_allow_agent_initiated ?? false,
    email_use_agent_name_in_from: props.emailAdvanced.email_use_agent_name_in_from ?? false,
    email_automatic_bcc: props.emailAdvanced.email_automatic_bcc ?? '',
    email_reply_to_address: props.emailAdvanced.email_reply_to_address ?? '',
    email_use_reply_to_as_requester: props.emailAdvanced.email_use_reply_to_as_requester ?? false,
    email_use_original_sender_for_forwarded: props.emailAdvanced.email_use_original_sender_for_forwarded ?? true,
    email_flexible_recipients: props.emailAdvanced.email_flexible_recipients ?? true,
    email_ignore_ticket_id_threading: props.emailAdvanced.email_ignore_ticket_id_threading ?? false,
    email_create_ticket_on_subject_change: props.emailAdvanced.email_create_ticket_on_subject_change ?? false,
    email_detect_auto_replies: props.emailAdvanced.email_detect_auto_replies ?? true,
    auto_first_response_enabled: props.emailAdvanced.auto_first_response_enabled ?? false,
    auto_first_response_body: props.emailAdvanced.auto_first_response_body ?? '',
    email_blocklist: [...(props.emailAdvanced.email_blocklist ?? [])],
});

const blocklistText = computed({
    get: () => advancedForm.email_blocklist.join('\n'),
    set: (value) => {
        advancedForm.email_blocklist = String(value)
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean);
    },
});

const testForm = useForm({ to: '' });
const inboxTestForm = useForm({
    email_inbox_id: defaultInboxId,
    to: '',
    password: '',
});

const inboxSmtpOptions = computed(() => props.outbound.inbox_smtp_options ?? []);

const selectedInboxSmtp = computed(() =>
    inboxSmtpOptions.value.find((option) => option.inbox_id === Number(outboundForm.email_inbox_id)) ?? null,
);

watch(() => outboundForm.email_inbox_id, (value) => {
    inboxTestForm.email_inbox_id = value;
});

onMounted(() => {
    if (outboundForm.use_inbox_smtp && !outboundForm.email_inbox_id && defaultInboxId) {
        outboundForm.email_inbox_id = defaultInboxId;
    }

    if (!outboundForm.use_inbox_smtp && outboundForm.driver === 'smtp') {
        syncSmtpProviderFromHost();
        if (!outboundForm.host || outboundForm.host === 'gmail.com') {
            applySmtpProvider();
        }
    }
});

const addInbox = () => {
    inboxForm.post('/settings/email/inboxes', {
        preserveScroll: true,
        onSuccess: () => {
            inboxForm.reset();
            inboxForm.brand_id = defaultBrandId;
            inboxForm.inbound_method = 'webhook';
            closeAddInbox();
        },
    });
};

const removeInbox = (inbox) => {
    askConfirm({
        title: 'Remove inbox',
        message: `Remove ${inbox.address}? Existing tickets are kept but new mail will stop syncing.`,
        confirmLabel: 'Remove',
        action: () => router.delete(`/settings/email/inboxes/${inbox.id}`, { preserveScroll: true }),
    });
};

const openAddInbox = () => {
    inboxForm.reset();
    inboxForm.brand_id = defaultBrandId;
    inboxForm.inbound_method = 'webhook';
    showAddInbox.value = true;
};

const closeAddInbox = () => {
    showAddInbox.value = false;
};

const saveOutbound = () => {
    outboundForm.put('/settings/email/outbound', { preserveScroll: true });
};

const saveAdvanced = () => {
    advancedForm.put('/settings/email/advanced', { preserveScroll: true });
};

const sendTest = () => {
    const { password, ...settingsWithoutPassword } = outboundForm.data();

    testForm
        .transform((data) => ({
            ...settingsWithoutPassword,
            to: data.to,
        }))
        .post('/settings/email/outbound/test', { preserveScroll: true });
};

const sendInboxSmtpTest = () => {
    inboxTestForm.email_inbox_id = outboundForm.email_inbox_id;
    inboxTestForm.password = inboxTestPassword.value;
    inboxTestForm.post('/settings/email/outbound/test-inbox', {
        preserveScroll: true,
        onSuccess: () => {
            inboxTestPassword.value = '';
        },
    });
};

const smtpProviderOptions = () => Object.entries(props.outbound.smtp_providers ?? {}).map(([key, provider]) => ({
    key,
    ...provider,
}));

const applySmtpProvider = () => {
    const provider = props.outbound.smtp_providers?.[smtpProvider.value];
    if (!provider?.host) return;
    outboundForm.host = provider.host;
    outboundForm.port = provider.port ?? 587;
    outboundForm.encryption = provider.encryption ?? 'tls';
};

const syncSmtpProviderFromHost = () => {
    const host = outboundForm.host?.toLowerCase();
    const fromAddress = outboundForm.from_address?.toLowerCase() ?? '';

    if (host?.includes('gmail') || fromAddress.endsWith('@gmail.com')) {
        smtpProvider.value = 'gmail';
        return;
    }

    if (host?.includes('office365') || host?.includes('outlook') || fromAddress.endsWith('@outlook.com') || fromAddress.endsWith('@hotmail.com')) {
        smtpProvider.value = 'outlook';
        return;
    }

    const match = Object.entries(props.outbound.smtp_providers ?? {}).find(([, provider]) => provider.host === host);
    if (match) {
        smtpProvider.value = match[0];
    }
};

const onUseInboxSmtpChange = () => {
    if (outboundForm.use_inbox_smtp) {
        outboundForm.driver = 'smtp';
        if (!outboundForm.email_inbox_id && defaultInboxId) {
            outboundForm.email_inbox_id = defaultInboxId;
        }
    }
};
</script>

<template>
    <SettingsLayout
        title="Email settings"
        description="Connect support inboxes, configure SMTP delivery, and manage inbound email policies."
    >
        <div v-show="activeSection === 'incoming'">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Incoming email</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Receive tickets via webhook forwarding, IMAP/POP3 polling, or OAuth mailboxes.
                        </p>
                    </div>
                    <button type="button" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openAddInbox">
                        Add inbox
                    </button>
                </div>

                <div class="px-6 py-5">
                    <div v-if="!inboxes?.length" class="rounded-xl border border-dashed border-slate-200 py-12 text-center">
                        <p class="text-sm font-medium text-slate-700">No inboxes yet</p>
                        <p class="mt-1 text-sm text-slate-500">Add your first support email address to start receiving tickets.</p>
                    </div>

                    <div class="space-y-6">
                        <EmailInboxCard
                            v-for="inbox in inboxes"
                            :key="inbox.id"
                            :inbox="inbox"
                            :brands="brands"
                            :departments="departments"
                            :teams="teams"
                            :mailbox-providers="mailboxProviders"
                            :oauth-providers="oauthProviders"
                            @remove="removeInbox"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-show="activeSection === 'outgoing'">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">Outgoing email (SMTP)</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        When an agent replies on a ticket, we can email the customer from your support address.
                    </p>
                </div>

                <form class="space-y-6 px-6 py-5" @submit.prevent="saveOutbound">
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input v-model="outboundForm.enabled" type="checkbox" class="mt-0.5 rounded border-slate-300 text-blue-600" />
                            <span>
                                <span class="font-medium">Enable outgoing email</span>
                                <span class="mt-0.5 block text-xs text-slate-500">Required before any emails can be sent from the helpdesk.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input v-model="outboundForm.reply_enabled" type="checkbox" class="mt-0.5 rounded border-slate-300 text-blue-600" :disabled="!outboundForm.enabled" />
                            <span>
                                <span class="font-medium">Email customers when agents reply</span>
                                <span class="mt-0.5 block text-xs text-slate-500">Sends the agent's message to the ticket contact automatically.</span>
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Delivery mode</label>
                            <select v-model="outboundForm.delivery_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="!outboundForm.enabled">
                                <option value="sync">Send immediately (sync)</option>
                                <option value="queue">Send via queue (background)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Queue backend</label>
                            <select v-model="outboundForm.queue_connection" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="option in (outbound.queue_options ?? [])" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Reply-To address</label>
                            <input v-model="outboundForm.reply_to_address" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="replies@company.com" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Automatic BCC</label>
                            <input v-model="outboundForm.automatic_bcc" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="archive@company.com" />
                        </div>
                    </div>

                    <AppToggle
                        v-model="outboundForm.use_agent_name_in_from"
                        label="Use agent name in From header"
                        description="Shows the replying agent's name alongside the support address."
                    />

                    <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                        <label class="flex items-start gap-3 text-sm text-slate-800">
                            <input
                                v-model="outboundForm.use_inbox_smtp"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-blue-600"
                                :disabled="!outboundForm.enabled || !inboxSmtpOptions.length"
                                @change="onUseInboxSmtpChange"
                            />
                            <span>
                                <span class="font-medium">Use same address as inbound inbox</span>
                                <span class="mt-0.5 block text-xs text-slate-600">Replies are sent from the same support address customers emailed.</span>
                            </span>
                        </label>

                        <div v-if="outboundForm.use_inbox_smtp" class="mt-4 space-y-4 border-t border-blue-100 pt-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Inbound inbox</label>
                                <select v-model="outboundForm.email_inbox_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="option in inboxSmtpOptions" :key="option.inbox_id" :value="option.inbox_id">
                                        {{ option.name }} — {{ option.address }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="selectedInboxSmtp" class="rounded-lg border border-blue-200 bg-white p-4 text-sm text-slate-700">
                                <p><span class="font-medium">Send as:</span> {{ selectedInboxSmtp.address }}</p>
                                <p class="mt-1"><span class="font-medium">SMTP:</span> {{ selectedInboxSmtp.host }}:{{ selectedInboxSmtp.port }} ({{ selectedInboxSmtp.encryption?.toUpperCase() }})</p>
                            </div>

                            <div class="flex flex-wrap items-end gap-3 border-t border-blue-100 pt-4">
                                <div class="min-w-[16rem] flex-1">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Test inbox SMTP</label>
                                    <input v-model="inboxTestForm.to" type="email" placeholder="you@example.com" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-50"
                                    :disabled="inboxTestForm.processing || !outboundForm.email_inbox_id"
                                    @click="sendInboxSmtpTest"
                                >
                                    Test inbox SMTP
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!outboundForm.use_inbox_smtp" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Driver</label>
                                <select v-model="outboundForm.driver" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="smtp">SMTP (real email)</option>
                                    <option value="log">Log only (testing)</option>
                                </select>
                            </div>
                            <div v-if="outboundForm.driver === 'smtp'">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Provider preset</label>
                                <div class="flex gap-2">
                                    <select v-model="smtpProvider" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option v-for="provider in smtpProviderOptions()" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                                    </select>
                                    <button type="button" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="applySmtpProvider">Apply</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">From name</label>
                                <input v-model="outboundForm.from_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">From address</label>
                                <input v-model="outboundForm.from_address" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <template v-if="outboundForm.driver === 'smtp'">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">SMTP host</label>
                                    <input v-model="outboundForm.host" type="text" placeholder="smtp.gmail.com" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Port</label>
                                    <input v-model.number="outboundForm.port" type="number" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Encryption</label>
                                    <select v-model="outboundForm.encryption" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="tls">TLS (recommended)</option>
                                        <option value="ssl">SSL</option>
                                        <option value="null">None</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                                    <input v-model="outboundForm.username" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                                    <input v-model="outboundForm.password" type="password" :placeholder="outbound.has_password ? 'Leave blank to keep current' : ''" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="outboundForm.processing">
                        Save outgoing settings
                    </button>
                </form>

                <form class="flex flex-wrap items-end gap-3 border-t border-slate-100 px-6 py-5" @submit.prevent="sendTest">
                    <div class="min-w-[16rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Send a test email</label>
                        <input v-model="testForm.to" type="email" required placeholder="you@example.com" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="testForm.processing">
                        Send test
                    </button>
                </form>
            </div>
        </div>

        <div v-show="activeSection === 'advanced'">
            <form class="max-w-3xl space-y-6" @submit.prevent="saveAdvanced">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Sender &amp; delivery policies</h2>
                    <p class="mt-1 text-sm text-slate-500">Global defaults for outbound email behavior across all channels.</p>

                    <div class="mt-6 space-y-4">
                        <AppToggle v-model="advancedForm.email_allow_agent_initiated" label="Allow agent-initiated outbound email" />
                        <AppToggle v-model="advancedForm.email_use_agent_name_in_from" label="Use agent name in From header (global)" />
                        <AppToggle v-model="advancedForm.email_use_reply_to_as_requester" label="Use Reply-To as requester address" />
                        <AppToggle v-model="advancedForm.email_flexible_recipients" label="Allow flexible recipient addresses" />
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Global Reply-To</label>
                            <input v-model="advancedForm.email_reply_to_address" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Global automatic BCC</label>
                            <input v-model="advancedForm.email_automatic_bcc" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Inbound threading &amp; routing</h2>
                    <div class="mt-6 space-y-4">
                        <AppToggle v-model="advancedForm.email_use_original_sender_for_forwarded" label="Use original sender for forwarded mail" />
                        <AppToggle v-model="advancedForm.email_ignore_ticket_id_threading" label="Ignore ticket ID in subject for threading" />
                        <AppToggle v-model="advancedForm.email_create_ticket_on_subject_change" label="Create new ticket when subject changes" />
                        <AppToggle v-model="advancedForm.email_detect_auto_replies" label="Detect and skip auto-replies" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Automatic first response</h2>
                            <p class="mt-1 text-sm text-slate-500">Send an acknowledgment when a new email ticket is created.</p>
                        </div>
                        <AppToggle v-model="advancedForm.auto_first_response_enabled" />
                    </div>
                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Message template</label>
                        <textarea
                            v-model="advancedForm.auto_first_response_body"
                            rows="8"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                            :disabled="!advancedForm.auto_first_response_enabled"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Email blocklist</h2>
                    <p class="mt-1 text-sm text-slate-500">Inbound messages from these addresses or domains are ignored.</p>
                    <textarea
                        v-model="blocklistText"
                        rows="6"
                        placeholder="noreply@*&#10;mailer-daemon@*"
                        class="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                    />
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="advancedForm.processing">
                    Save email policies
                </button>
            </form>
        </div>

        <AppModal
            :open="showAddInbox"
            title="Add inbox"
            description="Connect a support email address to receive tickets."
            size="md"
            @close="closeAddInbox"
        >
            <form id="add-inbox-form" class="space-y-4" @submit.prevent="addInbox">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Display name</label>
                    <input v-model="inboxForm.name" type="text" required placeholder="Support" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                    <input v-model="inboxForm.address" type="email" required placeholder="support@company.com" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Brand</label>
                    <select v-model="inboxForm.brand_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Inbound method</label>
                    <select v-model="inboxForm.inbound_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="webhook">Forward / Webhook</option>
                        <option value="poll">IMAP / POP3</option>
                        <option value="oauth">OAuth (Google / Microsoft / Zoho)</option>
                    </select>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeAddInbox">Cancel</button>
                    <button type="submit" form="add-inbox-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="inboxForm.processing">Create inbox</button>
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
