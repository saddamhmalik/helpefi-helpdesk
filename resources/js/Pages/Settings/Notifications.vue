<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    email_enabled: props.settings.email_enabled,
    notify_ticket_assigned: props.settings.notify_ticket_assigned,
    notify_customer_reply: props.settings.notify_customer_reply,
    notify_sla_breach: props.settings.notify_sla_breach,
});

const save = () => {
    form.put('/settings/notifications', { preserveScroll: true });
};
</script>

<template>
    <SettingsLayout
        title="Notifications"
        description="Configure in-app and email alerts for agents."
    >
        <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form class="space-y-4" @submit.prevent="save">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.email_enabled" type="checkbox" class="rounded border-slate-300" />
                    Send email notifications (requires mail configuration)
                </label>

                <div class="border-t border-slate-100 pt-4">
                    <p class="mb-3 text-sm font-medium text-slate-900">Alert types</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.notify_ticket_assigned" type="checkbox" class="rounded border-slate-300" />
                            Ticket assigned to an agent
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.notify_customer_reply" type="checkbox" class="rounded border-slate-300" />
                            Customer reply on a ticket
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.notify_sla_breach" type="checkbox" class="rounded border-slate-300" />
                            SLA breach detected
                        </label>
                    </div>
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                    Save settings
                </button>
            </form>
        </div>
    </SettingsLayout>
</template>
