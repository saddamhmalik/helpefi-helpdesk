<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppChipSelect from '../../Components/AppChipSelect.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    webhooks: Array,
    meta: Object,
    connections: Array,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const page = usePage();
const showForm = ref(false);
const editingWebhook = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();
const revealedSecret = computed(() => page.props.flash?.webhook_secret ?? null);
const revealedIntegrationSecret = computed(() => page.props.flash?.integration_secret ?? null);

const integrationSections = ['webhooks', 'slack', 'jira', 'linear', 'shopify', 'hubspot', 'salesforce', 'teams', 'zapier'];

const { activeSection } = useSettingsSection({
    defaultSection: 'webhooks',
    sections: integrationSections,
});

const pageMeta = computed(() => {
    const section = activeSection.value;
    const key = section === 'teams' ? 'microsoft_teams' : section;

    return {
        title: t(`settings.${key}`),
        description: t(`settings.descriptions.${key}`),
    };
});

const infoSection = computed(() => {
    const section = activeSection.value;

    return section === 'teams' ? 'microsoft_teams' : section;
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

const shopifyForm = useForm({
    shop: connection('shopify').config?.shop ?? '',
    access_token: '',
    is_active: connection('shopify').is_active ?? false,
});

const hubspotForm = useForm({
    access_token: '',
    is_active: connection('hubspot').is_active ?? false,
});

const salesforceForm = useForm({
    consumer_key: '',
    consumer_secret: '',
    username: connection('salesforce').config?.username ?? '',
    password: '',
    security_token: '',
    login_url: connection('salesforce').config?.login_url ?? 'https://login.salesforce.com',
    is_active: connection('salesforce').is_active ?? false,
});

const teamsForm = useForm({
    webhook_url: connection('microsoft_teams').config?.webhook_url ?? '',
    is_active: connection('microsoft_teams').is_active ?? false,
});

const zapierForm = useForm({
    is_active: connection('zapier').is_active ?? false,
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
        title: t('settings_integrations.delete_webhook'),
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

const saveShopify = () => shopifyForm.put('/settings/integrations/shopify', { preserveScroll: true });
const saveHubspot = () => hubspotForm.put('/settings/integrations/hubspot', { preserveScroll: true });
const saveSalesforce = () => salesforceForm.put('/settings/integrations/salesforce', { preserveScroll: true });
const saveTeams = () => teamsForm.put('/settings/integrations/teams', { preserveScroll: true });
const saveZapier = () => zapierForm.put('/settings/integrations/zapier', { preserveScroll: true });

const testSlack = () => {
    router.post('/settings/integrations/slack/test', {}, { preserveScroll: true });
};

const formatTime = (value) => value ? formatDateTime(value) : t('common.never');
const inputClass = 'w-full rounded-lg border agent-border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <SettingsPage :title="pageMeta.title" :description="pageMeta.description" :info-section="infoSection">
        <PlanFeatureBanner feature="integrations" />

        <template #actions>
            <button
                v-if="activeSection === 'webhooks'"
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                @click="openCreate"
            >{{ $t('settings_integrations.add_webhook') }}</button>
        </template>

        <div v-if="revealedSecret" class="mb-4 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <p class="font-medium">{{ $t('settings_integrations.webhook_signing_secret_copy_now') }}</p>
            <p class="mt-1 break-all font-mono text-xs">{{ revealedSecret }}</p>
        </div>

        <div v-if="revealedIntegrationSecret" class="mb-4 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <p class="font-medium">{{ $t('settings_integrations.inbound_webhook_secret_copy_now') }}</p>
            <p class="mt-1 break-all font-mono text-xs">{{ revealedIntegrationSecret }}</p>
        </div>

        <div v-show="activeSection === 'webhooks'" class="space-y-4">
                    <div v-for="webhook in webhooks" :key="webhook.id" class="agent-card transition hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-semibold agent-text">{{ webhook.name }}</h2>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="webhook.is_active ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'">
                                        {{ webhook.is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm agent-text-subtle">{{ webhook.url }}</p>
                                <p class="mt-2 text-xs agent-text-subtle">
                                    Last delivery: {{ formatTime(webhook.last_delivered_at) }}
                                    <span v-if="webhook.last_status_code"> · HTTP {{ webhook.last_status_code }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border agent-border px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="testWebhook(webhook.id)">{{ $t('settings_integrations.send_test') }}</button>
                                <button type="button" class="rounded-lg border agent-border px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="regenerateSecret(webhook.id)">{{ $t('settings_integrations.new_secret') }}</button>
                                <AppRowActions>
                                    <AppEditAction :label="$t('settings_integrations.edit')" @click="openEdit(webhook)" />
                                    <AppDeleteAction :label="$t('settings_integrations.delete')" @click="destroyWebhook(webhook)" />
                                </AppRowActions>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="event in webhook.events" :key="event" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-0.5 text-xs text-slate-700 dark:text-slate-300">
                                {{ eventLabel(event) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!webhooks.length" class="rounded-xl border border-dashed agent-border bg-white dark:bg-slate-900 px-6 py-12 text-center text-sm agent-text-subtle">
                        No webhooks configured yet.
                    </div>
                </div>

        <form v-show="activeSection === 'slack'" class="max-w-2xl agent-card" @submit.prevent="saveSlack">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings_integrations.slack_notifications') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.post_ticket_events_to_a_slack_channel_using_an_incoming_webhook') }}</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.incoming_webhook_url') }}</label>
                            <input v-model="slackForm.webhook_url" type="url" :class="inputClass" placeholder="https://hooks.slack.com/services/..." />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.channel_override') }}</label>
                            <input v-model="slackForm.channel" type="text" :class="inputClass" :placeholder="$t('settings_integrations.support_optional')" />
                        </div>
                        <AppChipSelect v-model="slackForm.events" :label="$t('settings_integrations.events')" :options="slackEventOptions" />
                        <AppToggle v-model="slackForm.is_active" :label="$t('common.active')" />
                        <p v-if="connection('slack').last_delivered_at" class="text-xs agent-text-subtle">
                            Last delivery: {{ formatTime(connection('slack').last_delivered_at) }}
                        </p>
                        <p v-if="connection('slack').last_error" class="text-xs text-red-600">{{ connection('slack').last_error }}</p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="slackForm.processing">{{ $t('common.save') }}</button>
                        <button type="button" class="agent-btn-secondary" @click="testSlack">{{ $t('settings_integrations.send_test') }}</button>
                    </div>
                </form>

        <form v-show="activeSection === 'jira'" class="max-w-2xl agent-card" @submit.prevent="saveJira">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings.jira') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.create_and_link_jira_issues_from_tickets_status_changes_sync_both_ways') }}</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.site_url') }}</label>
                            <input v-model="jiraForm.site_url" type="url" :class="inputClass" placeholder="https://yourcompany.atlassian.net" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.email') }}</label>
                            <input v-model="jiraForm.email" type="email" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.api_token') }}</label>
                            <input v-model="jiraForm.api_token" type="password" :class="inputClass" :placeholder="connection('jira').config?.has_api_token ? 'Saved — leave blank to keep' : ''" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.project_key') }}</label>
                            <input v-model="jiraForm.project_key" type="text" :class="inputClass" :placeholder="$t('settings_integrations.sup')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.issue_type') }}</label>
                            <input v-model="jiraForm.issue_type" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.done_transition') }}</label>
                            <input v-model="jiraForm.done_transition" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.reopen_transition') }}</label>
                            <input v-model="jiraForm.reopen_transition" type="text" :class="inputClass" />
                        </div>
                        <div class="md:col-span-2">
                            <AppToggle v-model="jiraForm.is_active" :label="$t('common.active')" />
                        </div>
                        <div class="md:col-span-2 rounded-lg agent-panel-muted p-3 text-xs agent-text-muted">
                            <p class="font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.inbound_webhook_url') }}</p>
                            <p class="mt-1 break-all font-mono">{{ meta.inbound_urls?.jira }}</p>
                            <p class="mt-2">Send header <span class="font-mono">{{ $t('settings_integrations.x-integration-secret') }}</span> with the secret shown after save.</p>
                        </div>
                    </div>

                    <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="jiraForm.processing">{{ $t('common.save') }}</button>
                </form>

        <form v-show="activeSection === 'linear'" class="max-w-2xl agent-card" @submit.prevent="saveLinear">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings.linear') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.create_and_link_linear_issues_from_tickets_status_changes_sync_both_wa') }}</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.api_key') }}</label>
                            <input v-model="linearForm.api_key" type="password" :class="inputClass" :placeholder="connection('linear').config?.has_api_key ? 'Saved — leave blank to keep' : ''" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.team_id') }}</label>
                            <input v-model="linearForm.team_id" type="text" :class="inputClass" />
                            <p class="mt-1 text-xs agent-text-subtle">{{ $t('settings_integrations.linear_team_id_hint') }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.done_state') }}</label>
                            <input v-model="linearForm.done_state" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.open_state') }}</label>
                            <input v-model="linearForm.open_state" type="text" :class="inputClass" />
                        </div>
                        <div class="md:col-span-2">
                            <AppToggle v-model="linearForm.is_active" :label="$t('common.active')" />
                        </div>
                        <div class="md:col-span-2 rounded-lg agent-panel-muted p-3 text-xs agent-text-muted">
                            <p class="font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.inbound_webhook_url') }}</p>
                            <p class="mt-1 break-all font-mono">{{ meta.inbound_urls?.linear }}</p>
                            <p class="mt-2">Linear sends <span class="font-mono">{{ $t('settings_integrations.linear-signature') }}</span> HMAC signed with the secret shown after save.</p>
                        </div>
                    </div>

                    <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="linearForm.processing">{{ $t('common.save') }}</button>
                </form>

        <form v-show="activeSection === 'shopify'" class="max-w-2xl agent-card" @submit.prevent="saveShopify">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.shopify') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.connect_your_shopify_store_using_the_official_shopify_api_client') }}</p>
            <div class="mt-4 space-y-4">
                <input v-model="shopifyForm.shop" type="text" :placeholder="$t('settings_integrations.your-store_myshopify_com')" :class="inputClass" />
                <input v-model="shopifyForm.access_token" type="password" :placeholder="$t('settings_integrations.admin_api_access_token')" :class="inputClass" />
                <AppToggle v-model="shopifyForm.is_active" :label="$t('common.active')" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="shopifyForm.processing">{{ $t('common.save') }}</button>
            </div>
        </form>

        <form v-show="activeSection === 'hubspot'" class="max-w-2xl agent-card" @submit.prevent="saveHubspot">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings_integrations.hubspot_crm') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.enrich_tickets_with_hubspot_contact_data_via_hubspot_api-client') }}</p>
            <div class="mt-4 space-y-4">
                <input v-model="hubspotForm.access_token" type="password" :placeholder="$t('settings_integrations.private_app_access_token')" :class="inputClass" />
                <AppToggle v-model="hubspotForm.is_active" :label="$t('common.active')" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="hubspotForm.processing">{{ $t('common.save') }}</button>
            </div>
        </form>

        <form v-show="activeSection === 'salesforce'" class="max-w-2xl agent-card" @submit.prevent="saveSalesforce">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.salesforce') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.connect_salesforce_using_omniphx_forrest_for_contact_lookup') }}</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <input v-model="salesforceForm.username" type="text" :placeholder="$t('settings_integrations.username')" :class="inputClass" />
                <input v-model="salesforceForm.password" type="password" :placeholder="$t('settings.password')" :class="inputClass" />
                <input v-model="salesforceForm.security_token" type="password" :placeholder="$t('settings_integrations.security_token')" :class="inputClass" />
                <input v-model="salesforceForm.login_url" type="url" :placeholder="$t('settings_integrations.login_url')" :class="inputClass" />
                <div class="md:col-span-2"><AppToggle v-model="salesforceForm.is_active" :label="$t('common.active')" /></div>
                <div class="md:col-span-2"><button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="salesforceForm.processing">{{ $t('common.save') }}</button></div>
            </div>
        </form>

        <form v-show="activeSection === 'teams'" class="max-w-2xl agent-card" @submit.prevent="saveTeams">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.microsoft_teams') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.post_ticket_notifications_to_a_teams_incoming_webhook') }}</p>
            <div class="mt-4 space-y-4">
                <input v-model="teamsForm.webhook_url" type="url" :placeholder="$t('settings_integrations.incoming_webhook_url')" :class="inputClass" />
                <AppToggle v-model="teamsForm.is_active" :label="$t('common.active')" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="teamsForm.processing">{{ $t('common.save') }}</button>
            </div>
        </form>

        <form v-show="activeSection === 'zapier'" class="max-w-2xl agent-card" @submit.prevent="saveZapier">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.zapier') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_integrations.use_outbound_webhooks_or_the_subscribe_url_below_in_zapier_triggers') }}</p>
            <div class="mt-4 space-y-4">
                <div class="rounded-lg agent-panel-muted p-3 text-xs agent-text-muted">
                    <p class="font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.hook_url') }}</p>
                    <p class="mt-1 break-all font-mono">{{ meta.zapier_hook_url }}</p>
                </div>
                <AppToggle v-model="zapierForm.is_active" :label="$t('common.active')" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="zapierForm.processing">{{ $t('common.save') }}</button>
            </div>
        </form>

        <AppModal
            :open="showForm"
            :title="editingWebhook ? 'Edit webhook' : 'Add webhook'"
            :description="$t('settings_integrations.receive_signed_post_requests_when_ticket_events_occur')"
            size="md"
            @close="closeForm"
        >
            <form id="webhook-form" class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_integrations.url') }}</label>
                    <input v-model="form.url" type="url" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="https://example.com/webhooks/helpdesk" />
                </div>
                <AppChipSelect v-model="form.events" :label="$t('settings_integrations.events')" :options="webhookEventOptions" />
                <p v-if="!form.events.length" class="text-xs text-amber-600">{{ $t('settings_integrations.select_at_least_one_event') }}</p>
                <AppToggle v-model="form.is_active" :label="$t('common.active')" />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeForm">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="webhook-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing || !form.events.length">{{ $t('common.save') }}</button>
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
