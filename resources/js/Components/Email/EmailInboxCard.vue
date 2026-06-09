<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppTabs from '../AppTabs.vue';

const props = defineProps({
    inbox: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    mailboxProviders: { type: Object, default: () => ({}) },
    oauthProviders: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['remove']);

const mailboxPassword = ref('');
const mailboxTesting = ref(false);
const tokenCopied = ref(false);
const aliasesText = ref((props.inbox.aliases ?? []).join('\n'));

const methodTab = ref(props.inbox.inbound_method || 'webhook');

const methodTabs = [
    { id: 'webhook', label: 'Forward / Webhook' },
    { id: 'poll', label: 'IMAP / POP3' },
    { id: 'oauth', label: 'OAuth' },
];

const filteredTeams = computed(() =>
    props.teams.filter((team) => !props.inbox.department_id || team.department_id === Number(props.inbox.department_id)),
);

const methodBadge = computed(() => {
    if (methodTab.value === 'webhook') return { label: 'Webhook', class: 'bg-sky-100 text-sky-800' };
    if (methodTab.value === 'oauth') return { label: 'OAuth', class: 'bg-indigo-100 text-indigo-800' };

    return { label: 'IMAP / POP3', class: 'bg-violet-100 text-violet-800' };
});

const oauthProviderList = computed(() => Object.values(props.oauthProviders ?? {}));

watch(methodTab, (value) => {
    props.inbox.inbound_method = value;

    if (value === 'poll') {
        props.inbox.mailbox_protocol = props.inbox.mailbox_protocol || 'imap';
        props.inbox.mailbox_encryption = props.inbox.mailbox_encryption || 'ssl';
        props.inbox.mailbox_username = props.inbox.mailbox_username || props.inbox.address;
        props.inbox.mailbox_folder = props.inbox.mailbox_folder || 'INBOX';

        if (!props.inbox.mailbox_provider && props.inbox.address?.includes('@gmail.com')) {
            props.inbox.mailbox_provider = 'gmail';
            applyProvider();
        }
    }
});

watch(() => props.inbox.department_id, () => {
    if (props.inbox.team_id && !filteredTeams.value.some((team) => team.id === Number(props.inbox.team_id))) {
        props.inbox.team_id = null;
    }
});

const providerOptions = () => Object.entries(props.mailboxProviders ?? {}).map(([key, provider]) => ({
    key,
    ...provider,
}));

const applyProvider = () => {
    const provider = props.mailboxProviders?.[props.inbox.mailbox_provider];
    if (!provider) return;
    props.inbox.mailbox_protocol = provider.protocol ?? props.inbox.mailbox_protocol ?? 'imap';
    props.inbox.mailbox_host = provider.host ?? props.inbox.mailbox_host;
    props.inbox.mailbox_port = provider.port ?? props.inbox.mailbox_port;
    props.inbox.mailbox_encryption = provider.encryption ?? props.inbox.mailbox_encryption ?? 'ssl';
    props.inbox.mailbox_folder = provider.folder ?? props.inbox.mailbox_folder ?? 'INBOX';
};

const parseAliases = () => aliasesText.value
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

const save = () => {
    router.put(`/settings/email/inboxes/${props.inbox.id}`, {
        name: props.inbox.name,
        address: props.inbox.address,
        brand_id: props.inbox.brand_id,
        department_id: props.inbox.department_id || null,
        team_id: props.inbox.team_id || null,
        aliases: parseAliases(),
        is_active: props.inbox.is_active,
        inbound_method: methodTab.value,
        oauth_provider: methodTab.value === 'oauth' ? (props.inbox.oauth_provider || null) : null,
        mailbox_provider: methodTab.value === 'poll' ? (props.inbox.mailbox_provider || null) : null,
        mailbox_protocol: props.inbox.mailbox_protocol || 'imap',
        mailbox_host: props.inbox.mailbox_host || null,
        mailbox_port: props.inbox.mailbox_port || null,
        mailbox_encryption: props.inbox.mailbox_encryption || 'none',
        mailbox_username: props.inbox.mailbox_username || props.inbox.address,
        mailbox_password: mailboxPassword.value || null,
        mailbox_folder: props.inbox.mailbox_folder || 'INBOX',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            mailboxPassword.value = '';
        },
    });
};

