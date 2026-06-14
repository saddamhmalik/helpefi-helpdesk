<?php

namespace App\Domains\Channels\Services\OAuth;

use App\Domains\Channels\Contracts\MailOAuthProviderInterface;
use InvalidArgumentException;

class MailOAuthProviderFactory
{
    public function make(string $provider): MailOAuthProviderInterface
    {
        return match ($provider) {
            'google' => app(GoogleMailOAuthProvider::class),
            'microsoft' => app(MicrosoftMailOAuthProvider::class),
            'zoho' => app(ZohoMailOAuthProvider::class),
            default => throw new InvalidArgumentException('Unsupported OAuth provider.'),
        };
    }

    public function configuredProviders(): array
    {
        $oauth = app(MailOAuthService::class);
        $providers = [];

        foreach (['google', 'microsoft', 'zoho'] as $provider) {
            $instance = $this->make($provider);
            $providers[$provider] = [
                'key' => $provider,
                'label' => config("helpdesk.mail_oauth.{$provider}.label"),
                'help' => config("helpdesk.mail_oauth.{$provider}.help"),
                'configured' => $instance->isConfigured(),
                'redirect_uri' => $oauth->redirectUri($provider),
                'setup_console_url' => config("helpdesk.mail_oauth.{$provider}.setup_console_url"),
                'gmail_api_enable_url' => config("helpdesk.mail_oauth.{$provider}.gmail_api_enable_url"),
            ];
        }

        return $providers;
    }
}
