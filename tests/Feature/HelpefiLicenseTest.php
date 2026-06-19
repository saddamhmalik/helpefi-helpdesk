<?php

namespace Tests\Feature;

use App\Domains\Platform\Services\HelpefiLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HelpefiLicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_mode_does_not_require_license(): void
    {
        config(['deployment.mode' => 'saas']);

        $this->assertTrue(app(HelpefiLicenseService::class)->validate());
        $this->assertNull(app(HelpefiLicenseService::class)->resolveValidationError());
    }

    public function test_self_hosted_requires_license_key(): void
    {
        config([
            'deployment.mode' => 'self_hosted',
            'deployment.license_key' => null,
        ]);

        $error = app(HelpefiLicenseService::class)->resolveValidationError();

        $this->assertNotNull($error);
        $this->assertStringContainsString('HELPEFI_LICENSE_KEY', $error);
    }

    public function test_generated_license_validates(): void
    {
        config([
            'deployment.mode' => 'self_hosted',
            'deployment.license_hmac_key' => 'test-license-key',
        ]);

        $service = app(HelpefiLicenseService::class);
        $token = $service->generate('Acme Corp', Carbon::parse('2027-12-31'));
        config(['deployment.license_key' => $token]);

        $this->assertTrue($service->validate());
        $this->assertSame('Acme Corp', $service->decode($token)['organization']);
    }

    public function test_expired_license_fails_after_grace_period(): void
    {
        config([
            'deployment.mode' => 'self_hosted',
            'deployment.license_hmac_key' => 'test-license-key',
            'deployment.license_grace_hours' => 0,
        ]);

        $service = app(HelpefiLicenseService::class);
        $token = $service->generate('Acme Corp', now()->subDay());
        config(['deployment.license_key' => $token]);

        $this->assertFalse($service->validate());
    }
}
