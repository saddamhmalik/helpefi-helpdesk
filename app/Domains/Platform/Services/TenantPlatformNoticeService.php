<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformNotice;
use App\Domains\Platform\Repositories\PlatformNoticeDismissalRepository;
use App\Domains\Platform\Repositories\PlatformNoticeRepository;
use App\Domains\Platform\Support\PlatformNoticeUrlGenerator;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Models\User;

class TenantPlatformNoticeService
{
    public function __construct(
        private PlatformNoticeRepository $notices,
        private PlatformNoticeDismissalRepository $dismissals,
        private PlatformNoticeUrlGenerator $urls,
    ) {
    }

    public function activeForUser(User $user): array
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            return [];
        }

        if ($user->hasRole('customer')) {
            return [];
        }

        $notices = $this->notices->activeForTenant($tenantId);
        $dismissed = $this->dismissals->dismissedForUser($user, $notices->pluck('id'))->flip();

        return $notices
            ->filter(function (PlatformNotice $notice) use ($user, $dismissed) {
                if ($notice->audience === PlatformNotice::AUDIENCE_ADMINS && ! $user->hasRole('admin')) {
                    return false;
                }

                if ($notice->dismissible && $dismissed->has($notice->id)) {
                    return false;
                }

                return true;
            })
            ->map(fn (PlatformNotice $notice) => $this->present($notice))
            ->values()
            ->all();
    }

    public function dismiss(User $user, int $platformNoticeId): void
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            abort(404);
        }

        $notice = $this->notices->find($platformNoticeId);

        if (! $notice->isCurrentlyActive() || ! $notice->targetsTenant($tenantId)) {
            abort(404);
        }

        if ($notice->audience === PlatformNotice::AUDIENCE_ADMINS && ! $user->hasRole('admin')) {
            abort(403);
        }

        if (! $notice->dismissible) {
            abort(422, 'This notice cannot be dismissed.');
        }

        $this->dismissals->dismiss($user, $platformNoticeId);
    }

    private function present(PlatformNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body_html' => $notice->body_html
                ? MessageBodySanitizer::sanitize($notice->body_html)
                : null,
            'notice_type' => $notice->notice_type,
            'priority' => $notice->priority,
            'dismissible' => $notice->dismissible,
            'image_url' => $this->urls->imageUrl($notice),
            'starts_at' => $notice->starts_at?->toIso8601String(),
            'ends_at' => $notice->ends_at?->toIso8601String(),
        ];
    }
}
