<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Repositories\PlatformLifecycleEmailRepository;
use App\Domains\Tenancy\Services\CentralSeoService;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class TrialNurtureService
{
    private const DAY_SCHEDULE = [
        1 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1,
        3 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3,
        5 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_5,
        7 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_7,
        10 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_10,
        12 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_12,
        13 => PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_13,
    ];

    public function __construct(
        private PlatformMailService $mail,
        private PlatformTenantAdminResolver $admins,
        private PlatformLifecycleEmailRepository $sends,
    ) {
    }

    public function dispatchDueEmails(): int
    {
        $sent = 0;

        foreach ($this->trialTenants() as $tenant) {
            $day = $this->trialDay($tenant);
            $slug = self::DAY_SCHEDULE[$day] ?? null;

            if ($slug === null || $this->sends->alreadySent($tenant->id, $slug)) {
                continue;
            }

            $admin = $this->admins->resolve($tenant);

            if (! filled($admin['email'])) {
                continue;
            }

            $this->mail->sendLifecycleReminder(
                $tenant,
                $slug,
                (string) $admin['name'],
                (string) $admin['email'],
            );

            $this->sends->recordSend($tenant->id, $slug);

            $sent++;
        }

        return $sent;
    }

    public static function reminderDefinitions(): array
    {
        return [
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_1 => [
                'label' => 'Trial day 1 — Connect channels',
                'day' => 1,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_3 => [
                'label' => 'Trial day 3 — Knowledge base',
                'day' => 3,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_5 => [
                'label' => 'Trial day 5 — AI Copilot',
                'day' => 5,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_7 => [
                'label' => 'Trial day 7 — Invite team',
                'day' => 7,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_10 => [
                'label' => 'Trial day 10 — Switch story',
                'day' => 10,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_12 => [
                'label' => 'Trial day 12 — Plan selection',
                'day' => 12,
            ],
            PlatformEmailTemplate::SLUG_TRIAL_NURTURE_DAY_13 => [
                'label' => 'Trial day 13 — Last day',
                'day' => 13,
            ],
        ];
    }

    public function currentTrialDay(Tenant $tenant): ?int
    {
        if (! $tenant->subscription?->isOnTrial()) {
            return null;
        }

        return $this->trialDay($tenant);
    }

    private function trialTenants(): Collection
    {
        return Tenant::query()
            ->where('is_blocked', false)
            ->whereHas('subscription', fn ($query) => $query
                ->where('status', 'trial')
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now()))
            ->with('subscription')
            ->get();
    }

    private function trialDay(Tenant $tenant): int
    {
        $startedAt = $tenant->created_at ?? now();

        return max(1, (int) $startedAt->startOfDay()->diffInDays(now()->startOfDay()) + 1);
    }

    public static function pricingUrl(): string
    {
        return app(CentralSeoService::class)->siteUrl().'/pricing';
    }
}
