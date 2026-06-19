<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
    emailTemplates: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    email_enabled: props.settings.email_enabled,
    notify_ticket_assigned: props.settings.notify_ticket_assigned,
    notify_customer_reply: props.settings.notify_customer_reply,
    notify_sla_breach: props.settings.notify_sla_breach,
    notify_approval_pending: props.settings.notify_approval_pending,
});

const alertOptions = [
    {
        key: 'notify_ticket_assigned',
        labelKey: 'settings_notifications.notify_ticket_assigned',
        descriptionKey: 'settings_notifications.notify_ticket_assigned_help',
    },
    {
        key: 'notify_customer_reply',
        labelKey: 'settings_notifications.notify_customer_reply',
        descriptionKey: 'settings_notifications.notify_customer_reply_help',
    },
    {
        key: 'notify_sla_breach',
        labelKey: 'settings_notifications.notify_sla_breach',
        descriptionKey: 'settings_notifications.notify_sla_breach_help',
    },
    {
        key: 'notify_approval_pending',
        labelKey: 'settings_notifications.notify_approval_pending',
        descriptionKey: 'settings_notifications.notify_approval_pending_help',
    },
];

const save = () => {
    form.put('/settings/notifications', { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="$t('common.notifications')"
        :description="$t('settings.descriptions.notifications')"
        info-section="notifications"
    >
        <div class="space-y-6">
            <div class="max-w-3xl agent-card">
                <form class="space-y-6" @submit.prevent="save">
                    <div>
                        <p class="text-sm font-semibold agent-text">{{ $t('settings_notifications.in_app_alerts') }}</p>
                        <p class="mt-1 text-sm agent-text-subtle" dir="auto">{{ $t('settings_notifications.in_app_alerts_help') }}</p>

                        <div class="mt-4 space-y-3">
                            <label
                                v-for="option in alertOptions"
                                :key="option.key"
                                class="flex items-start gap-3 rounded-xl border agent-border agent-panel-muted/60 px-4 py-3"
                            >
                                <input
                                    v-model="form[option.key]"
                                    type="checkbox"
                                    class="mt-0.5 rounded agent-border"
                                />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium agent-text">{{ $t(option.labelKey) }}</span>
                                    <span class="mt-0.5 block text-xs agent-text-subtle" dir="auto">{{ $t(option.descriptionKey) }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t agent-border-subtle pt-6">
                        <p class="text-sm font-semibold agent-text">{{ $t('settings_notifications.email_alerts') }}</p>
                        <p class="mt-1 text-sm agent-text-subtle" dir="auto">{{ $t('settings_notifications.email_alerts_help') }}</p>

                        <label class="mt-4 flex items-start gap-3 rounded-xl border agent-border agent-panel-muted/60 px-4 py-3">
                            <input v-model="form.email_enabled" type="checkbox" class="mt-0.5 rounded agent-border" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium agent-text">{{ $t('settings_notifications.email_enabled') }}</span>
                                <span class="mt-0.5 block text-xs agent-text-subtle" dir="auto">{{ $t('settings_notifications.email_enabled_help') }}</span>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="form.processing">
                        {{ $t('settings_notifications.save_settings') }}
                    </button>
                </form>
            </div>

            <div class="max-w-4xl overflow-hidden rounded-xl border agent-border agent-panel shadow-sm">
                <div class="border-b agent-border-subtle px-5 py-4">
                    <h2 class="text-base font-semibold agent-text">{{ $t('settings_notifications.email_templates_title') }}</h2>
                    <p class="mt-1 text-sm agent-text-subtle" dir="auto">{{ $t('settings_notifications.email_templates_help') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y agent-table-divider text-sm">
                        <thead class="agent-panel-muted">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('settings_notifications.template') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('common.subject') }}</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('settings_notifications.when_sent') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y agent-table-divider">
                            <tr v-for="template in emailTemplates" :key="template.id" class="agent-hover-row">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium agent-text">{{ template.name }}</p>
                                    <p class="mt-0.5 font-mono text-xs agent-text-subtle">{{ template.slug }}</p>
                                </td>
                                <td class="max-w-xs px-4 py-3 align-top agent-text-muted">{{ template.subject }}</td>
                                <td class="px-4 py-3 align-top agent-text-muted">{{ template.trigger || '—' }}</td>
                                <td class="px-4 py-3 align-top text-end">
                                    <Link :href="template.edit_path" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">
                                        {{ $t('common.edit') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SettingsPage>
</template>
