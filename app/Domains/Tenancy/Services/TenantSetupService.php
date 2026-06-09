<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Channels\Models\EmailInbox;
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
        $chat = $this->channels->findActiveBySlug('chat');
        $chatSettings = $chat?->settings ?? [];

        return [
            $this->step(
                key: 'business_hours',
                title: 'Business hours & timezone',
                description: 'Set when your team is available. Live chat uses these hours when offline mode is set to business hours.',
                url: route('settings.sla'),
                required: true,
                complete: $this->isStepComplete('business_hours', $manual) || $this->hasConfiguredBusinessHours(),
            ),
            $this->step(
                key: 'email_inbox',
                title: 'Email inbox',
                description: 'Configure the support address that receives tickets and copy the inbound webhook URL for your mail provider.',
                url: route('settings.email'),
                required: true,
                complete: $this->isStepComplete('email_inbox', $manual) || EmailInbox::query()->where('is_active', true)->exists(),
            ),
            $this->step(
                key: 'email_outbound',
                title: 'Outbound email (SMTP)',
                description: 'Set SMTP or a connected mailbox so ticket replies reach customers.',
                url: route('settings.email'),
                required: true,
                complete: $this->isStepComplete('email_outbound', $manual) || $this->hasOutboundMailConfigured(),
            ),
            $this->step(
                key: 'chat_widget',
                title: 'Live chat widget',
                description: 'Copy the embed snippet and add it to your website. The widget key routes visitors to this workspace.',
                url: route('settings.channels'),
                required: true,
                complete: $this->isStepComplete('chat_widget', $manual) || filled($chatSettings['widget_key'] ?? null),
                meta: [
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
            ),
            $this->step(
                key: 'invite_team',
                title: 'Invite your team',
                description: 'Add agents and admins who will respond to tickets and chat.',
                url: route('settings.members'),
                required: true,
                complete: $this->isStepComplete('invite_team', $manual) || User::query()->count() > 1,
            ),
            $this->step(
                key: 'sla_policies',
                title: 'Review SLA policies',
                description: 'Confirm first-response and resolution targets match your support commitments.',
                url: route('settings.sla'),
                required: true,
                complete: $this->isStepComplete('sla_policies', $manual) || SlaPolicy::query()->exists(),
            ),
            $this->step(
                key: 'customer_portal',
                title: 'Customer portal & brand',
                description: 'Customize your help center branding, portal URL, and knowledge base.',
                url: route('settings.brands'),
                required: false,
                complete: $this->isStepComplete('customer_portal', $manual),
            ),
            $this->step(
                key: 'realtime',
                title: 'Real-time updates (recommended)',
                description: 'Run Redis and the websocket server so agents and chat visitors see messages instantly without refreshing.',
                url: route('settings'),
                required: false,
                complete: $this->isStepComplete('realtime', $manual),
            ),
        ];
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

        $mail = DB::table('mail_settings')->first();

        if (! $mail) {
            return false;
        }

        return filled($mail->smtp_host ?? null)
            || filled($mail->from_address ?? null)
            || ($mail->delivery_mode ?? null) === 'queue';
    }

    private function infrastructureNotes(): array
    {
        return [
            'inbound_webhook' => url('/api/v1/channels/inbound/email'),
            'central_webhook' => 'http://'.config('tenancy.central_app_domain').'/api/v1/channels/inbound/email',
            'realtime' => [
                'ws_url' => config('realtime.ws_url'),
                'commands' => [
                    'redis-server',
                    'npm run realtime',
                ],
            ],
            'queue' => [
                'worker' => 'php artisan tenants:run queue:work',
                'scheduler' => 'php artisan schedule:work',
            ],
            'sync_routes' => 'php artisan tenants:sync-routes',
        ];
    }
}
