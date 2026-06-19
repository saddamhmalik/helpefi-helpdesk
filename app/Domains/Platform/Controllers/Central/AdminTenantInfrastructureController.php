<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformTenantService;
use App\Domains\Tenancy\Jobs\VerifyTenantInfrastructureJob;
use App\Domains\Tenancy\Services\ExternalTenantBackupService;
use App\Domains\Tenancy\Services\TenantInfrastructureMigrationService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminTenantInfrastructureController extends Controller
{
    public function __construct(
        private PlatformTenantService $tenants,
        private TenantInfrastructureService $infrastructure,
        private TenantInfrastructureMigrationService $migrations,
        private ExternalTenantBackupService $externalBackups,
    ) {
    }

    public function show(string $tenant): Response
    {
        $record = $this->tenants->find($tenant);

        return Inertia::render('Central/Admin/Tenants/Infrastructure', [
            'tenant' => [
                'id' => $record->id,
                'name' => $record->name,
                'slug' => $record->slug,
            ],
            'infrastructure' => $this->infrastructure->snapshot($record),
        ]);
    }

    public function update(Request $request, string $tenant): RedirectResponse
    {
        $record = $this->tenants->find($tenant);

        $data = $request->validate([
            'database_mode' => ['required', 'string', 'in:managed,external'],
            'storage_mode' => ['required', 'string', 'in:managed,external'],
            'confirm_external_database' => ['sometimes', 'boolean'],
            'confirm_external_storage' => ['sometimes', 'boolean'],
            'database_config' => ['nullable', 'array'],
            'database_config.host' => ['nullable', 'string', 'max:255'],
            'database_config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_config.database' => ['nullable', 'string', 'max:64'],
            'database_config.username' => ['nullable', 'string', 'max:128'],
            'database_config.password' => ['nullable', 'string', 'max:255'],
            'database_config.read_only_username' => ['nullable', 'string', 'max:128'],
            'database_config.read_only_password' => ['nullable', 'string', 'max:255'],
            'database_config.ssl' => ['nullable', 'boolean'],
            'storage_config' => ['nullable', 'array'],
            'storage_config.driver' => ['nullable', 'string', 'in:s3,r2'],
            'storage_config.bucket' => ['nullable', 'string', 'max:255'],
            'storage_config.region' => ['nullable', 'string', 'max:64'],
            'storage_config.endpoint' => ['nullable', 'string', 'max:255'],
            'storage_config.access_key_id' => ['nullable', 'string', 'max:255'],
            'storage_config.secret_access_key' => ['nullable', 'string', 'max:255'],
            'storage_config.prefix' => ['nullable', 'string', 'max:255'],
        ]);

        $this->infrastructure->update($record, $data);

        return back()->with('success', 'Infrastructure settings saved.');
    }

    public function testDatabase(Request $request, string $tenant): JsonResponse
    {
        $record = $this->tenants->find($tenant);

        try {
            $data = $request->validate([
                'database_config' => ['required', 'array'],
                'database_config.host' => ['required', 'string', 'max:255'],
                'database_config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'database_config.database' => ['required', 'string', 'max:64'],
                'database_config.username' => ['required', 'string', 'max:128'],
                'database_config.password' => ['nullable', 'string', 'max:255'],
                'database_config.read_only_username' => ['nullable', 'string', 'max:128'],
                'database_config.read_only_password' => ['nullable', 'string', 'max:255'],
                'database_config.ssl' => ['nullable', 'boolean'],
            ]);

            return $this->jsonConnectionTest(
                fn () => $this->infrastructure->testDatabaseCredentials($record, $data['database_config']),
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationFailure($exception);
        }
    }

    public function testStorage(Request $request, string $tenant): JsonResponse
    {
        $record = $this->tenants->find($tenant);

        try {
            $data = $request->validate([
                'storage_config' => ['required', 'array'],
                'storage_config.driver' => ['required', 'string', 'in:s3,r2'],
                'storage_config.bucket' => ['required', 'string', 'max:255'],
                'storage_config.region' => ['nullable', 'string', 'max:64'],
                'storage_config.endpoint' => ['nullable', 'string', 'max:255'],
                'storage_config.access_key_id' => ['required', 'string', 'max:255'],
                'storage_config.secret_access_key' => ['nullable', 'string', 'max:255'],
                'storage_config.prefix' => ['nullable', 'string', 'max:255'],
            ]);

            return $this->jsonConnectionTest(
                fn () => $this->infrastructure->testStorageCredentials($record, $data['storage_config']),
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationFailure($exception);
        }
    }

    public function verify(string $tenant): RedirectResponse
    {
        $record = $this->tenants->find($tenant);

        VerifyTenantInfrastructureJob::dispatch($record->id);

        return back()->with('success', 'Infrastructure verification queued.');
    }

    public function migrateDatabase(string $tenant): RedirectResponse
    {
        $record = $this->tenants->find($tenant);

        $this->migrations->queueDatabaseMigration($record);

        return back()->with('success', 'Database migration queued.');
    }

    public function migrateStorage(string $tenant): RedirectResponse
    {
        $record = $this->tenants->find($tenant);

        $this->migrations->queueStorageMigration($record);

        return back()->with('success', 'Storage migration queued.');
    }

    public function exportBackup(string $tenant): RedirectResponse
    {
        try {
            $record = $this->tenants->find($tenant);
            $this->externalBackups->queueExportDatabaseToCustomerBucket($record);

            return back()->with('success', 'Database backup export queued.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    private function jsonConnectionTest(callable $test): JsonResponse
    {
        try {
            $test();

            return response()->json(['ok' => true]);
        } catch (ValidationException $exception) {
            return $this->jsonValidationFailure($exception);
        }
    }

    private function jsonValidationFailure(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => collect($exception->errors())->flatten()->first() ?? 'Connection test failed.',
        ], 422);
    }
}
