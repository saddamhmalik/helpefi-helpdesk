<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import InfrastructureBackupsPanel from '../../Components/Settings/InfrastructureBackupsPanel.vue';
import { useInfrastructureConnectionTest } from '../../composables/useInfrastructureConnectionTest.js';
import { buildInfrastructureSavePayload } from '../../composables/useInfrastructureSavePayload.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    infrastructure: Object,
});

const { t } = useI18n();

const canConfigureDatabase = computed(() => props.infrastructure.enabled && props.infrastructure.database_eligible);
const canConfigureStorage = computed(() => props.infrastructure.enabled && props.infrastructure.storage_eligible);

const databaseMigrationActive = computed(() => canConfigureDatabase.value && ['queued', 'running'].includes(props.infrastructure.database_migration_status));
const storageMigrationActive = computed(() => canConfigureStorage.value && ['queued', 'running'].includes(props.infrastructure.storage_migration_status));
const backupExportActive = computed(() => ['queued', 'running'].includes(props.infrastructure.backup_export_status));
const showByoBackups = computed(() => (
    canConfigureStorage.value && props.infrastructure.storage_mode === 'external'
));
const hasExternalDatabase = computed(() => props.infrastructure.database_mode === 'external');

const infrastructureStatusLabel = computed(() => {
    const status = props.infrastructure.status;

    if (status === 'verified' || status === 'failed' || status === 'pending') {
        return t(`settings_infrastructure.status_${status}`);
    }

    return status;
});

const visibleStatusMessage = computed(() => {
    const message = props.infrastructure.status_message;

    if (! message) {
        return '';
    }

    if (! canConfigureDatabase.value && message.toLowerCase().includes('database migration')) {
        return '';
    }

    if (! canConfigureStorage.value && message.toLowerCase().includes('storage migration')) {
        return '';
    }

    return message;
});

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';

const form = useForm({
    database_mode: props.infrastructure.database_mode ?? 'managed',
    storage_mode: props.infrastructure.storage_mode ?? 'managed',
    confirm_external_database: false,
    confirm_external_storage: false,
    database_config: {
        host: props.infrastructure.database_config?.host ?? '',
        port: props.infrastructure.database_config?.port ?? 3306,
        database: props.infrastructure.database_config?.database ?? '',
        username: props.infrastructure.database_config?.username ?? '',
        password: '',
        read_only_username: props.infrastructure.database_config?.read_only_username ?? '',
        read_only_password: '',
        ssl: props.infrastructure.database_config?.ssl ?? false,
    },
    storage_config: {
        driver: props.infrastructure.storage_config?.driver ?? 's3',
        bucket: props.infrastructure.storage_config?.bucket ?? '',
        region: props.infrastructure.storage_config?.region ?? (props.infrastructure.storage_config?.driver === 'r2' ? 'auto' : ''),
        endpoint: props.infrastructure.storage_config?.endpoint ?? '',
        access_key_id: props.infrastructure.storage_config?.access_key_id ?? '',
        secret_access_key: '',
        prefix: props.infrastructure.storage_config?.prefix ?? '',
    },
});

const {
    databaseTestPassed,
    storageTestPassed,
    databaseTestError,
    storageTestError,
    testingDatabase,
    testingStorage,
    needsDatabaseTest,
    needsStorageTest,
    databaseChanged,
    storageChanged,
    databaseLinked,
    storageLinked,
    databaseConnectionState,
    storageConnectionState,
    canSaveDatabase,
    canSaveStorage,
    testDatabase,
    testStorage,
    databaseSectionChanged,
    storageSectionChanged,
} = useInfrastructureConnectionTest({
    form,
    infrastructure: props.infrastructure,
    canConfigureDatabase,
    canConfigureStorage,
    testDatabaseUrl: '/settings/infrastructure/test-database',
    testStorageUrl: '/settings/infrastructure/test-storage',
});

