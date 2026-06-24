<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Repositories\PlatformLifecycleEmailRepository;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PlatformTenantReminderService
{
    public function __construct(
        private PlatformMailService $mail,
        private PlatformTenantAdminResolver $admins,
        private PlatformLifecycleEmailRepository $sends,
        private TrialNurtureService $trialNurture,
        private SubscriptionEndingReminderService $subscriptionEnding,
    ) {
    }

    public function statusForTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('subscription');
        $sent = $this->sends->sendsForTenant($tenant->id)->keyBy('template_slug');

        $trial = $this->buildTrialReminders($tenant, $sent);
        $subscription = $this->buildSubscriptionReminders($tenant, $sent);

        return [
            'trial' => $trial,
            'subscription_ending' => $subscription,
        ];
    }

    public function resend(Tenant $tenant, string $slug): void
    {
        if (! in_array($slug, PlatformEmailTemplate::lifecycleReminderSlugs(), true)) {
            throw ValidationException::withMessages([
                'template_slug' => 'This email cannot be resent from platform admin.',
            ]);
        }

        $admin = $this->admins->resolve($tenant);

        if (! filled($admin['email'])) {
            throw ValidationException::withMessages([
                'template_slug' => 'No admin email is available for this workspace.',
            ]);
        }

        $this->mail->sendLifecycleReminder(
            $tenant,
            $slug,
            (string) $admin['name'],
            (string) $admin['email'],
        );

        $this->sends->recordSend($tenant->id, $slug);
    }

    private function buildTrialReminders(Tenant $tenant, $sent): array
    {
        $onTrial = $tenant->subscription?->isOnTrial() ?? false;
        $currentDay = $onTrial ? $this->trialNurture->currentTrialDay($tenant) : null;

        return collect(TrialNurtureService::reminderDefinitions())
            ->map(function (array $definition, string $slug) use ($sent, $onTrial, $currentDay) {
                $sentAt = $sent->get($slug)?->sent_at;

                return [
                    'slug' => $slug,
                    'label' => $definition['label'],
                    'sent_at' => $sentAt instanceof Carbon ? $sentAt->toIso8601String() : null,
                    'sent' => $sentAt !== null,
                    'due' => $onTrial && $currentDay !== null && $currentDay >= $definition['day'],
                ];
            })
            ->values()
            ->all();
    }

    private function buildSubscriptionReminders(Tenant $tenant, $sent): array
    {
        $eligible = $this->subscriptionEnding->isEligible($tenant);

        return collect(SubscriptionEndingReminderService::reminderDefinitions())
            ->map(function (array $definition, string $slug) use ($sent, $tenant, $eligible) {
                $sentAt = $sent->get($slug)?->sent_at;

                return [
                    'slug' => $slug,
                    'label' => $definition['label'],
                    'sent_at' => $sentAt instanceof Carbon ? $sentAt->toIso8601String() : null,
                    'sent' => $sentAt !== null,
                    'due' => $eligible && $this->subscriptionEnding->isDue($tenant, $definition['days_before_end']),
                ];
            })
            ->values()
            ->all();
    }
}
