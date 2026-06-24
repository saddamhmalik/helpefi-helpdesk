<?php

namespace App\Domains\Growth\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Tenancy\Services\TenantSetupService;
use Illuminate\Support\Facades\DB;

class SetupHealthService
{
    public function __construct(
        private TenantSetupService $setup,
        private ChannelRepository $channels,
    ) {
    }

    public function snapshot(): array
    {
        $setup = $this->setup->snapshot();
        $checks = [
            $this->emailHealth(),
            $this->outboundMailHealth(),
            $this->chatHealth(),
            $this->realtimeHealth(),
            $this->integrationsHealth(),
        ];

        $issues = collect($checks)->filter(fn (array $check) => $check['status'] === 'error')->count();
        $warnings = collect($checks)->filter(fn (array $check) => $check['status'] === 'warning')->count();

        return [
            'overall' => $issues > 0 ? 'error' : ($warnings > 0 ? 'warning' : 'healthy'),
            'issue_count' => $issues,
            'warning_count' => $warnings,
            'setup' => $setup,
            'checks' => $checks,
        ];
    }

    private function emailHealth(): array
    {
        $inboxes = EmailInbox::query()->where('is_active', true)->get();

        if ($inboxes->isEmpty()) {
            return $this->check(
                key: 'email_inbound',
                label: 'Email inbound',
                status: 'error',
                message: 'No active email inbox configured.',
                url: route('settings.email'),
            );
        }

        $pollErrors = $inboxes->filter(fn (EmailInbox $inbox) => filled($inbox->poll_error));

        if ($pollErrors->isNotEmpty()) {
            return $this->check(
                key: 'email_inbound',
                label: 'Email inbound',
                status: 'error',
                message: 'Inbox polling errors: '.$pollErrors->pluck('address')->join(', '),
                url: route('settings.email'),
                meta: ['inbox_count' => $inboxes->count(), 'error_count' => $pollErrors->count()],
            );
        }

        return $this->check(
            key: 'email_inbound',
            label: 'Email inbound',
            status: 'healthy',
            message: $inboxes->count().' active inbox'.($inboxes->count() === 1 ? '' : 'es').' connected.',
            url: route('settings.email'),
            meta: ['inbox_count' => $inboxes->count()],
        );
    }

    private function outboundMailHealth(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('mail_settings')) {
            return $this->check(
                key: 'email_outbound',
                label: 'Outbound email',
                status: 'error',
                message: 'Outbound mail is not configured.',
                url: route('settings.email'),
            );
        }

        $mail = MailSetting::query()->first();

        if (! $mail?->enabled) {
            return $this->check(
                key: 'email_outbound',
                label: 'Outbound email',
                status: 'error',
                message: 'Outbound SMTP is disabled.',
                url: route('settings.email'),
            );
        }

        return $this->check(
            key: 'email_outbound',
            label: 'Outbound email',
            status: 'healthy',
            message: 'Outbound mail is enabled.',
            url: route('settings.email'),
        );
    }

    private function chatHealth(): array
    {
        $chat = $this->channels->findActiveBySlug('chat');
        $widgetKey = $chat?->settings['widget_key'] ?? null;

        if (! filled($widgetKey)) {
            return $this->check(
                key: 'chat_widget',
                label: 'Live chat widget',
                status: 'warning',
                message: 'Widget key is not generated. Visitors cannot start chat.',
                url: route('settings.channels'),
            );
        }

        return $this->check(
            key: 'chat_widget',
            label: 'Live chat widget',
            status: 'healthy',
            message: 'Chat widget is ready to embed.',
            url: route('settings.channels'),
        );
    }

    private function realtimeHealth(): array
    {
        if (! config('realtime.enabled', true)) {
            return $this->check(
                key: 'realtime',
                label: 'Realtime updates',
                status: 'warning',
                message: 'Realtime is disabled. Agents rely on polling.',
                url: route('settings.channels'),
            );
        }

        if (! filled(config('realtime.ws_url'))) {
            return $this->check(
                key: 'realtime',
                label: 'Realtime updates',
                status: 'warning',
                message: 'WebSocket URL is not configured.',
                url: route('settings.channels'),
            );
        }

        return $this->check(
            key: 'realtime',
            label: 'Realtime updates',
            status: 'healthy',
            message: 'Realtime WebSocket is configured.',
            url: route('settings.channels'),
        );
    }

    private function integrationsHealth(): array
    {
        $connections = IntegrationConnection::query()
            ->where('is_active', true)
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '')
            ->get();

        if ($connections->isEmpty()) {
            $active = IntegrationConnection::query()->where('is_active', true)->count();

            return $this->check(
                key: 'integrations',
                label: 'Integrations',
                status: $active > 0 ? 'healthy' : 'warning',
                message: $active > 0
                    ? $active.' active integration'.($active === 1 ? '' : 's').' with no recent errors.'
                    : 'No integrations connected yet.',
                url: route('settings.integrations'),
                meta: ['active_count' => $active],
            );
        }

        return $this->check(
            key: 'integrations',
            label: 'Integrations',
            status: 'error',
            message: 'Delivery errors on: '.$connections->pluck('provider')->join(', '),
            url: route('settings.integrations'),
            meta: ['providers' => $connections->pluck('provider')->values()->all()],
        );
    }

    private function check(
        string $key,
        string $label,
        string $status,
        string $message,
        string $url,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'url' => $url,
            'meta' => $meta,
        ];
    }
}
