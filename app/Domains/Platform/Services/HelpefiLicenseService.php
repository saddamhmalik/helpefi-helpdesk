<?php

namespace App\Domains\Platform\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class HelpefiLicenseService
{
    public function isRequired(): bool
    {
        return config('deployment.mode') === 'self_hosted';
    }

    public function validate(): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        return $this->resolveValidationError() === null;
    }

    public function assertValid(): void
    {
        $error = $this->resolveValidationError();

        if ($error !== null) {
            throw new RuntimeException($error);
        }
    }

    public function resolveValidationError(): ?string
    {
        if (! $this->isRequired()) {
            return null;
        }

        $token = trim((string) config('deployment.license_key'));

        if ($token === '') {
            return 'HELPEFI_LICENSE_KEY is required for self-hosted deployments.';
        }

        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return 'HELPEFI_LICENSE_KEY is invalid.';
        }

        [$payload, $signature] = $parts;

        $expected = $this->sign($payload);

        if (! hash_equals($expected, $signature)) {
            return 'HELPEFI_LICENSE_KEY signature is invalid.';
        }

        $decoded = json_decode($this->decodePayload($payload), true);

        if (! is_array($decoded)) {
            return 'HELPEFI_LICENSE_KEY payload is invalid.';
        }

        $expiresAt = $this->parseExpiry($decoded['expires_at'] ?? null);

        if ($expiresAt === null) {
            return 'HELPEFI_LICENSE_KEY is missing a valid expires_at value.';
        }

        $graceEndsAt = $expiresAt->copy()->addHours(max(0, (int) config('deployment.license_grace_hours', 72)));

        if (now()->greaterThan($graceEndsAt)) {
            return 'HELPEFI_LICENSE_KEY has expired.';
        }

        $edition = (string) ($decoded['edition'] ?? '');

        if ($edition !== '' && $edition !== 'self_hosted') {
            return 'HELPEFI_LICENSE_KEY edition is not valid for this deployment.';
        }

        return null;
    }

    public function generate(string $organization, CarbonInterface $expiresAt, string $edition = 'self_hosted'): string
    {
        $payload = $this->encodePayload([
            'organization' => $organization,
            'expires_at' => $expiresAt->toDateString(),
            'edition' => $edition,
            'issued_at' => now()->toIso8601String(),
        ]);

        return $payload.'.'.$this->sign($payload);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            throw new RuntimeException('HELPEFI_LICENSE_KEY is invalid.');
        }

        $decoded = json_decode($this->decodePayload($parts[0]), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('HELPEFI_LICENSE_KEY payload is invalid.');
        }

        return $decoded;
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            $payload,
            (string) config('deployment.license_hmac_key'),
            true,
        )), '+/', '-_'), '=');
    }

    private function encodePayload(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decodePayload(string $payload): string
    {
        $normalized = strtr($payload, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw new RuntimeException('HELPEFI_LICENSE_KEY payload is invalid.');
        }

        return $decoded;
    }

    private function parseExpiry(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
