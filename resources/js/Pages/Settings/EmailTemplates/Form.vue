<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../../Components/SettingsPage.vue';
import AppModal from '../../../Components/AppModal.vue';
import { useI18n } from 'vue-i18n';
import { csrfHeaders } from '../../../support/csrf.js';

const props = defineProps({
    id: Number,
    slug: String,
    name: String,
    subject: String,
    body_html: String,
    is_active: Boolean,
    is_system: Boolean,
    trigger: String,
    placeholders: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    name: props.name ?? '',
    subject: props.subject ?? '',
    body_html: props.body_html ?? '',
    is_active: props.is_active ?? true,
});

const previewOpen = ref(false);
const previewLoading = ref(false);
const previewError = ref('');
const previewSubject = ref('');
const previewBodyHtml = ref('');

const submit = () => {
    form.put(`/settings/email-templates/${props.id}`);
};

const reset = () => {
    if (confirm(t('settings_email_templates.restore_default_confirm'))) {
        router.post(`/settings/email-templates/${props.id}/reset`);
    }
};

const preview = async () => {
    previewLoading.value = true;
    previewError.value = '';

    try {
        const response = await fetch(`/settings/email-templates/${props.id}/preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                subject: form.subject,
                body_html: form.body_html,
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message ?? t('settings_email_templates.preview_failed'));
        }

        previewSubject.value = data.subject ?? '';
        previewBodyHtml.value = data.body_html ?? '';
        previewOpen.value = true;
    } catch (error) {
        previewError.value = error.message ?? t('settings_email_templates.preview_failed');
    } finally {
        previewLoading.value = false;
    }
};

const closePreview = () => {
    previewOpen.value = false;
};

const placeholderTag = (key) => `{{${key}}}`;
</script>

<template>
    <Head :title="`Edit · ${name}`" />
    <SettingsPage :title="name" :description="trigger" info-section="email_templates">
        <div class="mb-6">
            <Link href="/settings/email-templates" class="text-sm agent-text-subtle hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">{{ $t('settings_email_templates.back_to_templates') }}</Link>
        </div>

        <div v-if="placeholders.length" class="mb-6 rounded-xl border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 p-4">
            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $t('settings_email_templates.placeholders_for_email') }}</p>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                <li v-for="item in placeholders" :key="item.key" class="text-xs text-blue-800 dark:text-blue-200">
                    <code class="rounded bg-white dark:bg-slate-900 px-1.5 py-0.5">{{ placeholderTag(item.key) }}</code>
                    — {{ item.label }}
                </li>
            </ul>
        </div>

        <form class="space-y-5 agent-card" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('profile.name') }}</label>
                <input v-model="form.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm agent-input" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('common.subject') }}</label>
                <input v-model="form.subject" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm agent-input" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium agent-text">{{ $t('settings_email_templates.body_html') }}</label>
                <textarea v-model="form.body_html" rows="16" required class="w-full rounded-lg border agent-border px-3 py-2 font-mono text-sm agent-input" />
            </div>

            <label class="flex items-center gap-2 text-sm agent-text-muted">
                <input v-model="form.is_active" type="checkbox" class="rounded agent-border" />
                {{ $t('settings_email_templates.active_emails_sent') }}
            </label>

            <p v-if="previewError" class="text-sm text-red-600 dark:text-red-400">{{ previewError }}</p>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="form.processing">
                    {{ $t('common.save') }}
                </button>
                <button type="button" class="agent-btn-secondary" :disabled="previewLoading || form.processing" @click="preview">
                    {{ previewLoading ? $t('settings_email_templates.preview_loading') : $t('settings_email_templates.preview') }}
                </button>
                <button v-if="is_system" type="button" class="agent-btn-secondary" @click="reset">
                    {{ $t('settings_email_templates.restore_default') }}
                </button>
                <Link href="/settings/email-templates" class="text-sm agent-text-subtle hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">{{ $t('common.cancel') }}</Link>
            </div>
        </form>

        <AppModal
            :open="previewOpen"
            size="xl"
            :title="$t('settings_email_templates.preview_title')"
            :description="$t('settings_email_templates.preview_description')"
            @close="closePreview"
        >
            <div class="space-y-4">
                <div class="rounded-lg border agent-border agent-panel-muted px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('common.subject') }}</p>
                    <p class="mt-1 text-sm font-medium agent-text">{{ previewSubject }}</p>
                </div>

                <div class="overflow-hidden rounded-lg border agent-border bg-white dark:bg-slate-950">
                    <iframe
                        :srcdoc="previewBodyHtml"
                        title="Email preview"
                        class="h-[28rem] w-full border-0 bg-white"
                        sandbox="allow-same-origin"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="closePreview">
                        {{ $t('common.close') }}
                    </button>
                </div>
            </template>
        </AppModal>
    </SettingsPage>
</template>
