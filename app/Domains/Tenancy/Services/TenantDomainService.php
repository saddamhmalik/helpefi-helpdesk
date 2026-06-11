<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Tenancy\Repositories\TenantDomainRepository;
use App\Domains\Tenancy\Support\DomainDnsVerifier;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantDomainService
{
    public function __construct(
        private TenantDomainRepository $domains,
        private DomainDnsVerifier $dns,
        private BillingService $billing,
    ) {
    }

    public function primaryHost(?Tenant $tenant = null): ?string
    {
        $tenant ??= tenant();

        if (! $tenant) {
            return null;
        }

        return $this->domains->primaryDomain($tenant)?->domain;
    }

    public function primaryUrl(?Tenant $tenant = null): ?string
    {
        $host = $this->primaryHost($tenant);

        return $host ? $this->urlForHost($host) : null;
    }

    public function platformHost(?Tenant $tenant = null): ?string
    {
        $tenant ??= tenant();

        return $tenant ? $this->domains->platformDomain($tenant)?->domain : null;
    }

    public function snapshot(?Tenant $tenant = null): array
    {
        $tenant ??= tenant();
        assert($tenant instanceof Tenant);

        $platform = $this->domains->platformDomain($tenant);
        $custom = $this->domains->customDomain($tenant);
        $primary = $this->domains->primaryDomain($tenant);

        return [
            'can_manage' => $this->billing->canUseFeature('custom_domain'),
            'platform_domain' => $platform?->domain,
            'platform_url' => $platform ? $this->urlForHost($platform->domain) : null,
            'custom_domain' => $this->presentCustom($custom),
            'primary_domain' => $primary?->domain,
            'primary_url' => $primary ? $this->urlForHost($primary->domain) : null,
            'redirect_platform_domain' => (bool) $tenant->custom_domain_redirect,
            'instructions' => $this->instructions($custom),
        ];
    }

    public function requestCustomDomain(string $host): array
    {
        $this->billing->assertFeature('custom_domain');

        $tenant = tenant();
        assert($tenant instanceof Tenant);

        $host = $this->normalizeHost($host);
        $this->assertHostAvailable($host, $tenant);

        if ($this->domains->customDomain($tenant)) {
            throw ValidationException::withMessages([
                'domain' => 'Remove the existing custom domain before adding a new one.',
            ]);
        }

        $token = 'helpdesk-verify='.Str::lower(Str::random(32));

        $custom = $this->domains->createCustom($tenant, $host, $token);

        return $this->snapshot($tenant->fresh());
    }

    public function verifyCustomDomain(): array
    {
        $this->billing->assertFeature('custom_domain');

        $tenant = tenant();
        assert($tenant instanceof Tenant);

        $custom = $this->domains->customDomain($tenant);

        if (! $custom) {
            throw ValidationException::withMessages([
                'domain' => 'Add a custom domain before verifying.',
            ]);
        }

        if ($this->verifyDns($custom)) {
            $custom = $this->domains->markVerified($custom);
            $this->domains->setPrimary($tenant, $custom);
        } else {
            $this->domains->markFailed($custom);
            throw ValidationException::withMessages([
                'domain' => $this->verificationFailureMessage($custom),
            ]);
        }

        return $this->snapshot($tenant->fresh());
    }

    public function removeCustomDomain(): array
    {
        $this->billing->assertFeature('custom_domain');

        $tenant = tenant();
        assert($tenant instanceof Tenant);

        $this->domains->deleteCustom($tenant);
        $tenant->update(['custom_domain_redirect' => false]);

        return $this->snapshot($tenant->fresh());
    }

    public function updatePreferences(bool $redirectPlatformDomain): array
    {
        $this->billing->assertFeature('custom_domain');

        $tenant = tenant();
        assert($tenant instanceof Tenant);

        $custom = $this->domains->customDomain($tenant);

        if ($redirectPlatformDomain && (! $custom || ! $custom->isVerified())) {
            throw ValidationException::withMessages([
                'redirect_platform_domain' => 'Verify a custom domain before enabling redirects.',
            ]);
        }

        $tenant->update(['custom_domain_redirect' => $redirectPlatformDomain]);

        return $this->snapshot($tenant->fresh());
    }

    public function shouldRedirectToPrimary(Tenant $tenant, string $requestHost): bool
    {
        if (! $tenant->custom_domain_redirect) {
            return false;
        }

        $platform = $this->domains->platformDomain($tenant);
        $primary = $this->domains->primaryDomain($tenant);

        if (! $platform || ! $primary || $platform->domain === $primary->domain) {
            return false;
        }

        if (strtolower($requestHost) !== strtolower($platform->domain)) {
            return false;
        }

        return $primary->isVerified();
    }

    public function redirectUrl(Tenant $tenant, string $requestUri = '/'): ?string
    {
        $primary = $this->primaryHost($tenant);

        if (! $primary) {
            return null;
        }

        if (! app()->runningInConsole() && request()->getHost()) {
            $scheme = request()->getScheme();
            $portSuffix = $this->nonDefaultPortSuffix($scheme, request()->getPort());

            return "{$scheme}://{$primary}{$portSuffix}{$requestUri}";
        }

        return $this->urlForHost($primary).$requestUri;
    }

    private function presentCustom(?TenantDomain $custom): ?array
    {
        if (! $custom) {
            return null;
        }

        return [
            'id' => $custom->id,
            'domain' => $custom->domain,
            'url' => $this->urlForHost($custom->domain),
            'status' => $custom->verification_status,
            'is_verified' => $custom->isVerified(),
            'verified_at' => $custom->verified_at?->toIso8601String(),
            'verification_token' => $custom->verification_token,
            'verification_host' => $this->verificationHost($custom->domain),
        ];
    }

    private function instructions(?TenantDomain $custom): array
    {
        $cnameTarget = (string) config('tenancy.custom_domain.cname_target');
        $prefix = (string) config('tenancy.custom_domain.verification_prefix', '_helpdesk-verify');
        $customHost = $custom?->domain;
        $txtFqdn = $customHost ? $this->verificationHost($customHost) : "{$prefix}.support.example.com";

        return [
            'cname_target' => $cnameTarget,
            'verification_prefix' => $prefix,
            'txt_host' => $txtFqdn,
            'txt_value' => $custom?->verification_token,
            'dns_zone' => $customHost ? $this->dnsZoneName($customHost) : 'example.com',
            'txt_dns_name' => $customHost ? $this->dnsTxtRecordName($customHost, $txtFqdn) : $txtFqdn,
            'cname_dns_name' => $customHost ? $this->dnsCnameRecordName($customHost) : 'support',
            'platform_operator_notes' => [
                'Accept custom hostnames on your load balancer or web server.',
                'Issue TLS certificates for verified customer domains.',
                'Keep SESSION_DOMAIN unset so cookies stay scoped to each hostname.',
            ],
        ];
    }

    private function verificationFailureMessage(TenantDomain $custom): string
    {
        $fqdn = $this->verificationHost($custom->domain);
        $zone = $this->dnsZoneName($custom->domain);
        $dnsName = $this->dnsTxtRecordName($custom->domain, $fqdn);
        $found = $this->dns->txtRecordsFor($fqdn);

        $foundSummary = $found === []
            ? 'no TXT record was found at that hostname yet'
            : 'found TXT value(s): '.implode('; ', $found);

        return "DNS verification failed. In your {$zone} DNS zone, add a TXT record with Name \"{$dnsName}\" (not @) and Value \"{$custom->verification_token}\". We looked up {$fqdn} and {$foundSummary}. DNS changes can take up to an hour to propagate.";
    }

    private function dnsZoneName(string $customDomain): string
    {
        $parts = explode('.', $customDomain);

        if (count($parts) < 2) {
            return $customDomain;
        }

        return implode('.', array_slice($parts, 1));
    }

    private function dnsTxtRecordName(string $customDomain, string $verificationFqdn): string
    {
        $zone = $this->dnsZoneName($customDomain);
        $suffix = '.'.$zone;

        if (str_ends_with($verificationFqdn, $suffix)) {
            return substr($verificationFqdn, 0, -strlen($suffix));
        }

        return $verificationFqdn;
    }

    private function dnsCnameRecordName(string $customDomain): string
    {
        return explode('.', $customDomain)[0];
    }

    private function verificationHost(string $domain): string
    {
        $prefix = (string) config('tenancy.custom_domain.verification_prefix', '_helpdesk-verify');

        return "{$prefix}.{$domain}";
    }

    private function verifyDns(TenantDomain $custom): bool
    {
        if (! $custom->verification_token) {
            return false;
        }

        return $this->dns->hasTxtRecord(
            $this->verificationHost($custom->domain),
            $custom->verification_token,
        );
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = rtrim($host, '/');
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            throw ValidationException::withMessages([
                'domain' => 'Enter a valid domain such as support.anytrip.com.',
            ]);
        }

        $central = strtolower((string) config('tenancy.central_app_domain'));

        if ($host === $central || str_ends_with($host, '.'.$central)) {
            throw ValidationException::withMessages([
                'domain' => 'Use your own domain instead of the platform domain.',
            ]);
        }

        return $host;
    }

    private function assertHostAvailable(string $host, Tenant $tenant): void
    {
        $existing = $this->domains->findByHost($host);

        if ($existing && $existing->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => 'This domain is already connected to another workspace.',
            ]);
        }
    }

    private function urlForHost(string $host): string
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $portSuffix = $this->nonDefaultPortSuffix($scheme, $port ? (int) $port : null);

        return "{$scheme}://{$host}{$portSuffix}";
    }

    private function nonDefaultPortSuffix(string $scheme, ?int $port): string
    {
        if (! $port) {
            return '';
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $port === $defaultPort ? '' : ":{$port}";
    }
}
