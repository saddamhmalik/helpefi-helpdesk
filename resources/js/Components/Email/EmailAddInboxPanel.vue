<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MailOAuthSetupGuide from './MailOAuthSetupGuide.vue';

const props = defineProps({
    provider: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    mailboxProviders: { type: Object, default: () => ({}) },
    oauthProviders: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['cancel', 'created']);

const { t } = useI18n();
const page = usePage();

const defaultBrandId = props.brands?.[0]?.id ?? null;

const form = useForm({
    name: '',
    address: '',
    brand_id: defaultBrandId,
    inbound_method: props.provider.method,
    is_active: true,
    mailbox_provider: props.provider.mailbox_provider ?? null,
    mailbox_protocol: props.provider.mailbox_protocol ?? 'imap',
    mailbox_host: '',
    mailbox_port: props.provider.mailbox_port ?? null,
    mailbox_encryption: props.provider.mailbox_encryption ?? 'ssl',
    mailbox_username: '',
    mailbox_password: '',
    mailbox_folder: 'INBOX',
});

const providerHelp = computed(() => {
    if (!form.mailbox_provider) {
        return null;
    }

    return props.mailboxProviders?.[form.mailbox_provider]?.help ?? null;
});

const providerOptions = computed(() => Object.entries(props.mailboxProviders ?? {}).map(([key, item]) => ({ key, ...item })));

const applyMailboxProvider = () => {
    const preset = props.mailboxProviders?.[form.mailbox_provider];
    if (!preset) {
        return;
    }

    form.mailbox_protocol = preset.protocol ?? form.mailbox_protocol ?? 'imap';
    form.mailbox_host = preset.host ?? form.mailbox_host;
    form.mailbox_port = preset.port ?? form.mailbox_port;
    form.mailbox_encryption = preset.encryption ?? form.mailbox_encryption ?? 'ssl';
    form.mailbox_folder = preset.folder ?? form.mailbox_folder ?? 'INBOX';
};

const syncDefaults = () => {
    if (props.provider.method !== 'poll') {
        return;
    }

    form.mailbox_username = form.mailbox_username || form.address;

    if (!form.mailbox_provider && form.address?.includes('@gmail.com')) {
        form.mailbox_provider = 'gmail';
        applyMailboxProvider();
    }
};

watch(() => form.address, syncDefaults);

watch(() => props.provider.key, () => {
    form.inbound_method = props.provider.method;
    form.mailbox_provider = props.provider.mailbox_provider ?? null;
    form.mailbox_protocol = props.provider.mailbox_protocol ?? 'imap';
    form.mailbox_encryption = props.provider.mailbox_encryption ?? 'ssl';
    syncDefaults();
}, { immediate: true });

const submitLabel = computed(() => {
    if (props.provider.method === 'oauth') {
        return t('settings_email.create_and_connect', { provider: props.provider.label });
    }

    return t('settings_email.create_inbox');
});

const submit = () => {
    form.post('/settings/email/inboxes', {
        preserveScroll: true,
        onSuccess: () => {
            const createdId = page.props.flash?.created_inbox_id;

            emit('created', {
                id: createdId,
                oauthProvider: props.provider.oauth_provider ?? null,
            });

            if (createdId && props.provider.oauth_provider) {
                window.location.href = `/settings/email/inboxes/${createdId}/oauth/${props.provider.oauth_provider}`;
            }
        },
    });
};
</script>

<template>
    <div class="rounded-2xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-blue-200/80 px-5 py-4 dark:border-blue-900/50">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $t('settings_email.new_inbox') }}</p>
                <h3 class="mt-1 text-lg font-semibold agent-text">{{ provider.label }}</h3>
                <p class="mt-1 text-sm agent-text-muted">{{ provider.setupHint }}</p>
            </div>
            <button type="button" class="rounded-lg border agent-border bg-white px-3 py-1.5 text-sm agent-text-muted transition agent-hover-surface dark:bg-slate-900" @click="emit('cancel')">
                {{ $t('common.cancel') }}
            </button>
        </div>

        <form class="space-y-4 px-5 py-5" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium agent-text">{{ $t('settings_email.display_name') }}</label>
                    <input v-model="form.name" type="text" required :placeholder="$t('nav.sections.support')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium agent-text">{{ $t('settings_email.email_address') }}</label>
                    <input v-model="form.address" type="email" required :placeholder="$t('settings_email.support_company_com')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('settings_email.brand') }}</label>
                <select v-model="form.brand_id" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm sm:max-w-xs">
                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                </select>
            </div>

            <div v-if="provider.method === 'poll'" class="space-y-4 rounded-xl border agent-border agent-panel p-4">
                <div>
                    <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.email_provider') }}</label>
                    <select v-model="form.mailbox_provider" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" @change="applyMailboxProvider">
                        <option :value="null">{{ $t('components.choose_your_provider_ellipsis') }}</option>
                        <option v-for="item in providerOptions" :key="item.key" :value="item.key">{{ item.label }}</option>
                    </select>
                    <p v-if="providerHelp" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">{{ providerHelp }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.username') }}</label>
                        <input v-model="form.mailbox_username" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium agent-text">{{ $t('components.app_password') }}</label>
                        <input v-model="form.mailbox_password" type="password" :placeholder="$t('components.app_password_enter')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                </div>
            </div>

            <div v-else-if="provider.method === 'oauth'" class="space-y-4">
                <MailOAuthSetupGuide
                    v-if="provider.oauth_provider"
                    :provider="provider.oauth_provider"
                />
            </div>

            <div v-else class="rounded-xl border agent-border agent-panel p-4 text-sm agent-text-muted">
                {{ $t('settings_email.webhook_setup_hint') }}
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t agent-border-subtle pt-4">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">
                    {{ submitLabel }}
                </button>
                <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm agent-text-muted agent-hover-surface" @click="emit('cancel')">
                    {{ $t('common.cancel') }}
                </button>
            </div>
        </form>
    </div>
</template>
