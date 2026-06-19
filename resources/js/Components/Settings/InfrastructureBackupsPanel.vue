<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    backups: { type: Object, default: null },
    canConfigureStorage: { type: Boolean, default: false },
    hasExternalDatabase: { type: Boolean, default: false },
    backupExportActive: { type: Boolean, default: false },
    inputClass: { type: String, required: true },
});

const emit = defineEmits(['export-backup']);

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const editingBackupId = ref(null);
const editingLabel = ref('');

const scheduleForm = useForm({
    enabled: props.backups?.schedule?.enabled ?? false,
    frequency: props.backups?.schedule?.frequency ?? 'daily',
    weekday: props.backups?.schedule?.weekday ?? 1,
    time: props.backups?.schedule?.time ?? '02:00',
});

const saveSchedule = () => {
    scheduleForm.put('/settings/infrastructure/auto-backup', {
        preserveScroll: true,
    });
};

const startEditBackup = (backup) => {
    editingBackupId.value = backup.id;
    editingLabel.value = backup.label;
};

const cancelEditBackup = () => {
    editingBackupId.value = null;
    editingLabel.value = '';
};

const saveBackupLabel = (backupId) => {
    router.put(`/settings/infrastructure/backups/${backupId}`, {
        label: editingLabel.value,
    }, {
        preserveScroll: true,
        onSuccess: () => cancelEditBackup(),
    });
};

const deleteBackup = (backupId) => {
    if (! window.confirm(t('settings_infrastructure.backup_delete_confirm'))) {
        return;
    }

    router.delete(`/settings/infrastructure/backups/${backupId}`, {
        preserveScroll: true,
    });
};

const formatSize = (bytes) => {
    if (! bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const backupItems = computed(() => props.backups?.backups ?? []);
const scheduleOptions = computed(() => props.backups?.schedule_options ?? { frequencies: [], weekdays: [] });
const canUseAutoBackup = computed(() => props.hasExternalDatabase);
</script>

<template>
    <section class="rounded-xl border border-slate-200 p-6 dark:border-slate-800">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ t('settings_infrastructure.backups_title') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.backups_intro') }}</p>
            </div>
            <button
                v-if="hasExternalDatabase"
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                :disabled="backupExportActive"
                @click="emit('export-backup')"
            >
                {{ backupExportActive ? t('settings_infrastructure.export_backup_running') : t('settings_infrastructure.export_backup_now') }}
            </button>
        </div>

        <div v-if="canUseAutoBackup" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ t('settings_infrastructure.auto_backup_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ backups?.schedule?.summary }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                    <input v-model="scheduleForm.enabled" type="checkbox" class="rounded border-slate-300" @change="saveSchedule">
                    {{ t('settings_infrastructure.auto_backup_enabled') }}
                </label>
            </div>

            <div v-if="scheduleForm.enabled" class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.auto_backup_frequency') }}</span>
                    <select v-model="scheduleForm.frequency" :class="inputClass" @change="saveSchedule">
                        <option v-for="option in scheduleOptions.frequencies" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
                <label v-if="scheduleForm.frequency === 'weekly'" class="block text-sm">
                    <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.auto_backup_weekday') }}</span>
                    <select v-model.number="scheduleForm.weekday" :class="inputClass" @change="saveSchedule">
                        <option v-for="option in scheduleOptions.weekdays" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.auto_backup_time') }}</span>
                    <input v-model="scheduleForm.time" type="time" :class="inputClass" @change="saveSchedule">
                </label>
            </div>
        </div>

        <p v-else class="mt-4 text-sm text-amber-700 dark:text-amber-300">
            {{ t('settings_infrastructure.auto_backup_requires_database') }}
        </p>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ t('settings_infrastructure.backups_in_bucket') }}</h3>

            <p v-if="!backupItems.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ t('settings_infrastructure.backups_empty') }}
            </p>

            <div v-else class="mt-3 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.backup_label') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.backup_size') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.backup_stored_at') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.backup_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950">
                        <tr v-for="backup in backupItems" :key="backup.id">
                            <td class="px-4 py-3">
                                <div v-if="editingBackupId === backup.id" class="flex flex-col gap-2 sm:flex-row">
                                    <input v-model="editingLabel" type="text" :class="inputClass" class="!mt-0">
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white" @click="saveBackupLabel(backup.id)">{{ t('settings_infrastructure.save') }}</button>
                                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-300" @click="cancelEditBackup">{{ t('settings_infrastructure.cancel_edit') }}</button>
                                    </div>
                                </div>
                                <div v-else>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ backup.label }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ backup.object_key }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ formatSize(backup.size) }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ backup.stored_at ? formatDateTime(backup.stored_at) : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-500" @click="startEditBackup(backup)">{{ t('settings_infrastructure.edit') }}</button>
                                    <button type="button" class="text-xs font-semibold text-red-600 hover:text-red-500" @click="deleteBackup(backup.id)">{{ t('settings_infrastructure.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
