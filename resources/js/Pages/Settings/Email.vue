<script setup>
import { router, useForm, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppToggle from '../../Components/AppToggle.vue';
import EmailInboxCard from '../../Components/Email/EmailInboxCard.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useClipboard } from '../../composables/useClipboard.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    inboxes: { type: Array, default: () => [] },
    outbound: { type: Object, default: () => ({}) },
    mailboxProviders: { type: Object, default: () => ({}) },
    oauthProviders: { type: Object, default: () => ({}) },
    brands: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    emailAdvanced: { type: Object, default: () => ({}) },
});

const page = usePage();
const inboxes = toRef(props, 'inboxes');
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const highlightedInboxId = ref(null);

const advancedDefaults = props.emailAdvanced ?? {};

const { t } = useI18n();
const { copy: copyRedirectUri } = useClipboard();
const copiedOAuthRedirect = ref(null);

const { activeSection } = useSettingsSection({
    defaultSection: 'incoming',
    sections: ['incoming', 'outgoing', 'advanced'],
});

const sectionTabs = computed(() => [
    { id: 'incoming', label: t('settings.incoming_email') },
    { id: 'outgoing', label: t('settings.outgoing_email') },
    { id: 'advanced', label: t('settings.advanced_email') },
]);

const showAddInbox = ref(false);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const smtpProvider = ref('gmail');

const defaultBrandId = props.brands?.[0]?.id ?? null;

const inboxForm = useForm({
    name: '',
    address: '',
    brand_id: defaultBrandId,
    inbound_method: 'webhook',
    is_active: true,
    mailbox_provider: null,
    mailbox_protocol: 'imap',
    mailbox_host: '',
    mailbox_port: null,
    mailbox_encryption: 'ssl',
    mailbox_username: '',
    mailbox_password: '',
    mailbox_folder: 'INBOX',
});

const defaultInboxId = props.outbound.email_inbox_id
    ?? props.outbound.inbox_smtp_options?.[0]?.inbox_id
    ?? props.inboxes?.[0]?.id
    ?? null;

