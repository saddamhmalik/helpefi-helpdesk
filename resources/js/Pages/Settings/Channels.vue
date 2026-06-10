<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    channels: Array,
    appUrl: String,
    chatAvailability: Object,
});

const { t } = useI18n();

const chatChannel = computed(() => props.channels.find((channel) => channel.type === 'chat'));

const embedSnippet = computed(() => {
    const key = chatChannel.value?.settings?.widget_key;
    const base = (props.appUrl || '').replace(/\/$/, '');

    if (!key || !base) {
        return '';
    }

    return `<script src="${base}/widget/helpdesk.js" data-key="${key}" defer><\/script>`;
});

const save = (channel) => {
    router.put(`/settings/channels/${channel.id}`, {
        is_active: channel.is_active,
        settings: channel.settings,
    }, { preserveScroll: true });
};

const originsText = (channel) => (channel.settings?.allowed_origins || ['*']).join('\n');

const updateOrigins = (channel, value) => {
    channel.settings.allowed_origins = value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
};
</script>

<template>
    <SettingsPage :title="$t('settings.groups.channels')" :description="$t('settings_channels.manage_omnichannel_sources_for_incoming_conversations')">
        <PlanFeatureBanner feature="channels" />

        <p class="-mt-4 mb-6 text-sm text-slate-600">
            Email inboxes and outbound SMTP are configured on
            <Link href="/settings/email" class="text-blue-600 hover:text-blue-700">Email settings</Link>.
            WhatsApp and SMS use
            <Link href="/settings/messaging" class="text-blue-600 hover:text-blue-700">Twilio messaging</Link>.
        </p>

        <div class="space-y-4">
            <div
                v-for="channel in channels.filter((c) => c.type !== 'email')"
                :key="channel.id"
                class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ channel.name }}</h2>
                        <p class="text-sm text-slate-500">{{ channel.type }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="channel.is_active" type="checkbox" class="rounded border-slate-300" />
                        {{ $t('common.active') }}
                    </label>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="save(channel)">
                    <template v-if="channel.type === 'chat'">
                        <div
                            v-if="chatAvailability"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="chatAvailability.online
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-amber-200 bg-amber-50 text-amber-900'"
                        >
                            <span class="font-medium">{{ chatAvailability.online ? 'Online now' : 'Offline now' }}</span>
                            <span class="text-current/80"> — {{ chatAvailability.reason }}</span>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.widget_key') }}</label>
                            <input
                                :value="channel.settings.widget_key"
                                type="text"
                                readonly
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-600"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.greeting') }}</label>
                            <input v-model="channel.settings.greeting" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.offline_message') }}</label>
                            <textarea v-model="channel.settings.offline_message" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.offline_mode') }}</label>
                            <select v-model="channel.settings.offline_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="never">{{ $t('settings_channels.always_online') }}</option>
                                <option value="business_hours">{{ $t('settings_channels.offline_outside_business_hours') }}</option>
                                <option value="always">{{ $t('settings_channels.always_offline_email_ticket_only') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.allowed_origins_one_per_line_use_for_any') }}</label>
                            <textarea
                                :value="originsText(channel)"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                                @input="updateOrigins(channel, $event.target.value)"
                            />
                        </div>

                        <div v-if="embedSnippet">
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_channels.embed_snippet') }}</label>
                            <textarea :value="embedSnippet" readonly rows="2" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600" />
                        </div>
                    </template>

                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $t('common.save') }}</button>
                </form>
            </div>
        </div>
    </SettingsPage>
</template>
