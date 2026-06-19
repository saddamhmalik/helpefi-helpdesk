<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';
import { useInfrastructureConnectionTest } from '../../../../composables/useInfrastructureConnectionTest.js';
import { buildInfrastructureSavePayload } from '../../../../composables/useInfrastructureSavePayload.js';

const props = defineProps({
    tenant: Object,
    infrastructure: Object,
});

const canConfigureDatabase = computed(() => props.infrastructure.enabled && props.infrastructure.database_eligible);
const canConfigureStorage = computed(() => props.infrastructure.enabled && props.infrastructure.storage_eligible);
const canConfigure = computed(() => canConfigureDatabase.value || canConfigureStorage.value);

const databaseMigrationActive = computed(() => ['queued', 'running'].includes(props.infrastructure.database_migration_status));
const storageMigrationActive = computed(() => ['queued', 'running'].includes(props.infrastructure.storage_migration_status));
const backupExportActive = computed(() => ['queued', 'running'].includes(props.infrastructure.backup_export_status));

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
    canSaveDatabase,
    canSaveStorage,
    testDatabase,
    testStorage,
} = useInfrastructureConnectionTest({
    form,
    infrastructure: props.infrastructure,
    canConfigureDatabase,
    canConfigureStorage,
    testDatabaseUrl: `/admin/tenants/${props.tenant.id}/infrastructure/test-database`,
    testStorageUrl: `/admin/tenants/${props.tenant.id}/infrastructure/test-storage`,
});

watch(() => form.storage_config.driver, (driver, previous) => {
    if (driver === 'r2' && previous !== 'r2') {
        form.storage_config.region = 'auto';
    }
});

const submitSection = (section) => {
    form.transform(() => buildInfrastructureSavePayload(props.infrastructure, form, section))
        .put(`/admin/tenants/${props.tenant.id}/infrastructure`, {
            preserveScroll: true,
        });
};

const saveDatabase = () => submitSection('database');

const saveStorage = () => submitSection('storage');

const verify = () => {
    form.post(`/admin/tenants/${props.tenant.id}/infrastructure/verify`, {
        preserveScroll: true,
    });
};

const migrateDatabase = () => {
    router.post(`/admin/tenants/${props.tenant.id}/infrastructure/migrate-database`, {}, { preserveScroll: true });
};

const migrateStorage = () => {
    router.post(`/admin/tenants/${props.tenant.id}/infrastructure/migrate-storage`, {}, { preserveScroll: true });
};

