<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SettingsPage from '../../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';

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

const submit = () => {
    form.put(`/settings/email-templates/${props.id}`);
};

const reset = () => {
    if (confirm('Restore this template to the default content?')) {
        router.post(`/settings/email-templates/${props.id}/reset`);
    }
};

const placeholderTag = (key) => `{{${key}}}`;
</script>

<template>
    <Head :title="`Edit · ${name}`" />
    <SettingsPage :title="name" :description="trigger">
        <div class="mb-6">
            <Link href="/settings/email-templates" class="text-sm agent-text-subtle hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">← Back to email templates</Link>
        </div>

        <div v-if="placeholders.length" class="mb-6 rounded-xl border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 p-4">
            <p class="text-sm font-medium text-blue-900">Placeholders for this email</p>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                <li v-for="item in placeholders" :key="item.key" class="text-xs text-blue-800">
                    <code class="rounded bg-white dark:bg-slate-900 px-1.5 py-0.5">{{ placeholderTag(item.key) }}</code>
                    — {{ item.label }}
                </li>
            </ul>
        </div>

        <form class="space-y-5 agent-card" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                <input v-model="form.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.subject') }}</label>
                <input v-model="form.subject" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Body (HTML)</label>
                <textarea v-model="form.body_html" rows="16" required class="w-full rounded-lg border agent-border px-3 py-2 font-mono text-sm" />
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded agent-border" />
                Active — when disabled, the built-in default email is used instead
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50" :disabled="form.processing">
                    Save changes
                </button>
                <button v-if="is_system" type="button" class="agent-btn-secondary" @click="reset">
                    Restore default
                </button>
                <Link href="/settings/email-templates" class="text-sm agent-text-subtle hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">{{ $t('common.cancel') }}</Link>
            </div>
        </form>
    </SettingsPage>
</template>
