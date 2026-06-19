<?php

namespace App\Domains\Channels\Support;

class EmailPlaceholders
{
    public static function render(string $content, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }

    public static function samplesFor(array $keys): array
    {
        $samples = self::samples();

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $samples[$key] ?? self::fallbackSample($key)])
            ->all();
    }

    private static function fallbackSample(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private static function samples(): array
    {
        return [
            'ticket_number' => 'HD-1042',
            'ticket_subject' => 'Unable to access VPN',
            'agent_name' => 'Jane Agent',
            'reply_body' => '<p>We reset your access. Please try signing in again.</p>',
            'original_message_body' => 'I cannot log in since this morning.',
            'app_name' => config('app.name', 'helpefi'),
            'inviter_name' => 'Admin User',
            'role' => 'Agent',
            'accept_url' => url('/invite/accept'),
            'expires_at' => now()->addDays(7)->format('M j, Y'),
            'survey_url' => url('/survey/sample'),
            'rating_links' => '<a href="#">★</a> <a href="#">★</a> <a href="#">★</a> <a href="#">★</a> <a href="#">★</a>',
            'recipient_name' => 'Jane Agent',
            'report_name' => 'Weekly ticket summary',
            'report_type' => 'Ticket volume',
            'message_preview' => 'Thanks for the update. When will this be resolved?',
            'action_url' => url('/tickets/1'),
            'breach_label' => 'First response SLA breached',
            'request_subject' => 'New laptop request',
            'decision' => 'approved',
        ];
    }
}
