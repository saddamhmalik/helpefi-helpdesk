<?php

namespace App\Domains\Security\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Security\Repositories\SecuritySettingRepository;
use Illuminate\Support\Facades\URL;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Error as SamlError;

class SamlAuthService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private FeatureEntitlementChecker $entitlements,
    ) {
    }

    public function redirectUrl(): string
    {
        $this->entitlements->assertFeature('sso');

        return $this->auth()->login(URL::route('sso.acs'), [], false, false, true);
    }

    public function handleAcs(array $payload): array
    {
        $this->entitlements->assertFeature('sso');
        $auth = $this->auth();

        $auth->processResponse();

        if ($auth->getErrors()) {
            throw new SamlError('SAML authentication failed: '.implode(', ', $auth->getErrors()));
        }

        if (! $auth->isAuthenticated()) {
            throw new SamlError('SAML authentication was not successful.');
        }

        $attributes = $auth->getAttributes();
        $email = $this->attribute($attributes, ['email', 'mail', 'Email', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'])
            ?? $auth->getNameId();
        $name = $this->attribute($attributes, ['name', 'displayName', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'])
            ?? $email;

        return [
            'subject' => (string) $auth->getNameId(),
            'email' => strtolower((string) $email),
            'name' => (string) $name,
            'provider' => 'saml',
        ];
    }

    public function metadata(): string
    {
        $this->entitlements->assertFeature('sso');
        $settings = $this->auth()->getSettings();
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if (! empty($errors)) {
            throw new SamlError('Invalid SP metadata: '.implode(', ', $errors));
        }

        return $metadata;
    }

    private function auth(): SamlAuth
    {
        $setting = $this->settings->current();
        $config = $setting->sso_config ?? [];

        if (! ($setting->sso_enabled && $setting->sso_protocol === 'saml')) {
            throw new \InvalidArgumentException('SAML SSO is not enabled.');
        }

        return new SamlAuth($this->buildSettings($config));
    }

    private function buildSettings(array $config): array
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return [
            'strict' => true,
            'debug' => config('app.debug'),
            'sp' => [
                'entityId' => $config['sp_entity_id'] ?? $baseUrl.'/sso/metadata',
                'assertionConsumerService' => [
                    'url' => route('sso.acs'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'singleLogoutService' => [
                    'url' => route('sso.slo'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ],
            'idp' => [
                'entityId' => $config['idp_entity_id'] ?? '',
                'singleSignOnService' => [
                    'url' => $config['sso_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'singleLogoutService' => [
                    'url' => $config['slo_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => $config['x509_cert'] ?? '',
            ],
            'security' => [
                'wantAssertionsSigned' => true,
                'wantMessagesSigned' => false,
            ],
        ];
    }

    private function attribute(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($attributes[$key][0])) {
                return (string) $attributes[$key][0];
            }
        }

        return null;
    }
}
