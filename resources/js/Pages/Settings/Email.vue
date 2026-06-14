<script setup>
import { router, useForm, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppToggle from '../../Components/AppToggle.vue';
import EmailInboxCard from '../../Components/Email/EmailInboxCard.vue';
import EmailProviderPicker from '../../Components/Email/EmailProviderPicker.vue';
import EmailAddInboxPanel from '../../Components/Email/EmailAddInboxPanel.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
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
const oauthFeedback = ref(null);
const oauthFeedbackError = ref(null);
const highlightedInboxId = ref(null);

const advancedDefaults = props.emailAdvanced ?? {};

const { t } = useI18n();

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
const selectedSetupProvider = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const smtpProvider = ref('gmail');

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
const hasOAuthProviders = computed(() => oauthProviderList.value.length > 0);

const setupProviders = computed(() => {
    const providers = [];

    if (hasOAuthProviders.value) {
        if (props.oauthProviders?.google) {
            providers.push({
                key: 'google',
                method: 'oauth',
                oauth_provider: 'google',
                label: t('settings_email.provider_google'),
                description: t('settings_email.provider_google_desc'),
                setupHint: t('settings_email.provider_google_setup_hint'),
            });
        }

        if (props.oauthProviders?.microsoft) {
            providers.push({
                key: 'microsoft',
                method: 'oauth',
                oauth_provider: 'microsoft',
                label: t('settings_email.provider_microsoft'),
                description: t('settings_email.provider_microsoft_desc'),
                setupHint: t('settings_email.provider_microsoft_setup_hint'),
            });
        }

        if (props.oauthProviders?.zoho) {
            providers.push({
                key: 'zoho',
                method: 'oauth',
                oauth_provider: 'zoho',
                label: t('settings_email.provider_zoho'),
                description: t('settings_email.provider_zoho_desc'),
                setupHint: t('settings_email.provider_zoho_setup_hint'),
            });
        }
    }

    providers.push({
        key: 'imap',
        method: 'poll',
        mailbox_provider: null,
        label: t('settings_email.provider_imap'),
        description: t('settings_email.provider_imap_desc'),
        setupHint: t('settings_email.provider_imap_setup_hint'),
    });

    providers.push({
        key: 'webhook',
        method: 'webhook',
        label: t('settings_email.provider_webhook'),
        description: t('settings_email.provider_webhook_desc'),
        setupHint: t('settings_email.provider_webhook_setup_hint'),
    });

    return providers;
});

const activeSetupProvider = computed(() =>
    setupProviders.value.find((provider) => provider.key === selectedSetupProvider.value) ?? null,
);

const inboxNeedsSetup = (inbox) => {
    if (!inbox?.is_active) {
        return false;
    }

    if (inbox.inbound_method === 'oauth' && !inbox.oauth_connected) {
        return true;
    }

    if (inbox.inbound_method === 'poll' && !inbox.has_mailbox_password) {
        return true;
    }

    return false;
};

const expandedInboxIds = computed(() => {
    const ids = new Set();

    if (highlightedInboxId.value) {
        ids.add(highlightedInboxId.value);
    }

    const firstIncomplete = inboxes.value?.find((inbox) => inboxNeedsSetup(inbox));
    if (firstIncomplete) {
        ids.add(firstIncomplete.id);
    }

    if (!inboxes.value?.length) {
        return ids;
    }

    if (inboxes.value.length === 1) {
        ids.add(inboxes.value[0].id);
    }

    return ids;
});

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

    if (!inboxes.value?.length && setupProviders.value.length) {
        openAddInbox(setupProviders.value[0].key);
    }

    const params = new URLSearchParams(window.location.search);
    const oauth = params.get('oauth');
    const oauthError = params.get('oauth_error');

    if (oauth === 'connected') {
        oauthFeedback.value = t('settings_email.oauth_connected_success', {
            email: params.get('email') ?? '',
            provider: params.get('provider') ?? 'OAuth',
            fetched: Number(params.get('fetched') ?? 0),
            created: Number(params.get('created') ?? 0),
            reply: Number(params.get('reply') ?? 0),
        });

        scrollToInbox(params.get('inbox'));
    } else if (oauthError) {
        oauthFeedbackError.value = oauthError;
    }

    if (oauth || oauthError) {
        const url = new URL(window.location.href);
        url.search = '';
        window.history.replaceState({}, '', `${url.pathname}${url.hash}`);
    }
});

