<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useClipboard } from '../../composables/useClipboard.js';
import { computed } from 'vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    observability: Object,
    sso: Object,
});

const { t } = useI18n();

const { activeSection } = useSettingsSection({
    defaultSection: 'overview',
    sections: ['overview', 'policy', 'sso', 'audit'],
});

const securitySectionKeys = {
    overview: 'security_overview',
    policy: 'security_policy',
    sso: 'single_sign_on',
    audit: 'data_retention',
};

const pageMeta = computed(() => {
    const key = securitySectionKeys[activeSection.value] ?? 'security_overview';

    return {
        title: t(`settings.${key}`),
        description: t(`settings.descriptions.${key}`),
    };
});

const infoSection = computed(() => securitySectionKeys[activeSection.value] ?? 'security_overview');

const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const { copied: callbackCopied, copy: copyCallback } = useClipboard();
const { copied: acsCopied, copy: copyAcs } = useClipboard();
const { copied: metadataCopied, copy: copyMetadata } = useClipboard();

const form = useForm({
    mfa_required_for_agents: props.observability.settings.mfa_required_for_agents,
    audit_retention_days: props.observability.settings.audit_retention_days,
    closed_ticket_retention_days: props.observability.settings.closed_ticket_retention_days ?? '',
});

const save = () => {
    form.transform((data) => ({
        ...data,
        closed_ticket_retention_days: data.closed_ticket_retention_days === '' ? null : Number(data.closed_ticket_retention_days),
    })).put('/settings/security', { preserveScroll: true });
};

const purgeRetention = () => {
    askConfirm({
        title: t('settings_security.run_retention_purge'),
        message: 'This permanently deletes old audit logs and closed tickets. Continue?',
        confirmLabel: 'Run purge',
        variant: 'danger',
        action: () => router.post('/settings/security/purge', {}, { preserveScroll: true }),
    });
};

const adoptionPercent = computed(() => {
    const total = props.observability.mfa_adoption.total;
    if (! total) {
        return 0;
    }
    return Math.round((props.observability.mfa_adoption.enabled / total) * 100);
});

const ssoForm = useForm({
    sso_enabled: props.sso?.sso_enabled ?? false,
    sso_protocol: props.sso?.sso_protocol ?? 'oidc',
    sso_config: {
        preset: props.sso?.sso_config?.preset ?? 'google',
        client_id: props.sso?.sso_config?.client_id ?? '',
        client_secret: '',
        tenant_id: props.sso?.sso_config?.tenant_id ?? 'common',
        issuer: props.sso?.sso_config?.issuer ?? '',
        button_label: props.sso?.sso_config?.button_label ?? 'Sign in with SSO',
        auto_provision: props.sso?.sso_config?.auto_provision ?? false,
        allowed_domains: (props.sso?.sso_config?.allowed_domains ?? []).join('\n'),
        idp_entity_id: props.sso?.sso_config?.idp_entity_id ?? '',
        sso_url: props.sso?.sso_config?.sso_url ?? '',
        slo_url: props.sso?.sso_config?.slo_url ?? '',
        x509_cert: props.sso?.sso_config?.x509_cert ?? '',
    },
});

