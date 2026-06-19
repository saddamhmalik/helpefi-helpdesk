<?php

return [
    'enabled' => (bool) env('TENANT_BYO_ENABLED', false),

    'egress_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HELPEFI_EGRESS_IPS', '')),
    ))),

    'allowed_database_drivers' => ['mysql'],

    'reserved_mysql_databases' => [
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
    ],

    'allowed_storage_drivers' => ['s3', 'r2'],

    'storage_key_prefix' => 'helpefi',

    'connection_timeout_seconds' => (int) env('TENANT_BYO_CONNECTION_TIMEOUT', 10),

    'persistent_connections' => (bool) env('TENANT_BYO_DB_PERSISTENT', false),

    'compress_connections' => (bool) env('TENANT_BYO_DB_COMPRESS', true),

    'mysql_client_binary_path' => env('TENANT_BYO_MYSQL_CLIENT_BINARY_PATH'),

    'database_migration_timeout_seconds' => (int) env('TENANT_BYO_DATABASE_MIGRATION_TIMEOUT', 3600),

    'verify_test_object' => '.helpefi-verify',

    'signed_url_minutes' => (int) env('TENANT_BYO_SIGNED_URL_MINUTES', 30),

    'health_failure_threshold' => (int) env('TENANT_BYO_HEALTH_FAILURE_THRESHOLD', 3),

    'verify_rate_limit_per_minute' => (int) env('TENANT_BYO_VERIFY_RATE_LIMIT', 5),

    'requires_enterprise_plan' => (bool) env('TENANT_BYO_REQUIRES_ENTERPRISE', true),

    'drop_managed_database_after_migration' => (bool) env('TENANT_BYO_DROP_MANAGED_DB_AFTER_MIGRATION', false),

    'delete_local_files_after_storage_migration' => (bool) env('TENANT_BYO_DELETE_LOCAL_FILES_AFTER_MIGRATION', true),

    'alert_on_failure' => (bool) env('TENANT_BYO_ALERT_ON_FAILURE', true),

    'alert_emails' => env('TENANT_BYO_ALERT_EMAILS', ''),

    'telescope_redact_external_queries' => (bool) env('TENANT_BYO_TELESCOPE_REDACT_EXTERNAL', true),

    'test_on_save' => (bool) env('TENANT_INFRASTRUCTURE_TEST_ON_SAVE', true),
];
