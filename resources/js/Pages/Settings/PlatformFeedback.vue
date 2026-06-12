<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    types: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    type: 'feedback',
    subject: '',
    body: '',
});

const submit = () => {
    form.post('/settings/platform-feedback', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'body');
            form.type = 'feedback';
        },
    });
};
</script>

<template>
    <SettingsPage
        :title="$t('settings.platform_feedback')"
        :description="$t('settings.descriptions.platform_feedback')"
    >
        <div class="max-w-2xl agent-card">
            <div v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                {{ flashSuccess }}
            </div>

            <p class="text-sm agent-text-muted">
                {{ $t('settings_platform_feedback.submissions_go_directly_to_the_platform_team_they_are_not_visible_to_o') }}
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="feedback-type">{{ $t('settings_platform_feedback.type') }}</label>
                    <select
                        id="feedback-type"
                        v-model="form.type"
                        class="w-full rounded-lg border agent-border px-3 py-2.5 text-sm agent-text shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    >
                        <option v-for="(label, value) in types" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="feedback-subject">{{ $t('settings_platform_feedback.subject') }}</label>
                    <input
                        id="feedback-subject"
                        v-model="form.subject"
                        type="text"
                        maxlength="255"
                        :placeholder="$t('settings_platform_feedback.brief_summary')"
                        class="w-full rounded-lg border agent-border px-3 py-2.5 text-sm agent-text shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="feedback-body">{{ $t('settings_platform_feedback.details') }}</label>
                    <textarea
                        id="feedback-body"
                        v-model="form.body"
                        rows="8"
                        maxlength="5000"
                        :placeholder="$t('settings_platform_feedback.describe_your_feedback_or_the_feature_you_would_like_to_see')"
                        class="w-full rounded-lg border agent-border px-3 py-2.5 text-sm agent-text shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                    :disabled="form.processing"
                >{{ $t('settings_platform_feedback.submit') }}</button>
            </form>
        </div>
    </SettingsPage>
</template>