const saveSso = () => {
    ssoForm.transform((data) => ({
        ...data,
        sso_config: {
            ...data.sso_config,
            allowed_domains: String(data.sso_config.allowed_domains || '')
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
        },
    })).put('/settings/security/sso', { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="pageMeta.title"
        :description="pageMeta.description"
        :info-section="infoSection"
    >
        <div v-show="activeSection === 'overview'" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                            <p class="text-sm agent-text-subtle">{{ $t('settings_security.mfa_adoption') }}</p>
                            <p class="mt-1 text-2xl font-semibold agent-text">{{ adoptionPercent }}%</p>
                            <p class="text-xs agent-text-subtle">{{ observability.mfa_adoption.enabled }} of {{ observability.mfa_adoption.total }} agents</p>
                        </div>
                        <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                            <p class="text-sm agent-text-subtle">{{ $t('settings_security.audit_retention') }}</p>
                            <p class="mt-1 text-2xl font-semibold agent-text">{{ observability.settings.audit_retention_days }} days</p>
                        </div>
                        <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                            <p class="text-sm agent-text-subtle">{{ $t('settings_security.closed_ticket_retention') }}</p>
                            <p class="mt-1 text-2xl font-semibold agent-text">
                                {{ observability.settings.closed_ticket_retention_days ?? 'Disabled' }}
                            </p>
                        </div>
                    </div>

                    <div class="max-w-2xl agent-card">
                        <h2 class="text-lg font-medium agent-text">{{ $t('settings_security.7-day_event_summary') }}</h2>
                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="(total, event) in observability.audit_summary"
                                :key="event"
                                class="flex items-center justify-between rounded-lg border agent-border-subtle px-3 py-2 text-sm"
                            >
                                <span class="text-slate-700 dark:text-slate-300">{{ event }}</span>
                                <span class="font-medium agent-text">{{ total }}</span>
                            </li>
                            <li v-if="!Object.keys(observability.audit_summary).length" class="text-sm agent-text-subtle">{{ $t('settings_security.no_audit_events_yet') }}</li>
                        </ul>
                    </div>
                </div>

        <div v-show="activeSection === 'policy'" class="max-w-2xl agent-card">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings.security_policy') }}</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="save">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.mfa_required_for_agents" type="checkbox" class="rounded agent-border" />
                            Require two-factor authentication for all agents
                        </label>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.audit_log_retention_days') }}</label>
                            <input v-model.number="form.audit_retention_days" type="number" min="7" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.closed_ticket_retention_days') }}</label>
                            <input v-model="form.closed_ticket_retention_days" type="number" min="30" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :placeholder="$t('settings_security.leave_blank_to_disable')" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('settings_security.save_settings') }}</button>
                            <button type="button" class="rounded-lg border border-red-200 dark:border-red-900/60 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:bg-red-950/40" @click="purgeRetention">{{ $t('settings_security.run_retention_purge') }}</button>
                        </div>
                    </form>
                </div>

        <div v-show="activeSection === 'sso'" class="max-w-2xl agent-card">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.single_sign_on') }}</h2>
            <PlanFeatureBanner feature="sso" class="!mb-4" />
            <form class="mt-4 space-y-4" @submit.prevent="saveSso">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="ssoForm.sso_enabled" type="checkbox" class="rounded agent-border" />
                    Enable SSO for agents
                </label>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.protocol') }}</label>
                    <select v-model="ssoForm.sso_protocol" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="oidc">{{ $t('settings_security.oidc_google_azure_okta') }}</option>
                        <option value="saml">{{ $t('settings_security.saml_2_0') }}</option>
                    </select>
                </div>
                <template v-if="ssoForm.sso_protocol === 'oidc'">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.provider_preset') }}</label>
                        <select v-model="ssoForm.sso_config.preset" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option value="google">{{ $t('settings_security.google_workspace') }}</option>
                            <option value="azure">{{ $t('settings_security.microsoft_entra_id') }}</option>
                            <option value="oidc">{{ $t('settings_security.generic_oidc') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.client_id') }}</label>
                        <input v-model="ssoForm.sso_config.client_id" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.client_secret') }}</label>
                        <input v-model="ssoForm.sso_config.client_secret" type="password" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" :placeholder="sso?.sso_config?.has_client_secret ? 'Leave blank to keep current secret' : ''" />
                    </div>
                    <div v-if="ssoForm.sso_config.preset === 'azure'">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.tenant_id') }}</label>
                        <input v-model="ssoForm.sso_config.tenant_id" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div v-if="ssoForm.sso_config.preset === 'oidc'">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.issuer_url') }}</label>
                        <input v-model="ssoForm.sso_config.issuer" type="url" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.idp_entity_id') }}</label>
                        <input v-model="ssoForm.sso_config.idp_entity_id" type="text" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.sso_url') }}</label>
                        <input v-model="ssoForm.sso_config.sso_url" type="url" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.idp_x509_certificate') }}</label>
                        <textarea v-model="ssoForm.sso_config.x509_cert" rows="4" class="w-full rounded-lg border agent-border px-3 py-2 text-sm font-mono" />
                    </div>
                </template>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_security.allowed_email_domains_one_per_line') }}</label>
                    <textarea v-model="ssoForm.sso_config.allowed_domains" rows="3" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" placeholder="company.com" />
                </div>
                <div class="space-y-3 rounded-lg border agent-border-subtle bg-slate-50 p-4 dark:bg-slate-950/50">
                    <div>
                        <p class="text-sm font-medium agent-text">{{ $t('settings_security.idp_redirect_urls') }}</p>
                        <p class="mt-1 text-xs agent-text-muted">{{ $t('settings_security.idp_redirect_urls_help') }}</p>
                    </div>
                    <div v-if="ssoForm.sso_protocol === 'oidc' && sso?.callback_url">
                        <label class="mb-1 block text-xs font-medium agent-text-subtle">{{ $t('settings_security.oidc_callback_uri') }}</label>
                        <div class="flex gap-2">
                            <input :value="sso.callback_url" type="text" readonly class="min-w-0 flex-1 rounded-lg border agent-border bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" />
                            <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm" @click="copyCallback(sso.callback_url)">
                                {{ callbackCopied ? $t('settings_security.copied') : $t('settings_security.copy') }}
                            </button>
                        </div>
                    </div>
                    <template v-else-if="ssoForm.sso_protocol === 'saml'">
                        <div v-if="sso?.acs_url">
                            <label class="mb-1 block text-xs font-medium agent-text-subtle">{{ $t('settings_security.saml_acs_url') }}</label>
                            <div class="flex gap-2">
                                <input :value="sso.acs_url" type="text" readonly class="min-w-0 flex-1 rounded-lg border agent-border bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" />
                                <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm" @click="copyAcs(sso.acs_url)">
                                    {{ acsCopied ? $t('settings_security.copied') : $t('settings_security.copy') }}
                                </button>
                            </div>
                        </div>
                        <div v-if="sso?.metadata_url">
                            <label class="mb-1 block text-xs font-medium agent-text-subtle">{{ $t('settings_security.saml_metadata_url') }}</label>
                            <div class="flex gap-2">
                                <input :value="sso.metadata_url" type="text" readonly class="min-w-0 flex-1 rounded-lg border agent-border bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" />
                                <button type="button" class="shrink-0 rounded-lg border agent-border px-3 py-2 text-sm" @click="copyMetadata(sso.metadata_url)">
                                    {{ metadataCopied ? $t('settings_security.copied') : $t('settings_security.copy') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="ssoForm.processing">{{ $t('settings_security.save_sso_settings') }}</button>
            </form>
        </div>

        <div v-show="activeSection === 'audit'" class="max-w-2xl agent-card">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-medium agent-text">{{ $t('settings_security.audit_trail') }}</h2>
                            <p class="mt-1 text-sm agent-text-muted">{{ $t('settings_security.full_activity_history_is_available_on_the_audit_logs_page') }}</p>
                        </div>
                        <Link href="/settings/audit-logs" class="agent-btn-secondary">
                            View audit logs
                        </Link>
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
    </SettingsPage>
</template>
