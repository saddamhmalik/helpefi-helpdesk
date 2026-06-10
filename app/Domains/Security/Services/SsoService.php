<?php

namespace App\Domains\Security\Services;

use App\Domains\Auth\Services\AuthService;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Security\Exceptions\TwoFactorRequiredException;
use App\Domains\Security\Repositories\SecuritySettingRepository;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SsoService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private OidcAuthService $oidc,
        private SamlAuthService $saml,
        private BillingService $billing,
        private AuditLogService $audit,
    ) {
    }

    public function snapshot(): array
    {
        $setting = $this->settings->current();
        $config = $setting->sso_config ?? [];

        return [
            'sso_enabled' => $setting->sso_enabled,
            'sso_protocol' => $setting->sso_protocol,
            'sso_config' => $this->maskedConfig($config),
            'presets' => config('sso.oidc_presets', []),
            'metadata_url' => route('sso.metadata'),
            'acs_url' => route('sso.acs'),
            'callback_url' => route('sso.callback'),
            'login_url' => route('sso.redirect'),
            'feature_available' => $this->billing->canUseFeature('sso'),
        ];
    }

    public function loginOptions(): array
    {
        try {
            $setting = $this->settings->current();
        } catch (\Throwable) {
            return ['enabled' => false];
        }

        if (! $setting->sso_enabled) {
            return ['enabled' => false];
        }

        try {
            if (! $this->billing->canUseFeature('sso')) {
                return ['enabled' => false];
            }
        } catch (\Throwable) {
            return ['enabled' => false];
        }

        $config = $setting->sso_config ?? [];

        return [
            'enabled' => true,
            'protocol' => $setting->sso_protocol,
            'label' => $config['button_label'] ?? 'Sign in with SSO',
            'redirect_url' => route('sso.redirect'),
        ];
    }

    public function update(array $data): array
    {
        $this->billing->assertFeature('sso');

        $setting = $this->settings->current();
        $existing = $setting->sso_config ?? [];
        $protocol = $data['sso_protocol'] ?? $setting->sso_protocol;

        $config = match ($protocol) {
            'oidc' => $this->mergeOidcConfig($existing, $data['sso_config'] ?? []),
            'saml' => $this->mergeSamlConfig($existing, $data['sso_config'] ?? []),
            default => $existing,
        };

        $this->settings->update($setting, [
            'sso_enabled' => $data['sso_enabled'] ?? false,
            'sso_protocol' => $protocol,
            'sso_config' => $config,
        ]);

        $this->audit->record(
            'security.sso_updated',
            auth()->id(),
            auth()->user()?->email,
            properties: ['protocol' => $protocol, 'enabled' => $data['sso_enabled'] ?? false],
        );

        return $this->snapshot();
    }

    public function redirectUrl(): string
    {
        $setting = $this->settings->current();

        return match ($setting->sso_protocol) {
            'saml' => $this->saml->redirectUrl(),
            default => $this->oidc->redirectUrl(),
        };
    }

    public function completeLogin(array $identity): User
    {
        $this->assertAllowedDomain($identity['email']);

        $user = User::query()
            ->where('sso_provider', $identity['provider'])
            ->where('sso_subject', $identity['subject'])
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $identity['email'])->first();
        }

        if (! $user) {
            $user = $this->provisionUser($identity);
        } else {
            $user->update([
                'sso_provider' => $identity['provider'],
                'sso_subject' => $identity['subject'],
                'name' => $user->name ?: $identity['name'],
            ]);
        }

        if ($user->hasRole('customer')) {
            throw ValidationException::withMessages([
                'email' => 'SSO login is only available for agent accounts.',
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            app(TwoFactorService::class)->markPendingLogin($user, false);

            throw new TwoFactorRequiredException;
        }

        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('two_factor_verified', true);
        $this->audit->record('auth.sso_login', $user->id, $user->email, properties: ['provider' => $identity['provider']]);

        return $user;
    }

    private function provisionUser(array $identity): User
    {
        $setting = $this->settings->current();
        $config = $setting->sso_config ?? [];

        if (! ($config['auto_provision'] ?? true)) {
            throw ValidationException::withMessages([
                'email' => 'No account exists for this SSO identity. Contact an administrator.',
            ]);
        }

        $role = $config['default_role'] ?? config('sso.default_role', 'agent');

        $user = User::query()->create([
            'name' => $identity['name'],
            'email' => $identity['email'],
            'password' => Hash::make(Str::random(32)),
            'sso_provider' => $identity['provider'],
            'sso_subject' => $identity['subject'],
        ]);

        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }

    private function assertAllowedDomain(string $email): void
    {
        $config = $this->settings->current()->sso_config ?? [];
        $domains = $config['allowed_domains'] ?? [];

        if ($domains === []) {
            return;
        }

        $domain = Str::after($email, '@');

        if (! in_array($domain, $domains, true)) {
            throw ValidationException::withMessages([
                'email' => 'Your email domain is not allowed for SSO login.',
            ]);
        }
    }

    private function mergeOidcConfig(array $existing, array $incoming): array
    {
        $merged = array_merge($existing, array_filter($incoming, fn ($value) => $value !== null && $value !== ''));

        if (($incoming['client_secret'] ?? '') === '' && ! empty($existing['client_secret'])) {
            $merged['client_secret'] = $existing['client_secret'];
        }

        return $merged;
    }

    private function mergeSamlConfig(array $existing, array $incoming): array
    {
        return array_merge($existing, array_filter($incoming, fn ($value) => $value !== null && $value !== ''));
    }

    private function maskedConfig(array $config): array
    {
        return array_merge($config, [
            'has_client_secret' => ! empty($config['client_secret']),
            'client_secret' => '',
        ]);
    }
}