const outboundForm = useForm({
    enabled: props.outbound.enabled,
    reply_enabled: props.outbound.reply_enabled,
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
    email_allow_agent_initiated: advancedDefaults.email_allow_agent_initiated ?? false,
    email_use_agent_name_in_from: advancedDefaults.email_use_agent_name_in_from ?? false,
    email_automatic_bcc: advancedDefaults.email_automatic_bcc ?? '',
    email_reply_to_address: advancedDefaults.email_reply_to_address ?? '',
    email_use_reply_to_as_requester: advancedDefaults.email_use_reply_to_as_requester ?? false,
    email_use_original_sender_for_forwarded: advancedDefaults.email_use_original_sender_for_forwarded ?? true,
    email_flexible_recipients: advancedDefaults.email_flexible_recipients ?? true,
    email_ignore_ticket_id_threading: advancedDefaults.email_ignore_ticket_id_threading ?? false,
    email_create_ticket_on_subject_change: advancedDefaults.email_create_ticket_on_subject_change ?? false,
    email_detect_auto_replies: advancedDefaults.email_detect_auto_replies ?? true,
    auto_first_response_enabled: advancedDefaults.auto_first_response_enabled ?? false,
    auto_first_response_body: advancedDefaults.auto_first_response_body ?? '',
    email_blocklist: [...(advancedDefaults.email_blocklist ?? [])],
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
const oauthProviderList = computed(() => Object.values(props.oauthProviders ?? {}));

const copyOAuthRedirect = async (uri, providerKey) => {
    const success = await copyRedirectUri(uri);

    if (success) {
        copiedOAuthRedirect.value = providerKey;
        window.setTimeout(() => {
            if (copiedOAuthRedirect.value === providerKey) {
                copiedOAuthRedirect.value = null;
            }
        }, 2000);
    }
};

const selectedInboxSmtp = computed(() =>
    inboxSmtpOptions.value.find((option) => option.inbox_id === Number(outboundForm.email_inbox_id)) ?? null,
);

const inboxSmtpTestError = computed(() => {
    const formError = inboxTestForm.errors.inbox_smtp;
    const pageError = page.props.errors?.inbox_smtp;

    return formError || pageError || null;
});

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

const addInboxProviderOptions = () => Object.entries(props.mailboxProviders ?? {}).map(([key, provider]) => ({
    key,
    ...provider,
}));

const resetInboxForm = () => {
    inboxForm.reset();
    inboxForm.brand_id = defaultBrandId;
    inboxForm.inbound_method = 'webhook';
    inboxForm.is_active = true;
    inboxForm.mailbox_provider = null;
    inboxForm.mailbox_protocol = 'imap';
    inboxForm.mailbox_host = '';
    inboxForm.mailbox_port = null;
    inboxForm.mailbox_encryption = 'ssl';
    inboxForm.mailbox_username = '';
    inboxForm.mailbox_password = '';
    inboxForm.mailbox_folder = 'INBOX';
};

const applyAddInboxProvider = () => {
    const provider = props.mailboxProviders?.[inboxForm.mailbox_provider];
    if (!provider) return;
    inboxForm.mailbox_protocol = provider.protocol ?? inboxForm.mailbox_protocol ?? 'imap';
    inboxForm.mailbox_host = provider.host ?? inboxForm.mailbox_host;
    inboxForm.mailbox_port = provider.port ?? inboxForm.mailbox_port;
    inboxForm.mailbox_encryption = provider.encryption ?? inboxForm.mailbox_encryption ?? 'ssl';
    inboxForm.mailbox_folder = provider.folder ?? inboxForm.mailbox_folder ?? 'INBOX';
};

const initPollDefaults = () => {
    if (inboxForm.inbound_method !== 'poll') return;
    inboxForm.mailbox_protocol = inboxForm.mailbox_protocol || 'imap';
    inboxForm.mailbox_encryption = inboxForm.mailbox_encryption || 'ssl';
    inboxForm.mailbox_username = inboxForm.mailbox_username || inboxForm.address;
    inboxForm.mailbox_folder = inboxForm.mailbox_folder || 'INBOX';

    if (!inboxForm.mailbox_provider && inboxForm.address?.includes('@gmail.com')) {
        inboxForm.mailbox_provider = inboxForm.mailbox_protocol === 'pop3' ? 'gmail_pop3' : 'gmail';
        applyAddInboxProvider();
    }
};

watch(() => inboxForm.inbound_method, initPollDefaults);

watch(() => inboxForm.address, () => {
    if (inboxForm.inbound_method === 'poll') {
        if (!inboxForm.mailbox_username) {
            inboxForm.mailbox_username = inboxForm.address;
        }
        initPollDefaults();
    }
});

const scrollToInbox = (inboxId) => {
    if (!inboxId) return;
    highlightedInboxId.value = Number(inboxId);
    nextTick(() => {
        document.getElementById(`inbox-${inboxId}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
};

const addInbox = () => {
    inboxForm.post('/settings/email/inboxes', {
        preserveScroll: true,
        onSuccess: () => {
            resetInboxForm();
            closeAddInbox();

            const createdId = page.props.flash?.created_inbox_id;
            if (createdId) {
                scrollToInbox(createdId);
            }
        },
        onError: () => {
            showAddInbox.value = true;

            if (inboxForm.errors.address) {
                const address = inboxForm.address?.trim().toLowerCase();
                const existing = (inboxes.value ?? []).find((inbox) => inbox.address?.toLowerCase() === address);

                if (existing) {
                    closeAddInbox();
                    scrollToInbox(existing.id);
                }
            }
        },
    });
};

const inboxFormError = (field) => inboxForm.errors[field];
const hasInboxFormErrors = computed(() => Object.keys(inboxForm.errors).length > 0);

const removeInbox = (inbox) => {
    askConfirm({
        title: t('settings_email.remove_inbox'),
        message: `Remove ${inbox.address}? Existing tickets are kept but new mail will stop syncing.`,
        confirmLabel: 'Remove',
        action: () => router.delete(`/settings/email/inboxes/${inbox.id}`, { preserveScroll: true }),
    });
};

const openAddInbox = () => {
    resetInboxForm();
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
    inboxTestForm.post('/settings/email/outbound/test-inbox', {
        preserveScroll: true,
        onSuccess: () => {
            inboxTestForm.password = '';
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
    <SettingsPage
        :title="$t('settings_email.email_settings')"
        :description="$t('settings_email.connect_support_inboxes_configure_smtp_delivery_and_manage_inbound_ema')"
    >
        <PlanFeatureBanner v-if="activeSection === 'incoming'" feature="channels" />

        <SettingsSectionNav
            path="/settings/email"
            default-section="incoming"
            :sections="sectionTabs"
            :active-section="activeSection"
        />

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border agent-border agent-panel-muted px-4 py-3">
            <p class="text-sm agent-text-muted">
                {{ $t('settings_email_templates.page_description') }}
            </p>
            <Link href="/settings/email-templates" class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                {{ $t('settings.email_templates') }} →
            </Link>
        </div>

        <div v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            {{ flashSuccess }}
        </div>

        <div v-if="activeSection === 'incoming'">

            <div class="rounded-2xl border agent-border agent-panel shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b agent-border-subtle px-6 py-5">
                    <div>
                        <h2 class="text-lg font-semibold agent-text">{{ $t('settings.incoming_email') }}</h2>
                        <p class="mt-1 text-sm agent-text-muted">
                            {{ $t('settings_email.receive_tickets_via_webhook_forwarding_imap_pop3_polling_or_oauth_mail') }}
                        </p>
                    </div>
                    <button type="button" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openAddInbox">{{ $t('settings_email.add_inbox') }}</button>
                </div>

                <div class="px-6 py-5">
                    <div v-if="!inboxes?.length" class="rounded-xl border border-dashed agent-border py-12 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.no_inboxes_yet') }}</p>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_email.add_your_first_support_email_address_to_start_receiving_tickets') }}</p>
                    </div>

                    <div class="space-y-6">
                        <div
                            v-for="inbox in inboxes"
                            :id="`inbox-${inbox.id}`"
                            :key="inbox.id"
                            :class="highlightedInboxId === inbox.id ? 'rounded-xl ring-2 ring-blue-500 ring-offset-2' : ''"
                        >
                            <EmailInboxCard
                                :inbox="inbox"
                                :brands="brands"
                                :departments="departments"
                                :teams="teams"
                                :mailbox-providers="mailboxProviders"
                                :oauth-providers="oauthProviders"
                                :expanded="highlightedInboxId === inbox.id"
                                @remove="removeInbox"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="activeSection === 'outgoing'">
            <div class="rounded-2xl border agent-border agent-panel shadow-sm">
                <div class="border-b agent-border-subtle px-6 py-5">
                    <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.outgoing_email_smtp') }}</h2>
                    <p class="mt-1 text-sm agent-text-muted">
                        {{ $t('settings_email.when_an_agent_replies_on_a_ticket_we_can_email_the_customer_from_your_') }}
                    </p>
                </div>

                <form class="space-y-6 px-6 py-5" @submit.prevent="saveOutbound">
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="outboundForm.enabled" type="checkbox" class="mt-0.5 rounded agent-border text-blue-600" />
                            <span>
                                <span class="font-medium">{{ $t('settings_email.enable_outgoing_email') }}</span>
                                <span class="mt-0.5 block text-xs agent-text-subtle">{{ $t('settings_email.required_before_any_emails_can_be_sent_from_the_helpdesk') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="outboundForm.reply_enabled" type="checkbox" class="mt-0.5 rounded agent-border text-blue-600" :disabled="!outboundForm.enabled" />
                            <span>
                                <span class="font-medium">{{ $t('settings_email.email_customers_when_agents_reply') }}</span>
                                <span class="mt-0.5 block text-xs agent-text-subtle">{{ $t('settings_email.sends_the_agents_message_to_the_ticket_contact_automatically') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.reply-to_address') }}</label>
                            <input v-model="outboundForm.reply_to_address" type="email" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :placeholder="$t('settings_email.replies_company_com')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.automatic_bcc') }}</label>
                            <input v-model="outboundForm.automatic_bcc" type="email" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :placeholder="$t('settings_email.archive_company_com')" />
                        </div>
                    </div>

                    <AppToggle
                        v-model="outboundForm.use_agent_name_in_from"
                        :label="$t('settings_email.use_agent_name_in_from_header')"
                        description="Shows the replying agent's name alongside the support address."
                    />

                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/60 dark:bg-blue-950/40">
                        <label class="flex items-start gap-3 text-sm text-slate-800 dark:text-slate-200">
                            <input
                                v-model="outboundForm.use_inbox_smtp"
                                type="checkbox"
                                class="mt-0.5 rounded agent-border text-blue-600"
                                :disabled="!outboundForm.enabled || !inboxSmtpOptions.length"
                                @change="onUseInboxSmtpChange"
                            />
                            <span>
                                <span class="font-medium">{{ $t('settings_email.use_same_address_as_inbound_inbox') }}</span>
                                <span class="mt-0.5 block text-xs agent-text-muted">{{ $t('settings_email.replies_are_sent_from_the_same_support_address_customers_emailed') }}</span>
                            </span>
                        </label>

                        <div v-if="outboundForm.use_inbox_smtp" class="mt-4 space-y-4 border-t border-blue-200 pt-4 dark:border-blue-900/60">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.inbound_inbox') }}</label>
                                <select v-model="outboundForm.email_inbox_id" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                                    <option v-for="option in inboxSmtpOptions" :key="option.inbox_id" :value="option.inbox_id">
                                        {{ option.name }} — {{ option.address }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="selectedInboxSmtp" class="rounded-lg border border-blue-200 dark:border-blue-900/60 bg-white dark:bg-slate-900 p-4 text-sm text-slate-700 dark:text-slate-300">
                                <p><span class="font-medium">{{ $t('settings_email.send_as') }}</span> {{ selectedInboxSmtp.address }}</p>
                                <p class="mt-1"><span class="font-medium">{{ $t('settings_email.smtp') }}</span> {{ selectedInboxSmtp.host }}:{{ selectedInboxSmtp.port }} ({{ selectedInboxSmtp.encryption?.toUpperCase() }})</p>
                            </div>

                            <div class="space-y-3 border-t border-blue-200 pt-4 dark:border-blue-900/60">
                                <p v-if="selectedInboxSmtp?.inbound_method === 'oauth'" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                                    {{ $t('settings_email.oauth_inbound_smtp_app_password') }}
                                </p>
                                <p v-else-if="selectedInboxSmtp && !selectedInboxSmtp.has_inbound_password" class="text-xs text-amber-800 dark:text-amber-200">
                                    {{ $t('components.use_an_app_password_from_your_email_provider_replies_with_ticket_ids_i') }}
                                </p>
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="min-w-[16rem] flex-1">
                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.send_a_test_email') }}</label>
                                        <input v-model="inboxTestForm.to" type="email" :placeholder="$t('settings_email.you_example_com')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                                    </div>
                                    <div class="min-w-[16rem] flex-1">
                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.app_password') }}</label>
                                        <input
                                            v-model="inboxTestForm.password"
                                            type="password"
                                            :placeholder="selectedInboxSmtp?.has_inbound_password ? $t('components.app_password_keep') : $t('components.app_password_enter')"
                                            class="w-full rounded-lg border agent-border px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-blue-200 dark:border-blue-900/60 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-50 dark:bg-blue-950/40"
                                        :disabled="inboxTestForm.processing || !outboundForm.email_inbox_id || !inboxTestForm.to"
                                        @click="sendInboxSmtpTest"
                                    >{{ $t('settings_email.test_inbox_smtp') }}</button>
                                </div>
                                <p v-if="inboxSmtpTestError" class="text-sm text-red-600">{{ inboxSmtpTestError }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!outboundForm.use_inbox_smtp" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.driver') }}</label>
                                <select v-model="outboundForm.driver" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                                    <option value="smtp">{{ $t('settings_email.smtp_real_email') }}</option>
                                    <option value="log">{{ $t('settings_email.log_only_testing') }}</option>
                                </select>
                            </div>
                            <div v-if="outboundForm.driver === 'smtp'">
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.provider_preset') }}</label>
                                <div class="flex gap-2">
                                    <select v-model="smtpProvider" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                                        <option v-for="provider in smtpProviderOptions()" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                                    </select>
                                    <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm text-slate-700 dark:text-slate-300 agent-hover-surface" @click="applySmtpProvider">{{ $t('settings_email.apply') }}</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.from_name') }}</label>
                                <input v-model="outboundForm.from_name" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.from_address') }}</label>
                                <input v-model="outboundForm.from_address" type="email" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                            </div>
                            <template v-if="outboundForm.driver === 'smtp'">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.smtp_host') }}</label>
                                    <input v-model="outboundForm.host" type="text" placeholder="smtp.gmail.com" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.port') }}</label>
                                    <input v-model.number="outboundForm.port" type="number" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.encryption') }}</label>
                                    <select v-model="outboundForm.encryption" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                                        <option value="tls">{{ $t('settings_email.tls_recommended') }}</option>
                                        <option value="ssl">{{ $t('settings_email.ssl') }}</option>
                                        <option value="null">{{ $t('settings_email.none') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.username') }}</label>
                                    <input v-model="outboundForm.username" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings.password') }}</label>
                                    <input v-model="outboundForm.password" type="password" :placeholder="outbound.has_password ? 'Leave blank to keep current' : ''" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="outboundForm.processing">{{ $t('settings_email.save_outgoing_settings') }}</button>
                </form>

                <form class="flex flex-wrap items-end gap-3 border-t agent-border-subtle px-6 py-5" @submit.prevent="sendTest">
                    <div class="min-w-[16rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.send_a_test_email') }}</label>
                        <input v-model="testForm.to" type="email" required :placeholder="$t('settings_email.you_example_com')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" class="agent-btn-secondary" :disabled="testForm.processing">{{ $t('settings_email.send_test') }}</button>
                </form>
            </div>
        </div>

        <div v-if="activeSection === 'advanced'">
            <form class="max-w-3xl space-y-6" @submit.prevent="saveAdvanced">
                <div class="rounded-2xl border agent-border agent-panel p-6 shadow-sm">
                    <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.sender_delivery_policies') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_email.global_defaults_for_outbound_email_behavior_across_all_channels') }}</p>

                    <div class="mt-6 space-y-4">
                        <AppToggle v-model="advancedForm.email_allow_agent_initiated" :label="$t('settings_email.allow_agent-initiated_outbound_email')" />
                        <AppToggle v-model="advancedForm.email_use_agent_name_in_from" :label="$t('settings_email.use_agent_name_in_from_header_global')" />
                        <AppToggle v-model="advancedForm.email_use_reply_to_as_requester" :label="$t('settings_email.use_reply-to_as_requester_address')" />
                        <AppToggle v-model="advancedForm.email_flexible_recipients" :label="$t('settings_email.allow_flexible_recipient_addresses')" />
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.global_reply-to') }}</label>
                            <input v-model="advancedForm.email_reply_to_address" type="email" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.global_automatic_bcc') }}</label>
                            <input v-model="advancedForm.email_automatic_bcc" type="email" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border agent-border agent-panel p-6 shadow-sm">
                    <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.inbound_threading_routing') }}</h2>
                    <div class="mt-6 space-y-4">
                        <AppToggle v-model="advancedForm.email_use_original_sender_for_forwarded" :label="$t('settings_email.use_original_sender_for_forwarded_mail')" />
                        <AppToggle v-model="advancedForm.email_ignore_ticket_id_threading" :label="$t('settings_email.ignore_ticket_id_in_subject_for_threading')" />
                        <AppToggle v-model="advancedForm.email_create_ticket_on_subject_change" :label="$t('settings_email.create_new_ticket_when_subject_changes')" />
                        <AppToggle v-model="advancedForm.email_detect_auto_replies" :label="$t('settings_email.detect_and_skip_auto-replies')" />
                    </div>
                </div>

                <div class="rounded-2xl border agent-border agent-panel p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.automatic_first_response') }}</h2>
                            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_email.send_an_acknowledgment_when_a_new_email_ticket_is_created') }}</p>
                        </div>
                        <AppToggle v-model="advancedForm.auto_first_response_enabled" />
                    </div>
                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.message_template') }}</label>
                        <p class="mb-2 text-xs agent-text-subtle">{{ $t('settings_email.first_response_placeholders_hint') }}</p>
                        <div class="mb-3 flex flex-wrap gap-2">
                            <code v-pre class="rounded bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300">{{contact_name}}</code>
                            <code v-pre class="rounded bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300">{{ticket_number}}</code>
                            <code v-pre class="rounded bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300">{{ticket_subject}}</code>
                        </div>
                        <textarea
                            v-model="advancedForm.auto_first_response_body"
                            rows="8"
                            class="w-full rounded-lg border agent-border px-3 py-2 font-mono text-sm"
                            :disabled="!advancedForm.auto_first_response_enabled"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border agent-border agent-panel p-6 shadow-sm">
                    <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.email_blocklist') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_email.inbound_messages_from_these_addresses_or_domains_are_ignored') }}</p>
                    <textarea
                        v-model="blocklistText"
                        rows="6"
                        :placeholder="$t('settings_email.noreply_mailer-daemon')"
                        class="mt-4 w-full rounded-lg border agent-border px-3 py-2 font-mono text-sm"
                    />
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="advancedForm.processing">{{ $t('settings_email.save_email_policies') }}</button>
            </form>
        </div>

        <AppModal
            :open="showAddInbox"
            :title="$t('settings_email.add_inbox')"
            :description="$t('settings_email.connect_a_support_email_address_to_receive_tickets')"
            :size="inboxForm.inbound_method === 'webhook' ? 'md' : 'xl'"
            @close="closeAddInbox"
        >
            <form id="add-inbox-form" class="space-y-4" @submit.prevent="addInbox">
                <div v-if="hasInboxFormErrors" class="rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    <p class="font-medium">{{ $t('settings_email.inbox_form_errors_title') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="(message, field) in inboxForm.errors" :key="field">{{ message }}</li>
                    </ul>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.display_name') }}</label>
                    <input v-model="inboxForm.name" type="text" required :placeholder="$t('nav.sections.support')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :class="inboxFormError('name') ? 'border-red-400' : ''" />
                    <p v-if="inboxFormError('name')" class="mt-1 text-xs text-red-600">{{ inboxFormError('name') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.email_address') }}</label>
                    <input v-model="inboxForm.address" type="email" required :placeholder="$t('settings_email.support_company_com')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :class="inboxFormError('address') ? 'border-red-400' : ''" />
                    <p v-if="inboxFormError('address')" class="mt-1 text-xs text-red-600">{{ inboxFormError('address') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.brand') }}</label>
                    <select v-model="inboxForm.brand_id" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :class="inboxFormError('brand_id') ? 'border-red-400' : ''">
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                    <p v-if="inboxFormError('brand_id')" class="mt-1 text-xs text-red-600">{{ inboxFormError('brand_id') }}</p>
                    <p v-else-if="!brands.length" class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $t('settings_email.create_a_brand_first') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.inbound_method') }}</label>
                    <select v-model="inboxForm.inbound_method" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="webhook">{{ $t('settings_email.forward_webhook') }}</option>
                        <option value="poll">{{ $t('settings_email.imap_pop3') }}</option>
                        <option value="oauth">{{ $t('settings_email.oauth_google_microsoft_zoho') }}</option>
                    </select>
                </div>

                <div v-if="inboxForm.inbound_method === 'poll'" class="space-y-4 rounded-xl border agent-border agent-panel-muted p-4">
                    <div class="rounded-lg border agent-border agent-panel p-3">
                        <p class="text-sm font-medium agent-text">{{ $t('components.mailbox_connection') }}</p>
                        <p class="mt-1 text-xs leading-relaxed agent-text-muted">
                            {{ $t('components.use_an_app_password_from_your_email_provider_replies_with_ticket_ids_i') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.email_provider') }}</label>
                        <select v-model="inboxForm.mailbox_provider" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" @change="applyAddInboxProvider">
                            <option :value="null">{{ $t('components.choose_your_provider_ellipsis') }}</option>
                            <option v-for="provider in addInboxProviderOptions()" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                        </select>
                        <p v-if="mailboxProviders?.[inboxForm.mailbox_provider]?.help" class="mt-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                            {{ mailboxProviders[inboxForm.mailbox_provider].help }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.protocol') }}</label>
                            <select v-model="inboxForm.mailbox_protocol" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm">
                                <option value="imap">{{ $t('components.imap') }}</option>
                                <option value="pop3">{{ $t('components.pop3') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.server_host') }}</label>
                            <input v-model="inboxForm.mailbox_host" type="text" placeholder="pop.gmail.com" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.port') }}</label>
                            <input v-model.number="inboxForm.mailbox_port" type="number" placeholder="995" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.encryption') }}</label>
                            <select v-model="inboxForm.mailbox_encryption" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm">
                                <option value="ssl">{{ $t('components.ssl') }}</option>
                                <option value="tls">{{ $t('components.tls_starttls') }}</option>
                                <option value="none">{{ $t('components.none') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.username') }}</label>
                            <input v-model="inboxForm.mailbox_username" type="text" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.app_password') }}</label>
                            <input v-model="inboxForm.mailbox_password" type="password" :placeholder="$t('components.app_password_enter')" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                        </div>
                        <div v-if="inboxForm.mailbox_protocol === 'imap'">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.folder') }}</label>
                            <input v-model="inboxForm.mailbox_folder" type="text" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                        </div>
                    </div>
                </div>

                <div v-else-if="inboxForm.inbound_method === 'oauth'" class="space-y-4 rounded-xl border agent-border agent-panel-muted p-4">
                    <p class="text-sm agent-text">{{ $t('components.connect_google_microsoft_or_zoho_to_sync_mail_without_storing_password') }}</p>
                    <p class="text-xs agent-text-muted">{{ $t('settings_email.create_inbox_then_connect_oauth') }}</p>
                    <div class="space-y-3">
                        <div v-for="provider in oauthProviderList" :key="provider.key" class="rounded-lg border agent-border agent-panel p-4">
                            <p class="text-sm font-medium agent-text">{{ provider.label }}</p>
                            <p v-if="!provider.configured" class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $t('components.not_configured_on_server') }}</p>
                            <p v-if="provider.help" class="mt-1 text-xs agent-text-subtle">{{ provider.help }}</p>
                            <div v-if="provider.redirect_uri" class="mt-3 border-t agent-border-subtle pt-3">
                                <label class="mb-1 block text-xs font-medium agent-text-muted">{{ $t('components.authorized_redirect_uri') }}</label>
                                <div class="flex gap-2">
                                    <input :value="provider.redirect_uri" type="text" readonly class="min-w-0 flex-1 rounded-lg border agent-border agent-panel px-3 py-2 font-mono text-[11px] agent-text-muted" />
                                    <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm agent-hover-surface" @click="copyOAuthRedirect(provider.redirect_uri, provider.key)">
                                        {{ copiedOAuthRedirect === provider.key ? $t('components.copied') : $t('components.copy') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeAddInbox">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="add-inbox-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="inboxForm.processing">{{ $t('settings_email.create_inbox') }}</button>
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
    </SettingsPage>
</template>
