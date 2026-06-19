<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();

const form = useForm({
    email_enabled: props.settings.email_enabled,
    notify_ticket_assigned: props.settings.notify_ticket_assigned,
    notify_customer_reply: props.settings.notify_customer_reply,
    notify_sla_breach: props.settings.notify_sla_breach,
    notify_approval_pending: props.settings.notify_approval_pending,
});

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
        <div class="max-w-2xl agent-card">
            <form class="space-y-4" @submit.prevent="save">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.email_enabled" type="checkbox" class="rounded agent-border" />
                    {{ $t('settings_notifications.email_enabled') }}
                </label>

                <div class="border-t agent-border-subtle pt-4">
                    <p class="mb-3 text-sm font-medium agent-text">{{ $t('settings_notifications.alert_types') }}</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.notify_ticket_assigned" type="checkbox" class="rounded agent-border" />
                            {{ $t('settings_notifications.notify_ticket_assigned') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.notify_customer_reply" type="checkbox" class="rounded agent-border" />
                            {{ $t('settings_notifications.notify_customer_reply') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.notify_sla_breach" type="checkbox" class="rounded agent-border" />
                            {{ $t('settings_notifications.notify_sla_breach') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.notify_approval_pending" type="checkbox" class="rounded agent-border" />
                            {{ $t('settings_notifications.notify_approval_pending') }}
                        </label>
                    </div>
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                    {{ $t('settings_notifications.save_settings') }}
                </button>
            </form>
        </div>
    </SettingsPage>
</template>
