import { computed, ref, watch } from 'vue';
import { csrfHeaders } from '../support/csrf.js';

function databaseSectionChanged(form, infrastructure) {
    if (form.database_mode !== infrastructure.database_mode) {
        return true;
    }

    if (form.database_mode !== 'external') {
        return false;
    }

    const saved = infrastructure.database_config ?? {};
    const config = form.database_config;

    return (
        config.host !== (saved.host ?? '')
        || Number(config.port) !== Number(saved.port ?? 3306)
        || config.database !== (saved.database ?? '')
        || config.username !== (saved.username ?? '')
        || config.password !== ''
        || config.read_only_username !== (saved.read_only_username ?? '')
        || config.read_only_password !== ''
        || config.ssl !== (saved.ssl ?? false)
    );
}

function storageSectionChanged(form, infrastructure) {
    if (form.storage_mode !== infrastructure.storage_mode) {
        return true;
    }

    if (form.storage_mode !== 'external') {
        return false;
    }

    const saved = infrastructure.storage_config ?? {};
    const config = form.storage_config;

    return (
        config.driver !== (saved.driver ?? 's3')
        || config.bucket !== (saved.bucket ?? '')
        || config.region !== (saved.region ?? '')
        || config.endpoint !== (saved.endpoint ?? '')
        || config.access_key_id !== (saved.access_key_id ?? '')
        || config.secret_access_key !== ''
        || config.prefix !== (saved.prefix ?? '')
    );
}

function databaseSectionNeedsSave(form, infrastructure) {
    if (form.database_mode !== infrastructure.database_mode) {
        return true;
    }

    if (form.database_mode !== 'external') {
        return false;
    }

    if (databaseSectionChanged(form, infrastructure)) {
        return true;
    }

    return infrastructure.database_mode === 'external' && infrastructure.status !== 'verified';
}

function storageSectionNeedsSave(form, infrastructure) {
    if (form.storage_mode !== infrastructure.storage_mode) {
        return true;
    }

    if (form.storage_mode !== 'external') {
        return false;
    }

    if (storageSectionChanged(form, infrastructure)) {
        return true;
    }

    return infrastructure.storage_mode === 'external' && infrastructure.status !== 'verified';
}

export function isDatabaseLinked(infrastructure) {
    if (infrastructure.database_mode !== 'external' || ! infrastructure.database_config?.host) {
        return false;
    }

    const migration = infrastructure.database_migration_status;

    if (['queued', 'running', 'failed'].includes(migration ?? '')) {
        return false;
    }

    return infrastructure.status === 'verified';
}

export function isStorageLinked(infrastructure) {
    if (infrastructure.storage_mode !== 'external' || ! infrastructure.storage_config?.bucket) {
        return false;
    }

    const migration = infrastructure.storage_migration_status;

    if (['queued', 'running', 'failed'].includes(migration ?? '')) {
        return false;
    }

    return infrastructure.status === 'verified';
}

function syncDatabaseTestState(form, infrastructure, databaseTestPassed, databaseTestError) {
    if (! databaseSectionChanged(form, infrastructure) && isDatabaseLinked(infrastructure)) {
        databaseTestPassed.value = true;
        databaseTestError.value = '';

        return;
    }

    databaseTestPassed.value = false;
    databaseTestError.value = '';
}

function syncStorageTestState(form, infrastructure, storageTestPassed, storageTestError) {
    if (! storageSectionChanged(form, infrastructure) && isStorageLinked(infrastructure)) {
        storageTestPassed.value = true;
        storageTestError.value = '';

        return;
    }

    storageTestPassed.value = false;
    storageTestError.value = '';
}

