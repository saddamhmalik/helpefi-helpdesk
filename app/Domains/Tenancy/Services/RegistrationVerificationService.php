<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\PlatformMailService;
use App\Domains\Tenancy\Exceptions\InvalidRegistrationTokenException;
use App\Domains\Tenancy\Models\PendingRegistration;
use App\Domains\Tenancy\Repositories\PendingRegistrationRepository;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationVerificationService
{
    private const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private PendingRegistrationRepository $registrations,
        private TenantProvisioningService $provisioning,
        private PlatformMailService $mail,
    ) {}

    public function register(array $data): PendingRegistration
    {
        $slug = Str::slug($data['slug']);

        $this->registrations->deleteUnverifiedForEmail($data['email']);
        $this->assertSlugAvailable($slug);

        $registration = $this->registrations->create([
            'organization_name' => $data['organization_name'],
            'slug' => $slug,
            'admin_name' => $data['name'],
            'admin_email' => $data['email'],
            'password' => $data['password'],
            'token' => $this->generateToken(),
            'expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
        ]);

        $this->sendVerification($registration);

        return $registration;
    }

    public function resend(string $email): ?PendingRegistration
    {
        $registration = $this->registrations->latestPendingForEmail($email);

        if (! $registration) {
            return null;
        }

        $registration->token = $this->generateToken();
        $registration->expires_at = now()->addHours(self::TOKEN_TTL_HOURS);
        $this->registrations->save($registration);

        $this->sendVerification($registration);

        return $registration;
    }

    public function verify(string $token): Tenant
    {
        $registration = $this->registrations->findByToken($token);

        if (! $registration || $registration->isVerified() || $registration->isExpired()) {
            throw InvalidRegistrationTokenException::expiredOrInvalid();
        }

        try {
            $tenant = $this->provisioning->provision(
                organizationName: $registration->organization_name,
                slug: $registration->slug,
                adminName: $registration->admin_name,
                adminEmail: $registration->admin_email,
                adminPassword: $registration->password,
            );
        } catch (ValidationException) {
            throw InvalidRegistrationTokenException::slugTaken();
        }

        $registration->verified_at = now();
        $this->registrations->save($registration);
        $this->registrations->delete($registration);

        return $tenant;
    }

    private function sendVerification(PendingRegistration $registration): void
    {
        $this->mail->sendRegistrationVerification(
            $registration->organization_name,
            $registration->admin_name,
            $registration->admin_email,
            $this->verificationUrl($registration->token),
        );
    }

    private function verificationUrl(string $token): string
    {
        return route('central.register.verify', ['token' => $token]);
    }

    private function generateToken(): string
    {
        return Str::random(64);
    }

    private function assertSlugAvailable(string $slug): void
    {
        $taken = $slug === ''
            || Tenant::query()->where('slug', $slug)->exists()
            || Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $this->provisioning->tenantDomain($slug)))->exists()
            || $this->registrations->slugReserved($slug);

        if ($taken) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }
    }
}