const exportBackup = () => {
    router.post(`/admin/tenants/${props.tenant.id}/infrastructure/export-backup`, {}, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Infrastructure — ${tenant.name}`" />

        <PageHeader
            :title="`Infrastructure — ${tenant.name}`"
            :description="`Configure external database and storage for ${tenant.slug}.`"
        />

        <div v-if="!infrastructure.enabled" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            Bring-your-own infrastructure is disabled. Set <code class="font-mono">TENANT_BYO_ENABLED=true</code> to allow external database or storage.
        </div>

        <div v-else-if="!infrastructure.eligible" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-medium">This workspace is not eligible for bring-your-own infrastructure.</p>
            <ul v-if="infrastructure.reasons?.length" class="mt-2 list-disc space-y-1 pl-5">
                <li v-for="reason in infrastructure.reasons" :key="reason">{{ reason }}</li>
            </ul>
            <p class="mt-2">Enable <strong>BYO allowlist</strong> on the workspace, assign an enterprise plan, and end the trial before configuring external database or storage.</p>
        </div>

        <div v-else-if="infrastructure.egress_ips?.length" class="mb-6 rounded-xl border agent-border p-4 text-sm agent-text-muted">
            <p class="font-medium agent-text">Allow these IPs on your database firewall:</p>
            <p class="mt-1 font-mono">{{ infrastructure.egress_ips.join(', ') }}</p>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded-full px-3 py-1 font-medium" :class="infrastructure.status === 'verified' ? 'bg-emerald-100 text-emerald-800' : infrastructure.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700'">
                {{ infrastructure.status }}
            </span>
            <span v-if="infrastructure.health_failure_count > 0" class="text-amber-700">
                Health warnings: {{ infrastructure.health_failure_count }}
            </span>
            <span v-if="infrastructure.database_migration_status" class="rounded-full bg-violet-100 px-3 py-1 font-medium text-violet-800">
                DB migration: {{ infrastructure.database_migration_status }}
            </span>
            <span v-if="infrastructure.storage_migration_status" class="rounded-full bg-violet-100 px-3 py-1 font-medium text-violet-800">
                Storage migration: {{ infrastructure.storage_migration_status }}
            </span>
            <span v-if="infrastructure.backup_export_status" class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-800">
                Backup export: {{ infrastructure.backup_export_status }}
            </span>
            <span v-if="infrastructure.backup_export_path && infrastructure.backup_export_status === 'completed'" class="font-mono text-xs agent-text-muted">
                {{ infrastructure.backup_export_path }}
            </span>
            <span v-if="infrastructure.backup_export_message" class="agent-text-muted">{{ infrastructure.backup_export_message }}</span>
            <span v-if="infrastructure.status_message" class="agent-text-muted">{{ infrastructure.status_message }}</span>
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <button
                v-if="infrastructure.database_migration_status === 'failed'"
                type="button"
                class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-800 hover:bg-violet-50"
                @click="migrateDatabase"
            >
                Retry database migration
            </button>
            <button
                v-if="infrastructure.storage_migration_status === 'failed'"
                type="button"
                class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-800 hover:bg-violet-50"
                @click="migrateStorage"
            >
                Retry storage migration
            </button>
            <button
                v-if="infrastructure.database_mode === 'external' && infrastructure.storage_mode === 'external'"
                type="button"
                class="rounded-lg border agent-border px-4 py-2 text-sm font-medium agent-text hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-slate-800"
                :disabled="backupExportActive"
                @click="exportBackup"
            >
                {{ backupExportActive ? 'Exporting database…' : 'Export database to customer bucket' }}
            </button>
        </div>

        <form class="space-y-8" @submit.prevent>
            <section class="rounded-xl border agent-border p-6">
                <h2 class="text-lg font-semibold agent-text">Database</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="agent-text-muted">Mode</span>
                        <select v-model="form.database_mode" :class="adminInputClass" :disabled="!canConfigureDatabase">
                            <option value="managed">Managed (helpefi)</option>
                            <option value="external">External (customer RDS)</option>
                        </select>
                    </label>
                </div>

                <div v-if="form.database_mode === 'external'" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm md:col-span-2">
                        <span class="agent-text-muted">Host</span>
                        <input v-model="form.database_config.host" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Port</span>
                        <input v-model.number="form.database_config.port" type="number" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Database</span>
                        <input v-model="form.database_config.database" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Username</span>
                        <input v-model="form.database_config.username" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Password</span>
                        <input v-model="form.database_config.password" type="password" :class="adminInputClass" placeholder="Leave blank to keep existing">
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="agent-text-muted">Read-only username (optional, for support access)</span>
                        <input v-model="form.database_config.read_only_username" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="agent-text-muted">Read-only password</span>
                        <input v-model="form.database_config.read_only_password" type="password" :class="adminInputClass" placeholder="Leave blank to keep existing">
                    </label>
                    <label class="flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="form.database_config.ssl" type="checkbox" class="rounded border-slate-300">
                        <span class="agent-text-muted">Use SSL</span>
                    </label>
                    <label v-if="infrastructure.database_mode === 'managed' && form.database_mode === 'external'" class="flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="form.confirm_external_database" type="checkbox" class="rounded border-slate-300">
                        <span class="agent-text-muted">I understand switching to an external database requires a data migration.</span>
                    </label>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="rounded-lg border agent-border px-4 py-2 text-sm font-medium agent-text hover:bg-slate-50 dark:hover:bg-slate-800"
                            :disabled="testingDatabase || databaseMigrationActive"
                            @click="testDatabase"
                        >
                            {{ testingDatabase ? 'Testing…' : 'Test connection' }}
                        </button>
                        <p v-if="databaseTestPassed" class="mt-2 text-sm font-medium text-emerald-700">Connection verified.</p>
                        <p v-else-if="databaseTestError" class="mt-2 text-sm text-red-600">{{ databaseTestError }}</p>
                        <p v-else-if="needsDatabaseTest" class="mt-2 text-sm agent-text-muted">Test the connection before saving.</p>
                    </div>
                </div>

                <div v-if="canSaveDatabase && !databaseMigrationActive" class="mt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        :disabled="form.processing"
                        @click="saveDatabase"
                    >
                        Save database
                    </button>
                </div>
            </section>

            <section class="rounded-xl border agent-border p-6">
                <h2 class="text-lg font-semibold agent-text">File storage</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="agent-text-muted">Mode</span>
                        <select v-model="form.storage_mode" :class="adminInputClass" :disabled="!canConfigureStorage">
                            <option value="managed">Managed (helpefi disk)</option>
                            <option value="external">External (S3 / R2)</option>
                        </select>
                    </label>
                </div>

                <div v-if="form.storage_mode === 'external'" class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="agent-text-muted">Driver</span>
                        <select v-model="form.storage_config.driver" :class="adminInputClass">
                            <option value="s3">AWS S3</option>
                            <option value="r2">Cloudflare R2</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Bucket</span>
                        <input v-model="form.storage_config.bucket" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">{{ form.storage_config.driver === 'r2' ? 'R2 region' : 'Region' }}</span>
                        <select
                            v-if="form.storage_config.driver === 'r2'"
                            v-model="form.storage_config.region"
                            :class="adminInputClass"
                        >
                            <option value="auto">auto (recommended)</option>
                            <option value="apac">apac (Asia-Pacific)</option>
                            <option value="wnam">wnam (Western North America)</option>
                            <option value="enam">enam (Eastern North America)</option>
                            <option value="weur">weur (Western Europe)</option>
                            <option value="eeur">eeur (Eastern Europe)</option>
                            <option value="oc">oc (Oceania)</option>
                        </select>
                        <input
                            v-else
                            v-model="form.storage_config.region"
                            type="text"
                            :class="adminInputClass"
                            placeholder="us-east-1"
                        >
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Endpoint (R2)</span>
                        <input v-model="form.storage_config.endpoint" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Access key ID</span>
                        <input v-model="form.storage_config.access_key_id" type="text" :class="adminInputClass">
                    </label>
                    <label class="block text-sm">
                        <span class="agent-text-muted">Secret access key</span>
                        <input v-model="form.storage_config.secret_access_key" type="password" :class="adminInputClass" placeholder="Leave blank to keep existing">
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="agent-text-muted">Key prefix</span>
                        <input v-model="form.storage_config.prefix" type="text" :class="adminInputClass" :placeholder="`helpefi/${tenant.id}`">
                    </label>
                    <label v-if="infrastructure.storage_mode === 'managed' && form.storage_mode === 'external'" class="flex items-center gap-2 text-sm md:col-span-2">
                        <input v-model="form.confirm_external_storage" type="checkbox" class="rounded border-slate-300">
                        <span class="agent-text-muted">I understand switching to external storage requires a file migration.</span>
                    </label>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="rounded-lg border agent-border px-4 py-2 text-sm font-medium agent-text hover:bg-slate-50 dark:hover:bg-slate-800"
                            :disabled="testingStorage || storageMigrationActive"
                            @click="testStorage"
                        >
                            {{ testingStorage ? 'Testing…' : 'Test connection' }}
                        </button>
                        <p v-if="storageTestPassed" class="mt-2 text-sm font-medium text-emerald-700">Connection verified.</p>
                        <p v-else-if="storageTestError" class="mt-2 text-sm text-red-600">{{ storageTestError }}</p>
                        <p v-else-if="needsStorageTest" class="mt-2 text-sm agent-text-muted">Test the connection before saving.</p>
                    </div>
                </div>

                <div v-if="canSaveStorage && !storageMigrationActive" class="mt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        :disabled="form.processing"
                        @click="saveStorage"
                    >
                        Save storage
                    </button>
                </div>
            </section>
        </form>
    </AdminLayout>
</template>