export function useInfrastructureConnectionTest({
    form,
    infrastructure,
    canConfigureDatabase,
    canConfigureStorage,
    testDatabaseUrl,
    testStorageUrl,
}) {
    const databaseTestPassed = ref(
        isDatabaseLinked(infrastructure)
        && ! databaseSectionChanged(form, infrastructure),
    );
    const storageTestPassed = ref(
        isStorageLinked(infrastructure)
        && ! storageSectionChanged(form, infrastructure),
    );
    const databaseTestError = ref('');
    const storageTestError = ref('');
    const testingDatabase = ref(false);
    const testingStorage = ref(false);

    watch(
        () => [form.database_mode, form.database_config],
        () => syncDatabaseTestState(form, infrastructure, databaseTestPassed, databaseTestError),
        { deep: true },
    );

    watch(
        () => [form.storage_mode, form.storage_config],
        () => syncStorageTestState(form, infrastructure, storageTestPassed, storageTestError),
        { deep: true },
    );

    const databaseLinked = computed(() => isDatabaseLinked(infrastructure));

    const storageLinked = computed(() => isStorageLinked(infrastructure));

    const databaseChanged = computed(() => (
        canConfigureDatabase.value && databaseSectionNeedsSave(form, infrastructure)
    ));

    const storageChanged = computed(() => (
        canConfigureStorage.value && storageSectionNeedsSave(form, infrastructure)
    ));

    const needsDatabaseTest = computed(
        () => databaseChanged.value && form.database_mode === 'external',
    );

    const needsStorageTest = computed(
        () => storageChanged.value && form.storage_mode === 'external',
    );

    const canSaveDatabase = computed(() => {
        if (form.processing || ! databaseChanged.value) {
            return false;
        }

        if (needsDatabaseTest.value && ! databaseTestPassed.value) {
            return false;
        }

        return true;
    });

    const canSaveStorage = computed(() => {
        if (form.processing || ! storageChanged.value) {
            return false;
        }

        if (needsStorageTest.value && ! storageTestPassed.value) {
            return false;
        }

        return true;
    });

    const canSave = computed(() => canSaveDatabase.value || canSaveStorage.value);

    const saveBlockedReason = computed(() => {
        if (canSave.value || form.processing) {
            return '';
        }

        if (! databaseChanged.value && ! storageChanged.value) {
            return 'no_changes';
        }

        if (needsDatabaseTest.value && ! databaseTestPassed.value) {
            return 'database_test_required';
        }

        if (needsStorageTest.value && ! storageTestPassed.value) {
            return 'storage_test_required';
        }

        return '';
    });

    const databaseConnectionState = computed(() => {
        if (databaseTestError.value) {
            return 'error';
        }

        if (databaseLinked.value && ! databaseSectionChanged(form, infrastructure)) {
            return 'linked';
        }

        if (databaseTestPassed.value && databaseSectionChanged(form, infrastructure)) {
            return 'verified_pending_save';
        }

        if (databaseTestPassed.value) {
            return 'active';
        }

        if (needsDatabaseTest.value) {
            return 'test_required';
        }

        return 'idle';
    });

    const storageConnectionState = computed(() => {
        if (storageTestError.value) {
            return 'error';
        }

        if (storageLinked.value && ! storageSectionChanged(form, infrastructure)) {
            return 'linked';
        }

        if (storageTestPassed.value && storageSectionChanged(form, infrastructure)) {
            return 'verified_pending_save';
        }

        if (storageTestPassed.value) {
            return 'active';
        }

        if (needsStorageTest.value) {
            return 'test_required';
        }

        return 'idle';
    });

    const postJson = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message ?? 'Connection test failed.');
        }

        return data;
    };

    const testDatabase = async () => {
        testingDatabase.value = true;
        databaseTestError.value = '';

        try {
            await postJson(testDatabaseUrl, {
                database_config: form.database_config,
            });
            databaseTestPassed.value = true;
        } catch (error) {
            databaseTestPassed.value = false;
            databaseTestError.value = error.message ?? 'Connection test failed.';
        } finally {
            testingDatabase.value = false;
        }
    };

    const testStorage = async () => {
        testingStorage.value = true;
        storageTestError.value = '';

        try {
            await postJson(testStorageUrl, {
                storage_config: form.storage_config,
            });
            storageTestPassed.value = true;
        } catch (error) {
            storageTestPassed.value = false;
            storageTestError.value = error.message ?? 'Connection test failed.';
        } finally {
            testingStorage.value = false;
        }
    };

    return {
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
        canSave,
        canSaveDatabase,
        canSaveStorage,
        saveBlockedReason,
        testDatabase,
        testStorage,
        databaseSectionChanged: () => databaseSectionChanged(form, infrastructure),
        storageSectionChanged: () => storageSectionChanged(form, infrastructure),
    };
}
