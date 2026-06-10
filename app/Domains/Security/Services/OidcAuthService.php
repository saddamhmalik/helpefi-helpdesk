<?php

namespace App\Domains\Security\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Security\Repositories\SecuritySettingRepository;
use App\Domains\Security\Socialite\OidcProvider;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class OidcAuthService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private BillingService $billing,
    ) {
    }

    public function redirectUrl(): string
    {
        $this->billing->assertFeature('sso');
        $config = $this->oidcConfig();

        return $this->driver($config)->redirect()->getTargetUrl();
    }

    public function handleCallback(): array
    {
        $this->billing->assertFeature('sso');
        $config = $this->oidcConfig();
        $socialUser = $this->driver($config)->user();

        return [
            'subject' => (string) $socialUser->getId(),
            'email' => strtolower((string) $socialUser->getEmail()),
            'name' => (string) ($socialUser->getName() ?: $socialUser->getEmail()),
            'provider' => 'oidc:'.$config['preset'],
        ];
    }

    private function oidcConfig(): array
    {
        $setting = $this->settings->current();
        $config = $setting->sso_config ?? [];

        if (! ($setting->sso_enabled && $setting->sso_protocol === 'oidc')) {
            throw new \InvalidArgumentException('OIDC SSO is not enabled.');
        }

        return $config;
    }

    private function driver(array $config): AbstractProvider
    {
        $preset = $config['preset'] ?? 'oidc';
        $redirect = route('sso.callback');
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';

        if ($preset === 'azure') {
            Config::set('services.azure', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect' => $redirect,
                'tenant' => $config['tenant_id'] ?? 'common',
            ]);

            return Socialite::driver('azure')->scopes(['openid', 'profile', 'email']);
        }

        if ($preset === 'google') {
            return Socialite::buildProvider(
                \Laravel\Socialite\Two\GoogleProvider::class,
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect' => $redirect,
                ],
            )->scopes(['openid', 'profile', 'email']);
        }

        return Socialite::buildProvider(OidcProvider::class, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect' => $redirect,
            'issuer' => $config['issuer'] ?? '',
        ]);
    }
}
