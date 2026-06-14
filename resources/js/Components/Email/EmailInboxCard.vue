<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';
import AppTabs from '../AppTabs.vue';
import AppCollapse from '../AppCollapse.vue';
import AppRowActions from '../AppRowActions.vue';
import AppEditAction from '../AppEditAction.vue';
import AppDeleteAction from '../AppDeleteAction.vue';
import MailOAuthSetupGuide from './MailOAuthSetupGuide.vue';

const props = defineProps({
    inbox: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    mailboxProviders: { type: Object, default: () => ({}) },
    oauthProviders: { type: Object, default: () => ({}) },
    expanded: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const brandOptions = computed(() => Array.isArray(props.brands) ? props.brands : []);
const departmentOptions = computed(() => Array.isArray(props.departments) ? props.departments : []);
const teamOptions = computed(() => Array.isArray(props.teams) ? props.teams : []);
const mailboxProviderMap = computed(() => props.mailboxProviders ?? {});
const oauthProviderList = computed(() => Object.values(props.oauthProviders ?? {}));
const providerOptions = computed(() => Object.entries(mailboxProviderMap.value).map(([key, provider]) => ({ key, ...provider })));

const localInbox = reactive({
    id: props.inbox.id,
    brand_id: props.inbox.brand_id ?? brandOptions.value[0]?.id ?? null,
    department_id: props.inbox.department_id ?? null,
    team_id: props.inbox.team_id ?? null,
    name: props.inbox.name ?? '',
    address: props.inbox.address ?? '',
    is_active: props.inbox.is_active ?? true,
    inbound_method: props.inbox.inbound_method ?? 'webhook',
    inbound_token: props.inbox.inbound_token ?? '',
    inbound_webhook_url: props.inbox.inbound_webhook_url ?? '',
    mailbox_provider: props.inbox.mailbox_provider ?? null,
    mailbox_protocol: props.inbox.mailbox_protocol ?? 'imap',
    mailbox_host: props.inbox.mailbox_host ?? '',
    mailbox_port: props.inbox.mailbox_port ?? null,
    mailbox_encryption: props.inbox.mailbox_encryption ?? 'ssl',
    mailbox_username: props.inbox.mailbox_username ?? props.inbox.address ?? '',
    mailbox_folder: props.inbox.mailbox_folder ?? 'INBOX',
    has_mailbox_password: props.inbox.has_mailbox_password ?? false,
    oauth_provider: props.inbox.oauth_provider ?? null,
    oauth_connected_email: props.inbox.oauth_connected_email ?? null,
    oauth_connected: props.inbox.oauth_connected ?? false,
    last_polled_at: props.inbox.last_polled_at ?? null,
    poll_error: props.inbox.poll_error ?? null,
});

const aliasesText = ref((props.inbox.aliases ?? []).join('\n'));
const methodTab = ref(props.inbox.inbound_method || 'webhook');
const oauthProviderTab = ref(props.inbox.oauth_provider || oauthProviderList.value[0]?.key || 'google');
const mailboxPassword = ref('');
const mailboxTesting = ref(false);
const tokenCopied = ref(false);

const needsSetup = computed(() => {
    if (!localInbox.is_active) {
        return false;
    }

    if (localInbox.inbound_method === 'oauth' && !localInbox.oauth_connected) {
        return true;
    }

    if (localInbox.inbound_method === 'poll' && !localInbox.has_mailbox_password) {
        return true;
    }

    return false;
});

const isExpanded = ref(props.expanded || needsSetup.value);

watch(() => props.expanded, (value) => {
    if (value) {
        isExpanded.value = true;
    }
});

watch(needsSetup, (value) => {
    if (value) {
        isExpanded.value = true;
    }
});

const oauthProviderTabs = computed(() =>
    oauthProviderList.value.map((provider) => ({ id: provider.key, label: provider.label })),
);

const activeOAuthProvider = computed(() =>
    oauthProviderList.value.find((provider) => provider.key === oauthProviderTab.value) ?? oauthProviderList.value[0] ?? null,
);

const guessOAuthProvider = () => {
    const address = (localInbox.address || '').toLowerCase();

    if (address.includes('@gmail.com') || address.includes('@googlemail.com')) {
        return 'google';
    }

    if (address.includes('@outlook.') || address.includes('@hotmail.') || address.includes('@live.')) {
        return 'microsoft';
    }

    if (address.includes('@zoho.')) {
        return 'zoho';
    }

    return oauthProviderList.value[0]?.key ?? null;
};

const quickConnect = () => {
    const provider = guessOAuthProvider();
    if (provider) {
        connectOAuth(provider);
    }
};

const toggleExpanded = () => {
    isExpanded.value = !isExpanded.value;
};

const methodTabs = computed(() => {
    const tabs = [
        { id: 'webhook', label: t('components.forward_webhook') },
        { id: 'poll', label: t('components.imap_pop3') },
    ];

    if (oauthProviderList.value.length) {
        tabs.push({ id: 'oauth', label: t('components.oauth') });
    }

    return tabs;
});

const filteredTeams = computed(() =>
    teamOptions.value.filter((team) => !localInbox.department_id || team.department_id === Number(localInbox.department_id)),
);

const methodBadge = computed(() => {
    if (methodTab.value === 'webhook') return { label: t('components.webhook'), class: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200' };
    if (methodTab.value === 'oauth') return { label: t('components.oauth'), class: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200' };
    return { label: t('components.imap_pop3'), class: 'bg-violet-100 text-violet-800 dark:bg-violet-950/50 dark:text-violet-200' };
});

const showGoogleWorkspaceOAuthHint = computed(() => {
    if (methodTab.value !== 'poll') {
        return false;
    }

    const address = (localInbox.address || '').toLowerCase();

    return address.includes('@') && !address.endsWith('@gmail.com') && !address.endsWith('@googlemail.com');
});

const selectedProviderHelp = computed(() => {
    if (!localInbox.mailbox_provider) {
        return null;
    }

    return mailboxProviderMap.value[localInbox.mailbox_provider]?.help ?? null;
});

const webhookForwardingAddress = computed(() => `${localInbox.inbound_webhook_url}?token=${localInbox.inbound_token}`);

const applyProvider = () => {
    const provider = mailboxProviderMap.value[localInbox.mailbox_provider];
    if (!provider) return;
    localInbox.mailbox_protocol = provider.protocol ?? localInbox.mailbox_protocol ?? 'imap';
    localInbox.mailbox_host = provider.host ?? localInbox.mailbox_host;
    localInbox.mailbox_port = provider.port ?? localInbox.mailbox_port;
    localInbox.mailbox_encryption = provider.encryption ?? localInbox.mailbox_encryption ?? 'ssl';
    localInbox.mailbox_folder = provider.folder ?? localInbox.mailbox_folder ?? 'INBOX';
};

const syncPollDefaults = () => {
    localInbox.mailbox_username = localInbox.mailbox_username || localInbox.address;
    if (!localInbox.mailbox_provider && localInbox.address?.includes('@gmail.com')) {
        localInbox.mailbox_provider = 'gmail';
        applyProvider();
    }
};

const formatPolledAt = (value) => value ? formatDateTime(value) : t('components.not_yet');

const testMailbox = () => {
    mailboxTesting.value = true;
    router.post(`/settings/email/inboxes/${localInbox.id}/mailbox/test`, {}, {
        preserveScroll: true,
        onFinish: () => { mailboxTesting.value = false; },
    });
};

const pollMailbox = () => {
    router.post(`/settings/email/inboxes/${localInbox.id}/mailbox/poll`, {}, { preserveScroll: true });
};

const copyToken = async () => {
    if (!localInbox.inbound_token) return;
    await navigator.clipboard.writeText(localInbox.inbound_token);
    tokenCopied.value = true;
    setTimeout(() => { tokenCopied.value = false; }, 2000);
};

const regenerateToken = () => {
    router.post(`/settings/email/inboxes/${localInbox.id}/regenerate-token`, {}, { preserveScroll: true });
};

const connectOAuth = (provider) => {
    window.location.href = `/settings/email/inboxes/${localInbox.id}/oauth/${provider}`;
};

const disconnectOAuth = () => {
    router.post(`/settings/email/inboxes/${localInbox.id}/oauth/disconnect`, {}, { preserveScroll: true });
};

const isProviderConnected = (providerKey) => localInbox.oauth_connected && localInbox.oauth_provider === providerKey;

watch(methodTab, (value) => {
    localInbox.inbound_method = value;
    if (value === 'poll') syncPollDefaults();
});

watch(() => props.inbox.inbound_method, (value) => {
    if (value && value !== methodTab.value) {
        methodTab.value = value;
        localInbox.inbound_method = value;
    }
});

watch(() => props.inbox.oauth_connected, (connected) => {
    if (connected) {
        methodTab.value = 'oauth';
        localInbox.inbound_method = 'oauth';
        oauthProviderTab.value = props.inbox.oauth_provider || oauthProviderTab.value;
    }
});

watch(() => props.inbox, (inbox) => {
    localInbox.oauth_connected = inbox.oauth_connected ?? false;
    localInbox.oauth_connected_email = inbox.oauth_connected_email ?? null;
    localInbox.oauth_provider = inbox.oauth_provider ?? null;
    localInbox.last_polled_at = inbox.last_polled_at ?? null;
    localInbox.poll_error = inbox.poll_error ?? null;
    localInbox.inbound_token = inbox.inbound_token ?? localInbox.inbound_token;
    localInbox.inbound_webhook_url = inbox.inbound_webhook_url ?? localInbox.inbound_webhook_url;
    localInbox.has_mailbox_password = inbox.has_mailbox_password ?? localInbox.has_mailbox_password;
}, { deep: true });

if (methodTab.value === 'poll') syncPollDefaults();

const parseAliases = () => aliasesText.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

const save = () => {
    const inboundMethod = localInbox.oauth_connected ? 'oauth' : methodTab.value;

    router.put(`/settings/email/inboxes/${localInbox.id}`, {
        name: localInbox.name,
        address: localInbox.address,
        brand_id: localInbox.brand_id,
        department_id: localInbox.department_id || null,
        team_id: localInbox.team_id || null,
        aliases: parseAliases(),
        is_active: localInbox.is_active,
        inbound_method: inboundMethod,
        oauth_provider: inboundMethod === 'oauth' ? (localInbox.oauth_provider || null) : null,
        mailbox_provider: inboundMethod === 'poll' ? (localInbox.mailbox_provider || null) : null,
        mailbox_protocol: localInbox.mailbox_protocol || 'imap',
        mailbox_host: localInbox.mailbox_host || null,
        mailbox_port: localInbox.mailbox_port ?? null,
        mailbox_encryption: localInbox.mailbox_encryption || 'none',
        mailbox_username: localInbox.mailbox_username || localInbox.address,
        mailbox_password: mailboxPassword.value || null,
        mailbox_folder: localInbox.mailbox_folder || 'INBOX',
    }, { preserveScroll: true, onSuccess: () => { mailboxPassword.value = ''; } });
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border agent-border agent-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b agent-border-subtle agent-panel-muted px-5 py-4">
            <button
                type="button"
                class="min-w-0 flex-1 text-left"
                @click="toggleExpanded"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold agent-text">{{ localInbox.name }}</h3>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="methodBadge.class">{{ methodBadge.label }}</span>
                    <span v-if="needsSetup" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ $t('settings_email.setup_required') }}</span>
                    <span v-if="!localInbox.is_active" class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">{{ $t('components.inactive') }}</span>
                </div>
                <p class="mt-0.5 text-sm agent-text-muted">{{ localInbox.address }}</p>
            </button>
            <AppRowActions class="shrink-0">
                <button
                    v-if="needsSetup && localInbox.inbound_method === 'oauth' && !localInbox.oauth_connected"
                    type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                    @click.stop="quickConnect"
                >
                    {{ $t('components.connect') }}
                </button>
                <button
                    v-else-if="localInbox.oauth_connected || (localInbox.inbound_method === 'poll' && localInbox.has_mailbox_password)"
                    type="button"
                    class="rounded-lg border agent-border px-3 py-1.5 text-xs font-medium agent-hover-surface"
                    @click.stop="pollMailbox"
                >
                    {{ $t('components.check_for_mail_now') }}
                </button>
                <AppEditAction
                    :label="isExpanded ? $t('components.collapse') : $t('components.edit')"
                    @click="toggleExpanded"
                />
                <AppDeleteAction
                    :label="$t('components.delete')"
                    @click="emit('remove', localInbox)"
                />
            </AppRowActions>
        </div>
        <AppCollapse :open="isExpanded">
            <form class="space-y-6 p-5" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.display_name') }}</label>
                    <input v-model="localInbox.name" type="text" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.email_address') }}</label>
                    <input v-model="localInbox.address" type="email" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.brand') }}</label>
                    <select v-model="localInbox.brand_id" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                        <option v-for="brand in brandOptions" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.department') }}</label>
                    <select v-model="localInbox.department_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                        <option :value="null">{{ $t('components.none') }}</option>
                        <option v-for="department in departmentOptions" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.team') }}</label>
                    <select v-model="localInbox.team_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm" :disabled="!filteredTeams.length">
                        <option :value="null">{{ $t('components.none') }}</option>
                        <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('components.aliases') }}</label>
                <textarea v-model="aliasesText" rows="3" :placeholder="$t('components.aliases_placeholder')" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 font-mono text-sm" />
            </div>
            <AppTabs v-model="methodTab" :items="methodTabs" variant="pills">
                <template #webhook>
                    <div class="space-y-4 rounded-xl border agent-border agent-panel-muted p-5">
                        <div>
                            <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.webhook_url') }}</label>
                            <input :value="localInbox.inbound_webhook_url" type="text" readonly class="w-full rounded-lg border agent-border agent-panel px-3 py-2 font-mono text-xs agent-text-muted" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.inbound_token') }}</label>
                            <div class="flex gap-2">
                                <input :value="localInbox.inbound_token" type="text" readonly class="min-w-0 flex-1 rounded-lg border agent-border agent-panel px-3 py-2 font-mono text-xs agent-text-muted" />
                                <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm agent-hover-surface" @click="copyToken">{{ tokenCopied ? $t('components.copied') : $t('components.copy') }}</button>
                                <button type="button" class="shrink-0 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-sm text-amber-900 dark:text-amber-200" @click="regenerateToken">{{ $t('components.regenerate') }}</button>
                            </div>
                        </div>
                        <p class="break-all rounded-lg border agent-border agent-panel px-2 py-1.5 font-mono text-[11px] agent-text-muted">{{ webhookForwardingAddress }}</p>
                    </div>
                </template>

                <template #poll>
                    <div class="space-y-5 rounded-xl border agent-border agent-panel-muted p-5">
                        <p v-if="showGoogleWorkspaceOAuthHint" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-900 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-200">
                            {{ $t('components.google_workspace_use_oauth') }}
                            <button type="button" class="ml-1 font-medium underline" @click="methodTab = 'oauth'">{{ $t('components.switch_to_oauth') }}</button>
                        </p>
                        <div>
                            <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.email_provider') }}</label>
                            <select v-model="localInbox.mailbox_provider" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" @change="applyProvider">
                                <option :value="null">{{ $t('components.choose_your_provider_ellipsis') }}</option>
                                <option v-for="provider in providerOptions" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                            </select>
                            <p v-if="selectedProviderHelp" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">{{ selectedProviderHelp }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.protocol') }}</label>
                                <select v-model="localInbox.mailbox_protocol" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm">
                                    <option value="imap">{{ $t('components.imap') }}</option>
                                    <option value="pop3">{{ $t('components.pop3') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.server_host') }}</label>
                                <input v-model="localInbox.mailbox_host" type="text" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.port') }}</label>
                                <input v-model.number="localInbox.mailbox_port" type="number" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.encryption') }}</label>
                                <select v-model="localInbox.mailbox_encryption" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm">
                                    <option value="ssl">{{ $t('components.ssl') }}</option>
                                    <option value="tls">{{ $t('components.tls_starttls') }}</option>
                                    <option value="none">{{ $t('components.none') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.username') }}</label>
                                <input v-model="localInbox.mailbox_username" type="text" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.app_password') }}</label>
                                <input v-model="mailboxPassword" type="password" :placeholder="localInbox.has_mailbox_password ? $t('components.app_password_keep') : $t('components.app_password_enter')" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                            </div>
                            <div v-if="localInbox.mailbox_protocol === 'imap'">
                                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.folder') }}</label>
                                <input v-model="localInbox.mailbox_folder" type="text" class="w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <p v-if="localInbox.poll_error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">{{ localInbox.poll_error }}</p>
                        <div class="flex flex-wrap items-center gap-3 border-t agent-border pt-4">
                            <button type="button" class="rounded-lg border agent-border agent-panel px-4 py-2 text-sm agent-hover-surface" :disabled="mailboxTesting" @click="testMailbox">{{ mailboxTesting ? $t('components.testing') : $t('components.test_connection') }}</button>
                            <button type="button" class="rounded-lg border agent-border agent-panel px-4 py-2 text-sm agent-hover-surface" @click="pollMailbox">{{ $t('components.check_for_mail_now') }}</button>
                            <span class="text-xs agent-text-subtle">{{ $t('components.last_checked', { date: formatPolledAt(localInbox.last_polled_at) }) }}</span>
                        </div>
                    </div>
                </template>

                <template #oauth>
                    <div class="space-y-4 rounded-xl border agent-border agent-panel-muted p-5">
                        <AppTabs v-if="oauthProviderTabs.length > 1" v-model="oauthProviderTab" :items="oauthProviderTabs" variant="pills" class="mb-1" />

                        <MailOAuthSetupGuide
                            v-if="activeOAuthProvider && !localInbox.oauth_connected"
                            :provider="activeOAuthProvider.key"
                        />

                        <div v-if="activeOAuthProvider" class="rounded-lg border agent-border agent-panel p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium agent-text">{{ activeOAuthProvider.label }}</p>
                                    <p v-if="isProviderConnected(activeOAuthProvider.key)" class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ $t('components.connected_as', { email: localInbox.oauth_connected_email }) }}</p>
                                    <p v-else class="mt-1 text-xs agent-text-muted">{{ activeOAuthProvider.help }}</p>
                                </div>
                                <button v-if="isProviderConnected(activeOAuthProvider.key)" type="button" class="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 dark:border-red-900/60 dark:text-red-300" @click="disconnectOAuth">{{ $t('components.disconnect') }}</button>
                                <button v-else type="button" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="connectOAuth(activeOAuthProvider.key)">{{ $t('components.connect') }}</button>
                            </div>
                        </div>

                        <p v-if="localInbox.oauth_connected && localInbox.oauth_provider === 'google'" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-200">
                            {{ $t('components.gmail_oauth_import_hint', { email: localInbox.oauth_connected_email || localInbox.address }) }}
                        </p>
                        <p v-if="localInbox.poll_error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">{{ localInbox.poll_error }}</p>
                        <div v-if="localInbox.oauth_connected" class="flex flex-wrap items-center gap-3 border-t agent-border pt-4">
                            <button type="button" class="rounded-lg border agent-border agent-panel px-4 py-2 text-sm agent-hover-surface" @click="pollMailbox">{{ $t('components.check_for_mail_now') }}</button>
                            <span class="text-xs agent-text-subtle">{{ $t('components.last_checked', { date: formatPolledAt(localInbox.last_polled_at) }) }}</span>
                        </div>
                    </div>
                </template>
            </AppTabs>

            <div class="flex flex-wrap items-center gap-3 border-t agent-border-subtle pt-4">
                <label class="flex items-center gap-2 text-sm agent-text">
                    <input v-model="localInbox.is_active" type="checkbox" class="rounded agent-border text-blue-600" />
                    {{ $t('components.inbox_is_active') }}
                </label>
                <div class="ml-auto flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('components.save_inbox') }}</button>
                    <button type="button" class="rounded-lg border border-red-200 dark:border-red-900/60 px-4 py-2 text-sm text-red-700 dark:text-red-300 hover:bg-red-50 dark:bg-red-950/40" @click="emit('remove', localInbox)">{{ $t('components.remove') }}</button>
                </div>
            </div>
            </form>
        </AppCollapse>
    </div>
</template>
