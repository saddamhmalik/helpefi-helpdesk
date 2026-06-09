<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Sla\Models\BusinessHours;
use App\Domains\Sla\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantSetupService
{
    public function __construct(
        private HelpdeskSettingRepository $settings,
        private ChannelRepository $channels,
    ) {
    }

    public function shouldRedirect(): bool
    {
        if (! tenant('id')) {
            return false;
        }

        $setting = $this->settings->current();

        return $setting->setup_completed_at === null;
    }

    public function hasIncompleteRequiredConfiguration(): bool
    {
        return $this->incompleteRequiredWarnings() !== [];
    }

    public function incompleteRequiredWarnings(): array
    {
        if (! tenant('id')) {
            return [];
        }

        $warnings = [];

        foreach ($this->requiredStepDefinitions() as $definition) {
            if ($this->isConfigured($definition['key'])) {
                continue;
            }

            $warnings[] = [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'message' => $definition['warning'],
                'url' => $definition['url'],
            ];
        }

        return $warnings;
    }

    public function snapshot(): array
    {
        $setting = $this->settings->current();
        $steps = $this->steps($setting);
        $required = collect($steps)->where('required', true);
        $completed = $required->where('complete', true);

        return [
            'completed' => $setting->setup_completed_at !== null,
            'progress' => [
                'completed' => $completed->count(),
                'total' => $required->count(),
            ],
            'workspace' => [
                'name' => tenant('name'),
                'domain' => request()->getHost(),
                'url' => request()->getSchemeAndHttpHost(),
            ],
            'steps' => array_values($steps),
            'infrastructure' => $this->infrastructureNotes(),
        ];
    }

    public function completeStep(string $step): array
    {
        $setting = $this->settings->current();
        $completed = $setting->setup_steps_completed ?? [];

        if (! in_array($step, $completed, true)) {
            $completed[] = $step;
        }

        $this->settings->update($setting, [
            'setup_steps_completed' => $completed,
        ]);

        return $this->snapshot();
    }

    public function finish(): array
    {
        $setting = $this->settings->current();

        $this->settings->update($setting, [
            'setup_completed_at' => now(),
        ]);

        return $this->snapshot();
    }

    private function steps(HelpdeskSetting $setting): array
    {
        $manual = $setting->setup_steps_completed ?? [];
        $definitions = $this->requiredStepDefinitions();
        $optional = $this->optionalStepDefinitions($manual);

        return array_map(function (array $definition) use ($manual) {
            $key = $definition['key'];

            return $this->step(
                key: $key,
                title: $definition['title'],
                description: $definition['description'],
                url: $definition['url'],
                required: $definition['required'],
                complete: $this->isStepComplete($key, $manual) || $this->isConfigured($key),
                meta: $definition['meta'] ?? [],
            );
        }, array_merge($definitions, $optional));
    }

    private function requiredStepDefinitions(): array
    {
        $chat = $this->channels->findActiveBySlug('chat');
        $chatSettings = $chat?->settings ?? [];

        return [
            [
                'key' => 'business_hours',
                'title' => 'Business hours & timezone',
                'description' => 'Set when your team is available. Live chat uses these hours when offline mode is set to business hours.',
                'warning' => 'Business hours are not configured. Live chat and SLA schedules may not reflect your working hours.',
                'url' => route('settings.sla'),
                'required' => true,
            ],
            [
                'key' => 'email_inbox',
                'title' => 'Email inbox',
                'description' => 'Configure the support address that receives tickets and copy the inbound webhook URL for your mail provider.',
                'warning' => 'Connect inbound email via webhook forwarding or mailbox polling so tickets can be created from email.',
                'url' => route('settings.email'),
                'required' => true,
            ],
            [
                'key' => 'email_outbound',
                'title' => 'Outbound email (SMTP)',
                'description' => 'Set SMTP or a connected mailbox so ticket replies reach customers.',
                'warning' => 'Enable outbound email and configure SMTP so ticket replies reach customers.',
                'url' => route('settings.email'),
                'required' => true,
            ],
            [
                'key' => 'chat_widget',
                'title' => 'Live chat widget',
                'description' => 'Copy the embed snippet and add it to your website. The widget key routes visitors to this workspace.',
                'warning' => 'Live chat widget is not ready. Generate a widget key and embed it on your site.',
                'url' => route('settings.channels'),
                'required' => true,
                'meta' => [
                    'widget_key' => $chatSettings['widget_key'] ?? null,
                    'embed_url' => url('/widget/helpdesk.js'),
                    'embed_snippet' => filled($chatSettings['widget_key'] ?? null)
                        ? sprintf(
                            '<script src="%s" data-key="%s" async></script>',
                            e(url('/widget/helpdesk.js')),
                            e($chatSettings['widget_key']),
                        )
                        : null,
                ],
            ],
            [
                'key' => 'invite_team',
                'title' => 'Invite your team',
                'description' => 'Add agents and admins who will respond to tickets and chat.',
                'warning' => 'No additional team members have been invited. You are the only agent in this workspace.',
                'url' => route('settings.members'),
                'required' => true,
            ],
            [
                'key' => 'sla_policies',
                'title' => 'Review SLA policies',
                'description' => 'Confirm first-response and resolution targets match your support commitments.',
                'warning' => 'SLA policies are not configured. Response targets and escalations will not run.',
                'url' => route('settings.sla'),
                'required' => true,
            ],
        ];
    }

    private function optionalStepDefinitions(array $manual): array
    {
        return [
            [
                'key' => 'customer_portal',
                'title' => 'Customer portal & brand',
                'description' => 'Customize your help center branding, portal URL, and knowledge base.',
                'warning' => '',
                'url' => route('settings.brands'),
                'required' => false,
            ],
        ];
    }

    private function isConfigured(string $key): bool
    {
        $chat = $this->channels->findActiveBySlug('chat');
        $chatSettings = $chat?->settings ?? [];

        return match ($key) {
            'business_hours' => $this->hasConfiguredBusinessHours(),
            'email_inbox' => $this->hasInboundEmailConfigured(),
            'email_outbound' => $this->hasOutboundMailConfigured(),
            'chat_widget' => filled($chatSettings['widget_key'] ?? null),
            'invite_team' => User::query()->count() > 1,
            'sla_policies' => SlaPolicy::query()->exists(),
            'customer_portal' => false,
            default => false,
        };
    }

    private function hasInboundEmailConfigured(): bool
    {
        return EmailInbox::query()
            ->where('is_active', true)
            ->get()
            ->contains(fn (EmailInbox $inbox) => $this->inboxIsConfigured($inbox));
    }

    private function inboxIsConfigured(EmailInbox $inbox): bool
    {
        if ($inbox->isOAuthConnected()) {
            return true;
        }

        if ($inbox->poll_enabled && filled($inbox->mailbox_host) && filled($inbox->mailbox_username)) {
            return true;
        }

        if ($inbox->address !== 'support@helpdesk.test') {
            return true;
        }

        return $inbox->updated_at?->gt($inbox->created_at) === true;
    }

    private function step(
        string $key,
        string $title,
        string $description,
        string $url,
        bool $required,
        bool $complete,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'required' => $required,
            'complete' => $complete,
            'meta' => $meta,
        ];
    }

    private function isStepComplete(string $key, array $manual): bool
    {
        return in_array($key, $manual, true);
    }

    private function hasConfiguredBusinessHours(): bool
    {
        $hours = BusinessHours::query()->orderBy('id')->first();

        if (! $hours) {
            return false;
        }

        return $hours->updated_at?->gt($hours->created_at) === true
            || $hours->timezone !== config('app.timezone');
    }

    private function hasOutboundMailConfigured(): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('mail_settings')) {
            return false;
        }

        $mail = MailSetting::query()->first();

        if (! $mail || ! $mail->enabled) {
            return false;
        }

        if ($mail->use_inbox_smtp && $mail->email_inbox_id) {
            $inbox = EmailInbox::query()->find($mail->email_inbox_id);

            return $inbox !== null && (
                $inbox->isOAuthConnected()
                || (filled($inbox->mailbox_host) && filled($inbox->mailbox_username))
            );
        }

        return $mail->driver === 'smtp' && filled($mail->host);
    }

    private function infrastructureNotes(): array
    {
        return [
            'inbound_webhook' => url('/api/v1/channels/inbound/email'),
            'central_webhook' => 'http://'.config('tenancy.central_app_domain').'/api/v1/channels/inbound/email',
            'sync_routes' => 'php artisan tenants:sync-routes',
        ];
    }
}
