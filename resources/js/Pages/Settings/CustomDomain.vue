<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    customDomain: Object,
    billingPlan: String,
    billingFeatures: Array,
});

const { t } = useI18n();

const page = usePage();
const copiedField = ref('');

const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const domainForm = useForm({
    domain: props.customDomain?.custom_domain?.domain ?? '',
});

const preferencesForm = useForm({
    redirect_platform_domain: props.customDomain?.redirect_platform_domain ?? false,
});

const addDomain = () => {
    domainForm.post('/settings/custom-domain', {
        preserveScroll: true,
    });
};

const verifyDomain = () => {
    domainForm.post('/settings/custom-domain/verify', {
        preserveScroll: true,
    });
};

const savePreferences = () => {
    preferencesForm.put('/settings/custom-domain/preferences', {
        preserveScroll: true,
    });
};

const removeDomain = () => {
    askConfirm({
        title: t('settings_custom_domain.remove_custom_domain'),
        message: 'Visitors will only be able to use your default platform subdomain.',
        confirmLabel: 'Remove',
        variant: 'danger',
        action: () => domainForm.delete('/settings/custom-domain', { preserveScroll: true }),
    });
};

const copyValue = async (field, value) => {
    if (!value || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(value);
    copiedField.value = field;
    window.setTimeout(() => {
        if (copiedField.value === field) {
            copiedField.value = '';
        }
    }, 2000);
};

const statusLabel = (status) => {
    if (status === 'verified') {
        return 'Active';
    }

    if (status === 'failed') {
        return 'Verification failed';
    }

    return 'Pending verification';
};

const statusClass = (status) => {
    if (status === 'verified') {
        return 'bg-emerald-100 text-emerald-700 dark:text-emerald-300 ring-emerald-200';
    }

    if (status === 'failed') {
        return 'bg-red-100 text-red-700 dark:text-red-300 ring-red-200';
    }

    return 'bg-amber-100 text-amber-700 dark:text-amber-300 ring-amber-200';
};

const cnameHost = () => {
    const domain = props.customDomain?.custom_domain?.domain;

    if (!domain) {
        return 'support';
    }

    return domain.split('.')[0];
};
</script>

<template>
    <SettingsPage
        :title="$t('settings.custom_domain')"
        :description="$t('settings.descriptions.custom_domain')"
        info-section="custom_domain"
    >
        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('settings_custom_domain.default_url') }}</p>
                <p class="mt-2 break-all text-sm font-medium agent-text">{{ customDomain?.platform_url }}</p>
            </div>
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('settings_custom_domain.primary_url') }}</p>
                <p class="mt-2 break-all text-sm font-medium agent-text">{{ customDomain?.primary_url }}</p>
            </div>
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('settings_custom_domain.your_plan') }}</p>
                <p class="mt-2 text-sm font-medium agent-text">{{ billingPlan }}</p>
            </div>
        </div>

        <div v-if="!customDomain?.can_manage" class="space-y-6">
            <div class="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-6">
                <h2 class="text-base font-semibold text-amber-950 dark:text-amber-100">{{ $t('settings_custom_domain.upgrade_to_enterprise_to_connect_a_custom_domain') }}</h2>
                <p class="mt-2 text-sm text-amber-900/90 dark:text-amber-200/90">
                    You are on the <span class="font-medium">{{ billingPlan }}</span> plan. Enterprise lets you serve the helpdesk from a branded hostname while keeping
                    <span class="font-medium">{{ customDomain?.platform_url }}</span> working as a fallback.
                </p>
                <Link
                    href="/settings/billing?section=plans"
                    class="mt-4 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Compare plans
                </Link>
            </div>

            <div class="agent-card">
                <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.what_you_can_configure_on_enterprise') }}</h2>
                <ol class="mt-4 space-y-4 text-sm text-slate-700 dark:text-slate-300">
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">1</span>
                        <span>Add a hostname such as <span class="font-medium agent-text">support.yourcompany.com</span></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">2</span>
                        <span>Point a CNAME record to <span class="font-mono agent-text">{{ customDomain?.instructions?.cname_target }}</span></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">3</span>
                        <span>{{ $t('settings_custom_domain.verify_ownership_with_a_txt_record_then_optionally_redirect_the_defaul') }}</span>
                    </li>
                </ol>
            </div>
        </div>

        <template v-else>
            <div v-if="customDomain.custom_domain" class="mb-6 rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('settings.custom_domain') }}</p>
                        <p class="mt-1 text-sm font-medium agent-text">{{ customDomain.custom_domain.url }}</p>
                    </div>
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="statusClass(customDomain.custom_domain.status)"
                    >
                        {{ statusLabel(customDomain.custom_domain.status) }}
                    </span>
                </div>
            </div>

            <div v-if="!customDomain.custom_domain" class="agent-card">
                <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.add_your_custom_domain') }}</h2>
                <p class="mt-2 text-sm agent-text-muted">Use a subdomain you control, such as <span class="font-medium agent-text">support.anytrip.com</span>.</p>
                <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="addDomain">
                    <input
                        v-model="domainForm.domain"
                        type="text"
                        placeholder="support.yourcompany.com"
                        class="min-w-0 flex-1 rounded-lg border agent-border px-3 py-2.5 text-sm"
                    />
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="domainForm.processing"
                    >{{ $t('settings_custom_domain.continue') }}</button>
                </form>
                <p v-if="domainForm.errors.domain" class="mt-2 text-sm text-red-600">{{ domainForm.errors.domain }}</p>
            </div>

            <div v-else class="space-y-6">
                <div class="agent-card">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">1</span>
                        <div>
                            <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.route_traffic_to_helpdesk') }}</h2>
                            <p class="text-sm agent-text-muted">
                                {{ $t('settings_custom_domain.add_this_cname_record_in_your_dns_provider') }}
                                <span v-if="customDomain.instructions.dns_zone" class="font-medium text-slate-800 dark:text-slate-200">
                                    ({{ customDomain.instructions.dns_zone }})
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border agent-border agent-panel-muted p-3">
                            <p class="text-xs font-semibold uppercase agent-text-subtle">{{ $t('settings_custom_domain.type') }}</p>
                            <p class="mt-1 font-mono text-sm agent-text">{{ $t('settings_custom_domain.cname') }}</p>
                        </div>
                        <div class="rounded-lg border agent-border agent-panel-muted p-3">
                            <p class="text-xs font-semibold uppercase agent-text-subtle">{{ $t('settings_custom_domain.host') }}</p>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p class="font-mono text-sm agent-text">{{ customDomain.instructions.cname_dns_name }}</p>
                                <button type="button" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="copyValue('cname-host', customDomain.instructions.cname_dns_name)">
                                    {{ copiedField === 'cname-host' ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <div class="rounded-lg border agent-border agent-panel-muted p-3">
                            <p class="text-xs font-semibold uppercase agent-text-subtle">{{ $t('settings_custom_domain.target') }}</p>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p class="break-all font-mono text-sm agent-text">{{ customDomain.instructions.cname_target }}</p>
                                <button type="button" class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="copyValue('cname-target', customDomain.instructions.cname_target)">
                                    {{ copiedField === 'cname-target' ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="agent-card">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">2</span>
                        <div>
                            <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.verify_domain_ownership') }}</h2>
                            <p class="text-sm agent-text-muted">
                                {{ $t('settings_custom_domain.dns_txt_instructions') }}
                                <span v-if="customDomain.instructions.dns_zone" class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ customDomain.instructions.dns_zone }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-lg border agent-border agent-panel-muted p-3">
                            <p class="text-xs font-semibold uppercase agent-text-subtle">{{ $t('settings_custom_domain.txt_dns_name') }}</p>
                            <div class="mt-1 flex items-start justify-between gap-3">
                                <p class="break-all font-mono text-sm agent-text">{{ customDomain.instructions.txt_dns_name }}</p>
                                <button type="button" class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="copyValue('txt-dns-name', customDomain.instructions.txt_dns_name)">
                                    {{ copiedField === 'txt-dns-name' ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="mt-2 text-xs agent-text-subtle">
                                {{ $t('settings_custom_domain.txt_fqdn') }}: {{ customDomain.custom_domain.verification_host }}
                            </p>
                        </div>
                        <div class="rounded-lg border agent-border agent-panel-muted p-3">
                            <p class="text-xs font-semibold uppercase agent-text-subtle">{{ $t('settings_custom_domain.txt_value') }}</p>
                            <div class="mt-1 flex items-start justify-between gap-3">
                                <p class="break-all font-mono text-sm agent-text">{{ customDomain.custom_domain.verification_token }}</p>
                                <button type="button" class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="copyValue('txt-value', customDomain.custom_domain.verification_token)">
                                    {{ copiedField === 'txt-value' ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                            :disabled="domainForm.processing || customDomain.custom_domain.is_verified"
                            @click="verifyDomain"
                        >{{ $t('settings_custom_domain.verify_domain') }}</button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-200 dark:border-red-900/60 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:bg-red-950/40"
                            @click="removeDomain"
                        >{{ $t('settings_custom_domain.remove') }}</button>
                    </div>
                    <p v-if="domainForm.errors.domain" class="mt-2 text-sm text-red-600">{{ domainForm.errors.domain }}</p>
                </div>

                <div class="agent-card">
                    <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.redirect_default_url_optional') }}</h2>
                    <p class="mt-2 text-sm agent-text-muted">
                        Send visitors from <span class="font-medium agent-text">{{ customDomain.platform_domain }}</span> to your custom domain after verification.
                    </p>
                    <form class="mt-4 space-y-4" @submit.prevent="savePreferences">
                        <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                            <input
                                v-model="preferencesForm.redirect_platform_domain"
                                type="checkbox"
                                class="mt-0.5 rounded agent-border"
                                :disabled="!customDomain.custom_domain?.is_verified"
                            />
                            <span>{{ $t('settings_custom_domain.enable_redirect_to_custom_domain') }}</span>
                        </label>
                        <button
                            type="submit"
                            class="agent-btn-secondary disabled:opacity-60"
                            :disabled="preferencesForm.processing || !customDomain.custom_domain?.is_verified"
                        >{{ $t('settings_custom_domain.save_preference') }}</button>
                    </form>
                </div>

                <div class="rounded-xl border agent-border agent-panel-muted p-6">
                    <h2 class="text-sm font-semibold agent-text">{{ $t('settings_custom_domain.platform_operator_checklist') }}</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700 dark:text-slate-300">
                        <li v-for="note in customDomain.instructions.platform_operator_notes" :key="note">{{ note }}</li>
                        <li>{{ $t('settings_custom_domain.issue_https_certificates_for_verified_customer_hostnames') }}</li>
                    </ul>
                </div>
            </div>
        </template>

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
