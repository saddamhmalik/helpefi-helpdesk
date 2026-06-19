<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Tenancy\Jobs\VerifyTenantInfrastructureJob;
use App\Domains\Tenancy\Services\ExternalTenantBackupService;
use App\Domains\Tenancy\Services\TenantByoEligibilityService;
use App\Domains\Tenancy\Services\TenantInfrastructureBackupService;
use App\Domains\Tenancy\Services\TenantInfrastructureMigrationService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InfrastructureController extends Controller
{
    public function __construct(
        private TenantInfrastructureService $infrastructure,
        private TenantInfrastructureMigrationService $migrations,
        private ExternalTenantBackupService $externalBackups,
        private TenantInfrastructureBackupService $backupManagement,
        private TenantByoEligibilityService $eligibility,
    ) {
    }

    public function index(): Response
    {
        $tenant = tenant();

        return Inertia::render('Settings/Infrastructure', [
            'infrastructure' => $this->infrastructure->snapshot($tenant, tenantSelfService: true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = tenant();

        $this->infrastructure->update($tenant, $this->validatedInfrastructure($request), tenantSelfService: true);

        return back()->with('success', 'Infrastructure settings saved.');
    }

    public function testDatabase(Request $request): JsonResponse
    {
        try {
            $tenant = tenant();
            $this->eligibility->assertCanConfigureDatabase($tenant, includeLegacyAllowlist: false);

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
                fn () => $this->infrastructure->testDatabaseCredentials($tenant, $data['database_config']),
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationFailure($exception);
        }
    }

    public function testStorage(Request $request): JsonResponse
    {
        try {
            $tenant = tenant();
            $this->eligibility->assertCanConfigureStorage($tenant, includeLegacyAllowlist: false);

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
                fn () => $this->infrastructure->testStorageCredentials($tenant, $data['storage_config']),
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationFailure($exception);
        }
    }

    public function verify(): RedirectResponse
    {
        $tenant = tenant();

        VerifyTenantInfrastructureJob::dispatch($tenant->id);

        return back()->with('success', 'Infrastructure verification queued.');
    }

    public function migrateDatabase(): RedirectResponse
    {
        $this->eligibility->assertCanConfigureDatabase(tenant(), includeLegacyAllowlist: false);

        $this->migrations->queueDatabaseMigration(tenant());

        return back()->with('success', 'Database migration queued.');
    }

    public function migrateStorage(): RedirectResponse
    {
        $this->eligibility->assertCanConfigureStorage(tenant(), includeLegacyAllowlist: false);

        $this->migrations->queueStorageMigration(tenant());

        return back()->with('success', 'Storage migration queued.');
    }

    public function exportBackup(): RedirectResponse
    {
        try {
            $this->externalBackups->queueExportDatabaseToCustomerBucket(tenant());

            return back()->with('success', 'Database backup export queued. Refresh this page for status.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    public function updateAutoBackup(Request $request): RedirectResponse
    {
        try {
            $data = $request->validate([
                'enabled' => ['required', 'boolean'],
                'frequency' => ['required', 'string', 'in:daily,weekly'],
                'weekday' => ['required', 'integer', 'min:0', 'max:6'],
                'time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            ]);

            $this->backupManagement->updateSchedule(tenant(), $data);

            return back()->with('success', 'Automatic backup schedule saved.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    public function updateBackup(Request $request, string $backup): RedirectResponse
    {
        try {
            $data = $request->validate([
                'label' => ['required', 'string', 'max:255'],
            ]);

            $this->backupManagement->updateBackupLabel(tenant(), $backup, $data['label']);

            return back()->with('success', 'Backup updated.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    public function destroyBackup(string $backup): RedirectResponse
    {
        try {
            $this->backupManagement->deleteBackup(tenant(), $backup);

            return back()->with('success', 'Backup deleted.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    private function validatedInfrastructure(Request $request): array
    {
        return $request->validate([
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