const copyToken = async () => {
    if (!props.inbox.inbound_token) return;
    await navigator.clipboard.writeText(props.inbox.inbound_token);
    tokenCopied.value = true;
    setTimeout(() => { tokenCopied.value = false; }, 2000);
};

const regenerateToken = () => {
    router.post(`/settings/email/inboxes/${props.inbox.id}/regenerate-token`, {}, { preserveScroll: true });
};

const testMailbox = () => {
    mailboxTesting.value = true;
    router.post(`/settings/email/inboxes/${props.inbox.id}/mailbox/test`, {}, {
        preserveScroll: true,
        onFinish: () => { mailboxTesting.value = false; },
    });
};

const pollMailbox = () => {
    router.post(`/settings/email/inboxes/${props.inbox.id}/mailbox/poll`, {}, { preserveScroll: true });
};

const connectOAuth = (provider) => {
    window.location.href = `/settings/email/inboxes/${props.inbox.id}/oauth/${provider}`;
};

const disconnectOAuth = () => {
    router.post(`/settings/email/inboxes/${props.inbox.id}/oauth/disconnect`, {}, { preserveScroll: true });
};

const isProviderConnected = (providerKey) =>
    props.inbox.oauth_connected && props.inbox.oauth_provider === providerKey;

const formatPolledAt = (value) => value ? new Date(value).toLocaleString() : 'Not yet';

