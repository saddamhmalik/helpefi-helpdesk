<?php

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Models\PlatformNotice;
use App\Domains\Platform\Notifications\PlatformNoticeNotification;
use App\Domains\Platform\Repositories\PlatformNoticeRepository;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverPlatformNoticeJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 3;

    public function __construct(public int $noticeId)
    {
        $this->bindToCentralQueue();
    }

    public function handle(PlatformNoticeRepository $notices): void
    {
        $this->ensureCentralContext();

        $notice = $notices->find($this->noticeId);

        if ($notice->status !== PlatformNotice::STATUS_PUBLISHED || ! $notice->isCurrentlyActive()) {
            return;
        }

        $tenants = $this->resolveTenants($notice);

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($notice) {
                $this->notifyTenantUsers($notice);
            });
        }
    }

    private function resolveTenants(PlatformNotice $notice)
    {
        $query = Tenant::query()->where('is_blocked', false);

        if ($notice->target_scope === PlatformNotice::TARGET_SELECTED) {
            $query->whereIn('id', $notice->tenant_ids ?? []);
        }

        return $query->get();
    }

    private function notifyTenantUsers(PlatformNotice $notice): void
    {
        $query = User::query();

        if ($notice->audience === PlatformNotice::AUDIENCE_ADMINS) {
            $query->role('admin');
        } else {
            $query->whereHas('roles', fn ($builder) => $builder->whereIn('name', ['admin', 'agent']));
        }

        $query->each(fn (User $user) => $user->notify(new PlatformNoticeNotification($notice)));
    }
}
