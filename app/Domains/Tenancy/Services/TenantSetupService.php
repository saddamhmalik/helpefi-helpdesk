<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Sla\Models\BusinessHours;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Tenancy\Support\BootstrapDemoContent;
use App\Models\User;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function sharedAdminMenuState(): array
    {
        if (! tenant('id')) {
            return [
                'warnings' => [],
                'guide_dismissed' => true,
            ];
        }

        if (app()->environment('testing')) {
            return [
                'warnings' => $this->incompleteRequiredWarnings(),
                'guide_dismissed' => ! $this->shouldRedirect(),
            ];
        }

        return Cache::remember(TenantCache::key('setup.admin_menu'), 120, fn () => [
            'warnings' => $this->incompleteRequiredWarnings(),
            'guide_dismissed' => ! $this->shouldRedirect(),
        ]);
    }

    public static function forgetAdminMenuCache(): void
    {
        if (! tenant('id')) {
            return;
        }

        Cache::forget(TenantCache::key('setup.admin_menu'));
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

    public function stepRules(): array
    {
        return [
            'step' => ['required', 'string', Rule::in($this->stepKeys())],
        ];
    }

    public function completeStep(string $step): array
    {
        if (! in_array($step, $this->stepKeys(), true)) {
            throw ValidationException::withMessages([
                'step' => 'Unknown setup step.',
            ]);
        }

        $setting = $this->settings->current();
        $completed = $setting->setup_steps_completed ?? [];

        if (! in_array($step, $completed, true)) {
            $completed[] = $step;
        }

        $this->settings->update($setting, [
            'setup_steps_completed' => $completed,
        ]);

        self::forgetAdminMenuCache();

        return $this->snapshot();
    }

    public function finish(): array
    {
        $this->assertRequiredStepsComplete();

        $setting = $this->settings->current();

        if ($setting->setup_completed_at !== null) {
            return $this->snapshot();
        }

        $this->settings->update($setting, [
            'setup_completed_at' => now(),
        ]);

        self::forgetAdminMenuCache();

        return $this->snapshot();
    }

    public function requiredStepsComplete(): bool
    {
        $steps = collect($this->snapshot()['steps'])->where('required', true);

        return $steps->isNotEmpty() && $steps->every(fn (array $step) => $step['complete']);
    }

    private function assertRequiredStepsComplete(): void
    {
        if ($this->requiredStepsComplete()) {
            return;
        }

        throw ValidationException::withMessages([
            'setup' => 'Complete all required setup steps before finishing.',
        ]);
    }

    private function stepKeys(): array
    {
        return collect($this->requiredStepDefinitions())
            ->merge($this->optionalStepDefinitions([]))
            ->pluck('key')
            ->all();
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
                minutes: $definition['minutes'] ?? null,
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
                'title' => 'Set your working hours',
                'description' => "Tell customers when you're online. Chat offline mode and SLA timers follow these hours automatically.",
                'warning' => "Business hours aren't set yet — chat and SLA schedules may not match when your team actually works.",
                'url' => route('settings.sla'),
                'required' => true,
                'minutes' => 2,
            ],
            [
                'key' => 'email',
                'title' => 'Connect your support inbox',
                'description' => 'Route customer emails into one shared queue and send replies without leaving the helpdesk.',
                'warning' => "Email isn't connected yet. Set up inbound mail and outbound SMTP so tickets flow end to end.",
                'url' => route('settings.email'),
                'required' => true,
                'minutes' => 5,
            ],
            [
                'key' => 'chat_widget',
                'title' => 'Add live chat to your site',
                'description' => 'Drop a snippet on your website and turn visitor chats into tickets in the same inbox.',
                'warning' => "Live chat isn't live yet. Generate a widget key and embed it on your site.",
                'url' => route('settings.channels'),
                'required' => true,
                'minutes' => 3,
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
                'title' => 'Bring your team aboard',
                'description' => 'Invite agents and admins so everyone can pick up tickets from the same queue.',
                'warning' => "You're flying solo for now. Invite teammates so tickets don't pile up on one person.",
                'url' => route('settings.members'),
                'required' => true,
                'minutes' => 2,
            ],
            [
                'key' => 'sla_policies',
                'title' => 'Tune your SLA targets',
                'description' => 'Set first-response and resolution goals so breaches surface before customers have to chase you.',
                'warning' => "SLA policies aren't configured — response targets and escalations won't run yet.",
                'url' => route('settings.sla'),
                'required' => true,
                'minutes' => 3,
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
            'email' => $this->hasInboundEmailConfigured() && $this->hasOutboundMailConfigured(),
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

        if ($inbox->address !== BootstrapDemoContent::DEMO_INBOX_ADDRESS) {
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
        ?int $minutes = null,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'required' => $required,
            'complete' => $complete,
            'meta' => $meta,
            'minutes' => $minutes,
        ];
    }

    private function isStepComplete(string $key, array $manual): bool
    {
        if ($key === 'email') {
            return in_array('email', $manual, true)
                || in_array('email_inbox', $manual, true)
                || in_array('email_outbound', $manual, true);
        }

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