const webhookForwardingAddress = computed(() => {
    const token = props.inbox.inbound_token ?? '';
    const base = props.inbox.inbound_webhook_url ?? '';

    return `${base}?token=${token}`;
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-slate-900">{{ inbox.name }}</h3>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="methodBadge.class">{{ methodBadge.label }}</span>
                    <span v-if="!inbox.is_active" class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                </div>
                <p class="mt-0.5 text-sm text-slate-600">{{ inbox.address }}</p>
            </div>
        </div>

        <form class="space-y-6 p-5" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Display name</label>
                    <input v-model="inbox.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                    <input v-model="inbox.address" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Brand</label>
                    <select v-model="inbox.brand_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                    <select v-model="inbox.department_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option :value="null">None</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="inbox.team_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" :disabled="!filteredTeams.length">
                        <option :value="null">None</option>
                        <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Aliases</label>
                <textarea
                    v-model="aliasesText"
                    rows="3"
                    placeholder="billing@company.com&#10;support+sales@company.com"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                />
                <p class="mt-1 text-xs text-slate-500">One email address per line. Incoming mail to these addresses routes to this inbox.</p>
            </div>

            <AppTabs v-model="methodTab" :items="methodTabs" variant="pills" />

            <div v-if="methodTab === 'webhook'" class="space-y-4 rounded-xl border border-sky-100 bg-sky-50/40 p-5">
                <div>
                    <p class="text-sm font-medium text-sky-900">Webhook endpoint</p>
                    <p class="mt-1 text-xs text-sky-800/90">Forward inbound email to this URL from your mail provider or automation tool.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Webhook URL</label>
                    <input :value="inbox.inbound_webhook_url" type="text" readonly class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Inbound token</label>
                    <div class="flex gap-2">
                        <input :value="inbox.inbound_token" type="text" readonly class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700" />
                        <button type="button" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-white" @click="copyToken">
                            {{ tokenCopied ? 'Copied' : 'Copy' }}
                        </button>
                        <button type="button" class="shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 hover:bg-amber-100" @click="regenerateToken">
                            Regenerate
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-sky-200 bg-white p-4">
                    <p class="text-sm font-medium text-slate-900">Forwarding instructions</p>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                        Configure your email provider to forward messages to
                        <code class="rounded bg-slate-100 px-1">{{ inbox.address }}</code>
                        and POST the parsed payload to the webhook URL with the token as a query parameter or header.
                    </p>
                    <p class="mt-2 break-all rounded bg-slate-50 px-2 py-1.5 font-mono text-[11px] text-slate-700">{{ webhookForwardingAddress }}</p>
                </div>
            </div>

            <div v-else-if="methodTab === 'poll'" class="space-y-5 rounded-xl border border-violet-100 bg-violet-50/30 p-5">
                <div class="rounded-lg border border-violet-100 bg-white/80 p-4">
                    <p class="text-sm font-medium text-violet-900">Mailbox connection</p>
                    <p class="mt-1 text-xs leading-relaxed text-violet-800/90">
                        Use an app password from your email provider. Replies with ticket IDs in the subject attach to existing tickets.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email provider</label>
                    <select v-model="inbox.mailbox_provider" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" @change="applyProvider">
                        <option :value="null">Choose your provider…</option>
                        <option v-for="provider in providerOptions()" :key="provider.key" :value="provider.key">{{ provider.label }}</option>
                    </select>
                    <p v-if="mailboxProviders?.[inbox.mailbox_provider]?.help" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        {{ mailboxProviders[inbox.mailbox_provider].help }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Protocol</label>
                        <select v-model="inbox.mailbox_protocol" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="imap">IMAP</option>
                            <option value="pop3">POP3</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Server host</label>
                        <input v-model="inbox.mailbox_host" type="text" placeholder="imap.gmail.com" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Port</label>
                        <input v-model.number="inbox.mailbox_port" type="number" placeholder="993" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Encryption</label>
                        <select v-model="inbox.mailbox_encryption" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS / STARTTLS</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                        <input v-model="inbox.mailbox_username" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">App password</label>
                        <input v-model="mailboxPassword" type="password" :placeholder="inbox.has_mailbox_password ? 'Leave blank to keep current' : 'Enter app password'" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div v-if="inbox.mailbox_protocol === 'imap'">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Folder</label>
                        <input v-model="inbox.mailbox_folder" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-violet-100 pt-4">
                    <button
                        type="button"
                        class="rounded-lg border border-violet-200 bg-white px-4 py-2 text-sm font-medium text-violet-900 hover:bg-violet-50 disabled:opacity-60"
                        :disabled="mailboxTesting"
                        @click="testMailbox"
                    >
                        {{ mailboxTesting ? 'Testing…' : 'Test connection' }}
                    </button>
                    <button type="button" class="rounded-lg border border-violet-200 bg-white px-4 py-2 text-sm font-medium text-violet-900 hover:bg-violet-50" @click="pollMailbox">
                        Check for mail now
                    </button>
                    <span class="text-xs text-slate-500">Last checked: {{ formatPolledAt(inbox.last_polled_at) }}</span>
                </div>
                <p v-if="inbox.poll_error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                    {{ inbox.poll_error }}
                </p>
            </div>

            <div v-else class="space-y-4 rounded-xl border border-indigo-100 bg-indigo-50/30 p-5">
                <div class="rounded-lg border border-indigo-100 bg-white/80 p-4">
                    <p class="text-sm font-medium text-indigo-900">OAuth mailbox</p>
                    <p class="mt-1 text-xs text-indigo-800/90">Connect Google, Microsoft, or Zoho to sync mail without storing passwords.</p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="provider in oauthProviderList"
                        :key="provider.key"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ provider.label }}</p>
                            <p v-if="provider.help" class="mt-0.5 text-xs text-slate-500">{{ provider.help }}</p>
                            <p v-if="isProviderConnected(provider.key)" class="mt-1 text-xs font-medium text-emerald-700">
                                Connected as {{ inbox.oauth_connected_email }}
                            </p>
                            <p v-else-if="!provider.configured" class="mt-1 text-xs text-amber-700">Not configured on server</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="isProviderConnected(provider.key)"
                                type="button"
                                class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                                @click="disconnectOAuth"
                            >
                                Disconnect
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="!provider.configured"
                                @click="connectOAuth(provider.key)"
                            >
                                Connect
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="inbox.oauth_connected" class="flex flex-wrap items-center gap-3 border-t border-indigo-100 pt-4">
                    <button type="button" class="rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-medium text-indigo-900 hover:bg-indigo-50" @click="pollMailbox">
                        Check for mail now
                    </button>
                    <span class="text-xs text-slate-500">Last checked: {{ formatPolledAt(inbox.last_polled_at) }}</span>
                </div>
                <p v-if="inbox.poll_error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                    {{ inbox.poll_error }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="inbox.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                    Inbox is active
                </label>
                <div class="ml-auto flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save inbox</button>
                    <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-700 hover:bg-red-50" @click="emit('remove', inbox)">Remove</button>
                </div>
            </div>
        </form>
    </div>
</template>
