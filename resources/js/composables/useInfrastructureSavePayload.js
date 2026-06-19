function preservedDatabaseConfig(infrastructure) {
    if (infrastructure.database_mode !== 'external') {
        return null;
    }

    const config = infrastructure.database_config ?? {};

    return {
        host: config.host ?? '',
        port: config.port ?? 3306,
        database: config.database ?? '',
        username: config.username ?? '',
        password: '',
        read_only_username: config.read_only_username ?? '',
        read_only_password: '',
        ssl: config.ssl ?? false,
    };
}

function preservedStorageConfig(infrastructure) {
    if (infrastructure.storage_mode !== 'external') {
        return null;
    }

    const config = infrastructure.storage_config ?? {};

    return {
        driver: config.driver ?? 's3',
        bucket: config.bucket ?? '',
        region: config.region ?? '',
        endpoint: config.endpoint ?? '',
        access_key_id: config.access_key_id ?? '',
        secret_access_key: '',
        prefix: config.prefix ?? '',
    };
}

export function buildInfrastructureSavePayload(infrastructure, form, section) {
    if (section === 'database') {
        return {
            database_mode: form.database_mode,
            storage_mode: infrastructure.storage_mode ?? 'managed',
            confirm_external_database: form.confirm_external_database,
            confirm_external_storage: false,
            database_config: form.database_mode === 'external' ? { ...form.database_config } : null,
            storage_config: preservedStorageConfig(infrastructure),
        };
    }

    return {
        database_mode: infrastructure.database_mode ?? 'managed',
        storage_mode: form.storage_mode,
        confirm_external_database: false,
        confirm_external_storage: form.confirm_external_storage,
        database_config: preservedDatabaseConfig(infrastructure),
        storage_config: form.storage_mode === 'external' ? { ...form.storage_config } : null,
    };
}