const scrollToInbox = (inboxId) => {
    if (!inboxId) return;
    highlightedInboxId.value = Number(inboxId);
    nextTick(() => {
        document.getElementById(`inbox-${inboxId}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
};

const removeInbox = (inbox) => {
    askConfirm({
        title: t('settings_email.remove_inbox'),
        message: `Remove ${inbox.address}? Existing tickets are kept but new mail will stop syncing.`,
        confirmLabel: 'Remove',
        action: () => router.delete(`/settings/email/inboxes/${inbox.id}`, { preserveScroll: true }),
    });
};

const openAddInbox = (providerKey = null) => {
    selectedSetupProvider.value = providerKey ?? setupProviders.value[0]?.key ?? null;
    showAddInbox.value = true;
};

const closeAddInbox = () => {
    showAddInbox.value = false;
    selectedSetupProvider.value = null;
};

const onInboxCreated = ({ id }) => {
    closeAddInbox();

    if (id) {
        scrollToInbox(id);
    }
};

const quickEnableOutbound = () => {
    outboundForm.enabled = true;
    outboundForm.reply_enabled = true;
    outboundForm.use_inbox_smtp = inboxSmtpOptions.value.length > 0;
    outboundForm.email_inbox_id = defaultInboxId;
    outboundForm.driver = 'smtp';
    saveOutbound();
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

watch(smtpProvider, () => {
    applySmtpProvider();
});

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

        <div v-if="flashSuccess || oauthFeedback" class="mb-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            {{ flashSuccess || oauthFeedback }}
        </div>
        <div v-if="oauthFeedbackError || page.props.errors?.oauth" class="mb-4 rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ oauthFeedbackError || page.props.errors?.oauth }}
        </div>

        <div v-if="activeSection === 'incoming'">
            <div class="space-y-6">
                <div class="rounded-2xl border agent-border agent-panel shadow-sm">
                    <div class="border-b agent-border-subtle px-6 py-5">
                        <h2 class="text-lg font-semibold agent-text">{{ $t('settings_email.connect_an_inbox') }}</h2>
                        <p class="mt-1 text-sm agent-text-muted">{{ $t('settings_email.connect_an_inbox_desc') }}</p>
                    </div>
                    <div class="space-y-5 px-6 py-5">
                        <EmailProviderPicker
                            :providers="setupProviders"
                            :selected-key="selectedSetupProvider"
                            @select="openAddInbox"
                        />
                        <EmailAddInboxPanel
                            v-if="showAddInbox && activeSetupProvider"
                            :provider="activeSetupProvider"
                            :brands="brands"
                            :mailbox-providers="mailboxProviders"
                            :oauth-providers="oauthProviders"
                            @cancel="closeAddInbox"
                            @created="onInboxCreated"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border agent-border agent-panel shadow-sm">
                    <div class="border-b agent-border-subtle px-6 py-5">
                        <h2 class="text-lg font-semibold agent-text">{{ $t('settings.incoming_email') }}</h2>
                        <p class="mt-1 text-sm agent-text-muted">
                            {{ $t('settings_email.receive_tickets_via_webhook_forwarding_imap_pop3_polling_or_oauth_mail') }}
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        <div v-if="!inboxes?.length" class="rounded-xl border border-dashed agent-border py-10 text-center">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_email.no_inboxes_yet') }}</p>
                            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_email.pick_provider_above') }}</p>
                        </div>

                        <div v-else class="space-y-4">
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
                                    :expanded="expandedInboxIds.has(inbox.id)"
                                    @remove="removeInbox"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="activeSection === 'outgoing'">
            <div
                v-if="inboxes?.length && (!outboundForm.enabled || !outboundForm.reply_enabled)"
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 dark:border-emerald-900/60 dark:bg-emerald-950/30"
            >
                <div>
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $t('settings_email.quick_outbound_title') }}</p>
                    <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">{{ $t('settings_email.quick_outbound_desc') }}</p>
                </div>
                <button type="button" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" @click="quickEnableOutbound">
                    {{ $t('settings_email.enable_replies_now') }}
                </button>
            </div>

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
                                <select v-model="smtpProvider" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                                    <option v-for="provider in smtpProviderOptions()" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                                </select>
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
