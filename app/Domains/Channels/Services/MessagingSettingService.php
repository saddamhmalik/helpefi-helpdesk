<?php

namespace App\Domains\Channels\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Channels\Repositories\MessagingSettingRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Support\Str;

class MessagingSettingService
{
    public function __construct(
        private MessagingSettingRepository $settings,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
    ) {
    }

    public function snapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'is_active' => $setting->is_active,
            'account_sid' => $setting->account_sid ?? '',
            'whatsapp_from' => $setting->whatsapp_from ?? '',
            'sms_from' => $setting->sms_from ?? '',
            'webhook_token' => $setting->webhook_token,
            'has_auth_token' => ! empty($setting->auth_token),
            'webhook_url' => url('/api/v1/channels/inbound/twilio'),
            'feature_available' => $this->entitlements->canUseFeature('channels'),
        ];
    }

    public function update(array $data): array
    {
        $this->entitlements->assertFeature('channels');

        $setting = $this->settings->current();
        $payload = [
            'is_active' => $data['is_active'] ?? false,
            'account_sid' => $data['account_sid'] ?? null,
            'whatsapp_from' => $data['whatsapp_from'] ?? null,
            'sms_from' => $data['sms_from'] ?? null,
        ];

        if (! empty($data['auth_token'])) {
            $payload['auth_token'] = $data['auth_token'];
        }

        if (! $setting->webhook_token) {
            $payload['webhook_token'] = Str::random(32);
        }

        $this->settings->update($setting, $payload);
        $this->audit->record('channel.messaging_updated', properties: ['is_active' => $payload['is_active']]);

        return $this->snapshot();
    }
}
