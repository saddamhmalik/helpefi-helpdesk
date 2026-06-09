<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformBackupScheduleService;
use App\Domains\Platform\Services\PlatformBackupService;
use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBackupController extends Controller
{
    public function __construct(
        private PlatformBackupService $backups,
        private PlatformBackupScheduleService $schedule,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Central/Admin/Backups/Index', [
            'backups' => $this->backups->list((int) $request->integer('per_page', 20)),
            'workspaces' => $this->backups->workspaceOptions()
                ->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ])
                ->values()
                ->all(),
            'retention_days' => (int) config('backup.retention_days', 30),
            'schedule' => $this->schedule->snapshot(),
            'schedule_options' => $this->schedule->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'string', 'in:central,tenant,all_tenants'],
            'tenant_id' => ['nullable', 'string', 'required_if:scope,tenant'],
        ]);

        $actor = $request->user('platform');
        assert($actor instanceof PlatformUser);

        match ($data['scope']) {
            'central' => $this->backups->queueCentral($actor),
            'tenant' => $this->backups->queueTenant($data['tenant_id'], $actor),
            'all_tenants' => $this->backups->queueAllTenants($actor),
        };

        return back()->with('success', 'Backup queued. It will appear in the list once complete.');
    }

    public function download(int $backup): StreamedResponse
    {
        return $this->backups->download($backup);
    }

    public function destroy(int $backup): RedirectResponse
    {
        $this->backups->delete($backup);

        return back()->with('success', 'Backup deleted.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'string', 'in:daily,weekly'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $this->schedule->update($data);

        return back()->with('success', 'Automatic backup schedule updated.');
    }
}