const editingDatabase = ref(! databaseLinked.value);
const editingStorage = ref(! storageLinked.value);

watch(databaseLinked, (linked) => {
    if (linked && ! databaseSectionChanged()) {
        editingDatabase.value = false;
    }
});

watch(storageLinked, (linked) => {
    if (linked && ! storageSectionChanged()) {
        editingStorage.value = false;
    }
});

const resetDatabaseForm = () => {
    form.database_mode = props.infrastructure.database_mode ?? 'managed';
    form.confirm_external_database = false;
    form.database_config = {
        host: props.infrastructure.database_config?.host ?? '',
        port: props.infrastructure.database_config?.port ?? 3306,
        database: props.infrastructure.database_config?.database ?? '',
        username: props.infrastructure.database_config?.username ?? '',
        password: '',
        read_only_username: props.infrastructure.database_config?.read_only_username ?? '',
        read_only_password: '',
        ssl: props.infrastructure.database_config?.ssl ?? false,
    };
    editingDatabase.value = false;
};

const resetStorageForm = () => {
    form.storage_mode = props.infrastructure.storage_mode ?? 'managed';
    form.confirm_external_storage = false;
    form.storage_config = {
        driver: props.infrastructure.storage_config?.driver ?? 's3',
        bucket: props.infrastructure.storage_config?.bucket ?? '',
        region: props.infrastructure.storage_config?.region ?? (props.infrastructure.storage_config?.driver === 'r2' ? 'auto' : ''),
        endpoint: props.infrastructure.storage_config?.endpoint ?? '',
        access_key_id: props.infrastructure.storage_config?.access_key_id ?? '',
        secret_access_key: '',
        prefix: props.infrastructure.storage_config?.prefix ?? '',
    };
    editingStorage.value = false;
};

const databaseConnectionMessageKey = computed(() => {
    const state = databaseConnectionState.value;

    if (state === 'linked' || state === 'active') {
        return 'settings_infrastructure.connection_active';
    }

    if (state === 'verified_pending_save') {
        return 'settings_infrastructure.connection_verified';
    }

    if (state === 'test_required') {
        return 'settings_infrastructure.test_before_save';
    }

    return '';
});

const storageConnectionMessageKey = computed(() => {
    const state = storageConnectionState.value;

    if (state === 'linked' || state === 'active') {
        return 'settings_infrastructure.connection_active';
    }

    if (state === 'verified_pending_save') {
        return 'settings_infrastructure.connection_verified';
    }

    if (state === 'test_required') {
        return 'settings_infrastructure.test_before_save';
    }

    return '';
});

watch(() => form.storage_config.driver, (driver, previous) => {
    if (driver === 'r2' && previous !== 'r2') {
        form.storage_config.region = 'auto';
    }
});

const submitSection = (section) => {
    form.transform(() => buildInfrastructureSavePayload(props.infrastructure, form, section))
        .put('/settings/infrastructure', {
            preserveScroll: true,
        });
};

const saveDatabase = () => submitSection('database');

const saveStorage = () => submitSection('storage');

const migrateDatabase = () => {
    router.post('/settings/infrastructure/migrate-database', {}, { preserveScroll: true });
};

const migrateStorage = () => {
    router.post('/settings/infrastructure/migrate-storage', {}, { preserveScroll: true });
};

const exportBackup = () => {
    router.post('/settings/infrastructure/export-backup', {}, { preserveScroll: true });
};
</script>

