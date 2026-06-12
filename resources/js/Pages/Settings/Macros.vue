<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass, formTextareaClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    responses: Array,
    placeholders: Array,
});

const { t } = useI18n();

const showForm = ref(false);
const editing = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blank = () => ({
    title: '',
    shortcut: '',
    body: '',
    is_shared: false,
});

const form = useForm(blank());

const openCreate = () => {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    showForm.value = true;
};

const openEdit = (response) => {
    editing.value = response;
    form.defaults({
        title: response.title,
        shortcut: response.shortcut ?? '',
        body: response.body,
        is_shared: response.is_shared,
    });
    form.reset();
    showForm.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(`/settings/macros/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post('/settings/macros', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    }
};

const destroy = (response) => {
    askConfirm({
        title: 'Delete macro?',
        message: `Remove "${response.title}" permanently?`,
        action: () => router.delete(`/settings/macros/${response.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsPage :title="$t('settings.macros')">
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold agent-text">{{ $t('settings_macros.canned_responses') }}</h1>
                    <p class="mt-1 text-sm agent-text-subtle">{{ $t('settings_macros.save_reusable_replies_with_placeholders_for_ticket_and_customer_detail') }}</p>
                </div>
                <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="openCreate">{{ $t('settings_macros.new_macro') }}</button>
            </div>

            <div class="rounded-xl border agent-border agent-panel-muted p-4">
                <p class="text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('settings_macros.placeholders') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <code v-for="item in placeholders" :key="item.token" class="rounded bg-white dark:bg-slate-900 px-2 py-1 text-xs text-slate-700 dark:text-slate-300 ring-1 agent-border">{{ item.token }}</code>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border agent-border agent-panel">
                <ul class="divide-y agent-table-divider">
                    <li v-for="response in responses" :key="response.id" class="flex items-start justify-between gap-4 px-4 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium agent-text">{{ response.title }}</p>
                                <span v-if="response.shortcut" class="rounded bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-[11px] agent-text-muted">#{{ response.shortcut }}</span>
                                <span v-if="response.is_shared" class="rounded bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:text-blue-300">{{ $t('settings_macros.shared') }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm agent-text-subtle">{{ response.body.replace(/<[^>]+>/g, ' ').trim() }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <AppRowActions>
                                <AppEditAction :label="$t('settings_macros.edit')" @click="openEdit(response)" />
                                <AppDeleteAction :label="$t('settings_macros.delete')" @click="destroy(response)" />
                            </AppRowActions>
                        </div>
                    </li>
                    <li v-if="!responses?.length" class="px-4 py-10 text-center text-sm agent-text-subtle">{{ $t('settings_macros.no_macros_yet') }}</li>
                </ul>
            </div>
        </div>

        <AppModal :show="showForm" @close="showForm = false">
            <form class="space-y-4" @submit.prevent="submit">
                <h2 class="text-lg font-semibold agent-text">{{ editing ? 'Edit macro' : 'New macro' }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.title') }}</label>
                    <input v-model="form.title" type="text" required :class="formInputClass" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_macros.shortcut') }}</label>
                    <input v-model="form.shortcut" type="text" :placeholder="$t('settings_macros.refund')" :class="formInputClass" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_macros.body') }}</label>
                    <textarea v-model="form.body" rows="8" required :class="formTextareaClass" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.is_shared" type="checkbox" class="rounded agent-border text-blue-600" />
                    Share with all agents
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm" @click="showForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ $t('common.save') }}</button>
                </div>
            </form>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsPage>
</template>
