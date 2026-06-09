<?php

namespace App\Domains\Channels\Services;

class InboundEmailPayloadNormalizer
{
    public function shouldSkip(array $payload): bool
    {
        if (! app(\App\Domains\Settings\Services\HelpdeskSettingService::class)->emailDetectAutoReplies()) {
            return false;
        }

        $headers = array_change_key_case($payload['headers'] ?? [], CASE_LOWER);

        if (($headers['auto-submitted'] ?? null) === 'auto-replied') {
            return true;
        }

        if (($headers['x-auto-response-suppress'] ?? null) !== null) {
            return true;
        }

        if (preg_match('/^(out of office|automatic reply|auto.?reply)/i', $payload['subject'] ?? '')) {
            return true;
        }

        return false;
    }

    public function normalize(array $payload): array
    {
        $settings = app(\App\Domains\Settings\Services\HelpdeskSettingService::class);

        if ($settings->emailUseOriginalSenderForForwarded()) {
            $payload = $this->resolveForwardedSender($payload);
        }

        if ($settings->emailUseReplyToAsRequester() && ! empty($payload['reply_to_email'])) {
            $payload['from_email'] = strtolower(trim($payload['reply_to_email']));
            $payload['from_name'] = $payload['reply_to_name'] ?? $payload['from_name'] ?? $payload['from_email'];
        }

        return $payload;
    }

    private function resolveForwardedSender(array $payload): array
    {
        $body = $payload['body'] ?? '';

        if (preg_match('/(?:From|De|Von):\s*(.+?)\s*<([^>]+@[^>]+)>/i', $body, $matches)) {
            $payload['from_name'] = trim($matches[1], " \t\"'");
            $payload['from_email'] = strtolower(trim($matches[2]));

            return $payload;
        }

        if (preg_match('/(?:From|De|Von):\s*([^\s<]+@[^\s>]+)/i', $body, $matches)) {
            $payload['from_email'] = strtolower(trim($matches[1]));
            $payload['from_name'] = $payload['from_name'] ?? $payload['from_email'];

            return $payload;
        }

        if (! empty($payload['original_from_email'])) {
            $payload['from_email'] = strtolower(trim($payload['original_from_email']));
            $payload['from_name'] = $payload['original_from_name'] ?? $payload['from_name'] ?? $payload['from_email'];
        }

        return $payload;
    }
}