<template>
    <SettingsPage
        :title="t('settings_infrastructure.title')"
        :description="t('settings_infrastructure.description')"
    >
        <div v-if="!infrastructure.enabled" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            {{ t('settings_infrastructure.platform_disabled') }}
        </div>

        <div v-else-if="infrastructure.egress_ips?.length" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
            <p class="font-medium">{{ t('settings_infrastructure.egress_ips_title') }}</p>
            <p class="mt-1 font-mono">{{ infrastructure.egress_ips.join(', ') }}</p>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded-full px-3 py-1 font-medium" :class="infrastructure.status === 'verified' ? 'bg-emerald-100 text-emerald-800' : infrastructure.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700'">
                {{ infrastructureStatusLabel }}
            </span>
            <span v-if="canConfigureDatabase && infrastructure.database_migration_status" class="rounded-full bg-violet-100 px-3 py-1 font-medium text-violet-800">
                {{ t('settings_infrastructure.db_migration') }}: {{ infrastructure.database_migration_status }}
            </span>
            <span v-if="canConfigureStorage && infrastructure.storage_migration_status" class="rounded-full bg-violet-100 px-3 py-1 font-medium text-violet-800">
                {{ t('settings_infrastructure.storage_migration') }}: {{ infrastructure.storage_migration_status }}
            </span>
            <span v-if="infrastructure.backup_export_status" class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-800">
                {{ t('settings_infrastructure.backup_export') }}: {{ infrastructure.backup_export_status }}
            </span>
            <span v-if="infrastructure.backup_export_path && infrastructure.backup_export_status === 'completed'" class="font-mono text-xs text-slate-500 dark:text-slate-400">
                {{ infrastructure.backup_export_path }}
            </span>
            <span v-if="infrastructure.backup_export_message" class="text-slate-500 dark:text-slate-400">{{ infrastructure.backup_export_message }}</span>
            <span v-if="visibleStatusMessage" class="text-slate-500 dark:text-slate-400">{{ visibleStatusMessage }}</span>
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <button
                v-if="canConfigureDatabase && infrastructure.database_migration_status === 'failed'"
                type="button"
                class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-800 hover:bg-violet-50"
                @click="migrateDatabase"
            >
                {{ t('settings_infrastructure.retry_db_migration') }}
            </button>
            <button
                v-if="canConfigureStorage && infrastructure.storage_migration_status === 'failed'"
                type="button"
                class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-800 hover:bg-violet-50"
                @click="migrateStorage"
            >
                {{ t('settings_infrastructure.retry_storage_migration') }}
            </button>
        </div>

        <form class="space-y-8" @submit.prevent>
            <section class="rounded-xl border border-slate-200 p-6 dark:border-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ t('settings_infrastructure.database_title') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.database_intro') }}</p>
                    </div>
                    <span
                        v-if="databaseLinked && !editingDatabase"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        {{ t('settings_infrastructure.connection_linked') }}
                    </span>
                </div>

                <PlanFeatureBanner feature="byo_database" class="mt-4" />

                <div
                    v-if="databaseLinked && !editingDatabase"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                >
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ t('settings_infrastructure.connection_active') }}</p>
                    <dl class="mt-3 grid gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.host') }}</dt>
                            <dd class="font-mono">{{ infrastructure.database_config.host }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.database_name') }}</dt>
                            <dd class="font-mono">{{ infrastructure.database_config.database }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.username') }}</dt>
                            <dd class="font-mono">{{ infrastructure.database_config.username }}</dd>
                        </div>
                    </dl>
                    <button
                        type="button"
                        class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900"
                        @click="editingDatabase = true"
                    >
                        {{ t('settings_infrastructure.edit_connection') }}
                    </button>
                </div>

                <template v-else>

                <div v-if="canConfigureDatabase" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.mode') }}</span>
                        <select v-model="form.database_mode" :class="inputClass">
                            <option value="managed">{{ t('settings_infrastructure.managed_database') }}</option>
                            <option value="external">{{ t('settings_infrastructure.external_database') }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="canConfigureDatabase && form.database_mode === 'external'" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm md:col-span-2">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.host') }}</span>
                        <input v-model="form.database_config.host" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.port') }}</span>
                        <input v-model.number="form.database_config.port" type="number" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.database_name') }}</span>
                        <input v-model="form.database_config.database" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.username') }}</span>
                        <input v-model="form.database_config.username" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.password') }}</span>
                        <input v-model="form.database_config.password" type="password" :class="inputClass" :placeholder="t('settings_infrastructure.keep_existing')">
                    </label>
                    <label class="flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="form.database_config.ssl" type="checkbox" class="rounded border-slate-300">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.use_ssl') }}</span>
                    </label>
                    <label v-if="infrastructure.database_mode === 'managed' && form.database_mode === 'external'" class="flex items-start gap-2 text-sm md:col-span-2">
                        <input v-model="form.confirm_external_database" type="checkbox" class="mt-1 rounded border-slate-300">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.confirm_database_migration') }}</span>
                    </label>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            :disabled="testingDatabase || databaseMigrationActive"
                            @click="testDatabase"
                        >
                            {{ testingDatabase ? t('settings_infrastructure.testing_connection') : t('settings_infrastructure.test_connection') }}
                        </button>
                        <p v-if="databaseTestError" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ databaseTestError }}
                        </p>
                        <p v-else-if="databaseConnectionMessageKey" class="mt-2 text-sm font-medium" :class="databaseConnectionState === 'test_required' ? 'text-slate-500 dark:text-slate-400' : 'text-emerald-700 dark:text-emerald-300'">
                            {{ t(databaseConnectionMessageKey) }}
                        </p>
                    </div>
                </div>

                <div v-if="canSaveDatabase && !databaseMigrationActive" class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        :disabled="form.processing"
                        @click="saveDatabase"
                    >
                        {{ t('settings_infrastructure.save_database') }}
                    </button>
                    <button
                        v-if="databaseLinked"
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="resetDatabaseForm"
                    >
                        {{ t('settings_infrastructure.cancel_edit') }}
                    </button>
                </div>
                <p v-else-if="databaseMigrationActive" class="mt-4 text-sm text-amber-700 dark:text-amber-300">
                    {{ t('settings_infrastructure.save_blocked_migration') }}
                </p>
                </template>
            </section>

            <section class="rounded-xl border border-slate-200 p-6 dark:border-slate-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ t('settings_infrastructure.storage_title') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.storage_intro') }}</p>
                    </div>
                    <span
                        v-if="storageLinked && !editingStorage"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        {{ t('settings_infrastructure.connection_linked') }}
                    </span>
                </div>

                <PlanFeatureBanner feature="byo_storage" class="mt-4" />

                <div
                    v-if="storageLinked && !editingStorage"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                >
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ t('settings_infrastructure.connection_active') }}</p>
                    <dl class="mt-3 grid gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.driver') }}</dt>
                            <dd class="uppercase">{{ infrastructure.storage_config.driver }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.bucket') }}</dt>
                            <dd class="font-mono">{{ infrastructure.storage_config.bucket }}</dd>
                        </div>
                        <div v-if="infrastructure.storage_config.prefix" class="flex flex-wrap gap-x-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('settings_infrastructure.key_prefix') }}</dt>
                            <dd class="font-mono">{{ infrastructure.storage_config.prefix }}</dd>
                        </div>
                    </dl>
                    <button
                        type="button"
                        class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900"
                        @click="editingStorage = true"
                    >
                        {{ t('settings_infrastructure.edit_connection') }}
                    </button>
                </div>

                <template v-else>

                <div v-if="canConfigureStorage" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.mode') }}</span>
                        <select v-model="form.storage_mode" :class="inputClass">
                            <option value="managed">{{ t('settings_infrastructure.managed_storage') }}</option>
                            <option value="external">{{ t('settings_infrastructure.external_storage') }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="canConfigureStorage && form.storage_mode === 'external'" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.driver') }}</span>
                        <select v-model="form.storage_config.driver" :class="inputClass">
                            <option value="s3">AWS S3</option>
                            <option value="r2">Cloudflare R2</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.bucket') }}</span>
                        <input v-model="form.storage_config.bucket" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ form.storage_config.driver === 'r2' ? t('settings_infrastructure.r2_region') : t('settings_infrastructure.region') }}</span>
                        <select
                            v-if="form.storage_config.driver === 'r2'"
                            v-model="form.storage_config.region"
                            :class="inputClass"
                        >
                            <option value="auto">{{ t('settings_infrastructure.r2_region_auto') }}</option>
                            <option value="apac">{{ t('settings_infrastructure.r2_region_apac') }}</option>
                            <option value="wnam">{{ t('settings_infrastructure.r2_region_wnam') }}</option>
                            <option value="enam">{{ t('settings_infrastructure.r2_region_enam') }}</option>
                            <option value="weur">{{ t('settings_infrastructure.r2_region_weur') }}</option>
                            <option value="eeur">{{ t('settings_infrastructure.r2_region_eeur') }}</option>
                            <option value="oc">{{ t('settings_infrastructure.r2_region_oc') }}</option>
                        </select>
                        <input
                            v-else
                            v-model="form.storage_config.region"
                            type="text"
                            :class="inputClass"
                            placeholder="us-east-1"
                        >
                        <p v-if="form.storage_config.driver === 'r2'" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ t('settings_infrastructure.r2_region_hint') }}
                        </p>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.endpoint') }}</span>
                        <input v-model="form.storage_config.endpoint" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.access_key_id') }}</span>
                        <input v-model="form.storage_config.access_key_id" type="text" :class="inputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.secret_access_key') }}</span>
                        <input v-model="form.storage_config.secret_access_key" type="password" :class="inputClass" :placeholder="t('settings_infrastructure.keep_existing')">
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.key_prefix') }}</span>
                        <input v-model="form.storage_config.prefix" type="text" :class="inputClass">
                    </label>
                    <label v-if="infrastructure.storage_mode === 'managed' && form.storage_mode === 'external'" class="flex items-start gap-2 text-sm md:col-span-2">
                        <input v-model="form.confirm_external_storage" type="checkbox" class="mt-1 rounded border-slate-300">
                        <span class="text-slate-600 dark:text-slate-400">{{ t('settings_infrastructure.confirm_storage_migration') }}</span>
                    </label>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            :disabled="testingStorage || storageMigrationActive"
                            @click="testStorage"
                        >
                            {{ testingStorage ? t('settings_infrastructure.testing_connection') : t('settings_infrastructure.test_connection') }}
                        </button>
                        <p v-if="storageTestError" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ storageTestError }}
                        </p>
                        <p v-else-if="storageConnectionMessageKey" class="mt-2 text-sm font-medium" :class="storageConnectionState === 'test_required' ? 'text-slate-500 dark:text-slate-400' : 'text-emerald-700 dark:text-emerald-300'">
                            {{ t(storageConnectionMessageKey) }}
                        </p>
                    </div>
                </div>

                <div v-if="canSaveStorage && !storageMigrationActive" class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        :disabled="form.processing"
                        @click="saveStorage"
                    >
                        {{ t('settings_infrastructure.save_storage') }}
                    </button>
                    <button
                        v-if="storageLinked"
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="resetStorageForm"
                    >
                        {{ t('settings_infrastructure.cancel_edit') }}
                    </button>
                </div>
                <p v-else-if="storageMigrationActive" class="mt-4 text-sm text-amber-700 dark:text-amber-300">
                    {{ t('settings_infrastructure.save_blocked_migration') }}
                </p>
                </template>
            </section>

            <InfrastructureBackupsPanel
                v-if="showByoBackups"
                :backups="infrastructure.backups"
                :can-configure-storage="canConfigureStorage"
                :has-external-database="hasExternalDatabase"
                :backup-export-active="backupExportActive"
                :input-class="inputClass"
                @export-backup="exportBackup"
            />
        </form>
    </SettingsPage>
</template>
