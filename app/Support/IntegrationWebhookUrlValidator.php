<?php

namespace App\Support;

use InvalidArgumentException;

class IntegrationWebhookUrlValidator
{
    public static function assertSlackWebhookUrl(string $url): void
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        if (! in_array($host, ['hooks.slack.com', 'hooks.slack-services.com'], true)) {
            throw new InvalidArgumentException('Slack webhook URL must point to hooks.slack.com.');
        }

        SafeUrlValidator::assertPublicHttpUrl($url);
    }

    public static function assertTeamsWebhookUrl(string $url): void
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        if ($host !== 'outlook.office.com' && ! str_ends_with($host, '.webhook.office.com')) {
            throw new InvalidArgumentException('Teams webhook URL must point to outlook.office.com.');
        }

        SafeUrlValidator::assertPublicHttpUrl($url);
    }

    public static function assertJiraSiteUrl(string $url): void
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        if ($host === '' || ! str_contains($host, '.')) {
            throw new InvalidArgumentException('Jira site URL must include a valid host.');
        }

        if (! str_ends_with($host, '.atlassian.net') && ! str_ends_with($host, '.jira.com')) {
            SafeUrlValidator::assertPublicHttpUrl($url);
        }
    }
}
