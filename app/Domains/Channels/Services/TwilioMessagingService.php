<?php

namespace App\Domains\Channels\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Channels\Repositories\MessagingSettingRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class TwilioMessagingService
{
    public function __construct(
        private MessagingSettingRepository $settings,
        private FeatureEntitlementChecker $entitlements,
    ) {
    }

    public function client(): Client
    {
        $setting = $this->settings->current();

        if (! $setting->is_active || ! $setting->account_sid || ! $setting->auth_token) {
            throw new \InvalidArgumentException('Twilio messaging is not configured.');
        }

        return new Client($setting->account_sid, $setting->auth_token);
    }

    public function send(string $to, string $body, string $channelType): string
    {
        $this->entitlements->assertFeature('channels');

        $setting = $this->settings->current();
        $from = $channelType === 'whatsapp'
            ? $this->normalizeWhatsAppFrom($setting->whatsapp_from)
            : $setting->sms_from;

        if (! $from) {
            throw new \InvalidArgumentException('Twilio sender number is not configured.');
        }

        $toAddress = $channelType === 'whatsapp'
            ? $this->normalizeWhatsAppTo($to)
            : $this->normalizePhone($to);

        $message = $this->client()->messages->create($toAddress, [
            'from' => $from,
            'body' => strip_tags($body),
        ]);

        return (string) $message->sid;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? $phone;

        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }

    public function normalizeWhatsAppFrom(?string $from): string
    {
        $from = $this->normalizePhone((string) $from);

        return str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:'.$from;
    }

    public function normalizeWhatsAppTo(string $to): string
    {
        $to = $this->normalizePhone($to);

        return str_starts_with($to, 'whatsapp:') ? $to : 'whatsapp:'.$to;
    }

    public function stripWhatsAppPrefix(string $address): string
    {
        return str_replace('whatsapp:', '', $address);
    }
}
