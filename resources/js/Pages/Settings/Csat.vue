<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();

const form = useForm({
    enabled: props.settings.enabled,
    comment_required: props.settings.comment_required,
    email_enabled: props.settings.email_enabled,
});

const save = () => {
    form.put('/settings/csat', { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="$t('settings_csat.customer_satisfaction')"
        :description="$t('settings_csat.configure_post-resolution_surveys_on_the_customer_portal_and_by_email')"
    >
        <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form class="space-y-4" @submit.prevent="save">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                    Enable CSAT surveys on closed tickets
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.comment_required" type="checkbox" class="rounded border-slate-300" :disabled="!form.enabled" />
                    Require a comment with the rating on the portal
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.email_enabled" type="checkbox" class="rounded border-slate-300" :disabled="!form.enabled" />
                    Send CSAT survey email when a ticket is resolved or closed
                </label>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('settings_csat.save_settings') }}</button>
            </form>
        </div>
    </SettingsPage>
</template>
