<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingLeadService;
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
        private MarketingLeadService $leads,
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

        try {
            $this->leads->captureFromRegistration($registration, request());
        } catch (\Throwable $exception) {
            report($exception);
        }

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

        try {
            $this->leads->markRegistrationConverted($registration->admin_email);
        } catch (\Throwable $exception) {
            report($exception);
        }

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
        if ($this->isSlugTaken($slug)) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }
    }

    public function registerRules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resendRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function slugCheckRules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    public function verificationTokenRules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function slugAvailability(string $rawSlug): array
    {
        $slug = Str::slug($rawSlug);

        if ($slug === '' || strlen($slug) > 63 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return [
                'slug' => $slug,
                'available' => false,
                'status' => 'invalid',
            ];
        }

        $available = ! $this->isSlugTaken($slug);

        return [
            'slug' => $slug,
            'available' => $available,
            'status' => $available ? 'available' : 'taken',
        ];
    }

    private function isSlugTaken(string $slug): bool
    {
        return $slug === ''
            || Tenant::query()->where('slug', $slug)->exists()
            || Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $this->provisioning->tenantDomain($slug)))->exists()
            || $this->registrations->slugReserved($slug);
    }
}
