<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Repositories\PlatformLifecycleEmailRepository;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class SubscriptionEndingReminderService
{
    private const DAYS_BEFORE_END = [
        7 => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_7_DAYS,
        3 => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_3_DAYS,
        1 => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_1_DAY,
        0 => PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_FINAL,
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

        foreach ($this->eligibleTenants() as $tenant) {
            $daysRemaining = $this->daysUntilAccessEnds($tenant);

            if ($daysRemaining === null) {
                continue;
            }

            $slug = self::DAYS_BEFORE_END[$daysRemaining] ?? null;

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
            PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_7_DAYS => [
                'label' => '7 days before access ends',
                'days_before_end' => 7,
            ],
            PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_3_DAYS => [
                'label' => '3 days before access ends',
                'days_before_end' => 3,
            ],
            PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_1_DAY => [
                'label' => '1 day before access ends',
                'days_before_end' => 1,
            ],
            PlatformEmailTemplate::SLUG_SUBSCRIPTION_ENDING_FINAL => [
                'label' => 'Last day of access',
                'days_before_end' => 0,
            ],
        ];
    }

    public function isEligible(Tenant $tenant): bool
    {
        return $this->daysUntilAccessEnds($tenant) !== null
            || $this->sends->sendsForTenant($tenant->id)
                ->contains(fn ($send) => in_array($send->template_slug, PlatformEmailTemplate::subscriptionEndingSlugs(), true));
    }

    private function eligibleTenants(): Collection
    {
        return Tenant::query()
            ->where('is_blocked', false)
            ->whereHas('subscription', fn ($query) => $query
                ->whereNotNull('access_ends_at')
                ->where('access_ends_at', '>', now())
                ->where(function ($builder) {
                    $builder
                        ->whereNotNull('cancelled_at')
                        ->orWhereIn('status', [
                            Subscription::STATUS_CANCELLED,
                            Subscription::STATUS_PAST_DUE,
                        ]);
                }))
            ->with('subscription')
            ->get();
    }

    private function daysUntilAccessEnds(Tenant $tenant): ?int
    {
        $accessEndsAt = $tenant->subscription?->access_ends_at;

        if ($accessEndsAt === null || $accessEndsAt->isPast()) {
            return null;
        }

        if ($tenant->subscription->cancelled_at === null
            && ! in_array($tenant->subscription->status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_PAST_DUE], true)) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($accessEndsAt->copy()->startOfDay()));
    }

    public function isDue(Tenant $tenant, int $daysBeforeEnd): bool
    {
        $daysRemaining = $this->daysUntilAccessEnds($tenant);

        return $daysRemaining !== null && $daysRemaining <= $daysBeforeEnd;
    }
}
