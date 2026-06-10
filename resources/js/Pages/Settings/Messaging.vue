<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    messaging: Object,
});

const { t } = useI18n();

const form = useForm({
    is_active: props.messaging.is_active,
    account_sid: props.messaging.account_sid,
    auth_token: '',
    whatsapp_from: props.messaging.whatsapp_from,
    sms_from: props.messaging.sms_from,
});

const save = () => form.put('/settings/messaging', { preserveScroll: true });
</script>

<template>
    <SettingsPage :title="$t('settings.whatsapp_sms')" :description="$t('settings_messaging.twilio_messaging_for_whatsapp_and_sms_channels')">
        <PlanFeatureBanner feature="channels" />

        <p class="-mt-4 mb-6 text-sm text-slate-600">
            Enable WhatsApp and SMS channels on
            <Link href="/settings/channels" class="text-blue-600 hover:text-blue-700">{{ $t('settings.groups.channels') }}</Link>
            after configuring Twilio below.
        </p>

        <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form class="space-y-4" @submit.prevent="save">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                    Enable Twilio messaging
                </label>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_messaging.account_sid') }}</label>
                    <input v-model="form.account_sid" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_messaging.auth_token') }}</label>
                    <input v-model="form.auth_token" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" :placeholder="messaging.has_auth_token ? 'Leave blank to keep current token' : ''" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_messaging.whatsapp_sender') }}</label>
                    <input v-model="form.whatsapp_from" type="text" placeholder="+14155238886" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_messaging.sms_sender') }}</label>
                    <input v-model="form.sms_from" type="text" placeholder="+14155238886" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>

                <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                    <p class="font-medium text-slate-800">{{ $t('settings_messaging.webhook_url') }}</p>
                    <p class="mt-1 break-all font-mono text-xs">{{ messaging.webhook_url }}?token={{ messaging.webhook_token }}</p>
                </div>

                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('settings_messaging.save_messaging_settings') }}</button>
            </form>
        </div>
    </SettingsPage>
</template>
