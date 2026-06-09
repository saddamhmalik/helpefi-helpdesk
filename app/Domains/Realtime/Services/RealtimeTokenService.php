<?php

namespace App\Domains\Realtime\Services;

use App\Models\User;
use InvalidArgumentException;

class RealtimeTokenService
{
    public function forChannel(string $channel, ?int $userId = null): string
    {
        $this->assertChannel($channel);

        return $this->sign([
            'channel' => $channel,
            'user_id' => $userId,
            'exp' => time() + config('realtime.token_ttl'),
        ]);
    }

    public function agentToken(User $user): string
    {
        return $this->sign([
            'scope' => 'agent',
            'user_id' => $user->id,
            'exp' => time() + config('realtime.token_ttl'),
        ]);
    }

    public function verify(string $token, string $channel): bool
    {
        $payload = $this->decode($token);

        if (! $payload || ($payload['exp'] ?? 0) < time()) {
            return false;
        }

        if (($payload['scope'] ?? null) === 'agent') {
            return true;
        }

        return ($payload['channel'] ?? null) === $channel;
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, $this->secret());

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    private function sign(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $encoded.'.'.hash_hmac('sha256', $encoded, $this->secret());
    }

    private function secret(): string
    {
        $key = config('app.key');

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7), true) ?: $key;
        }

        return $key;
    }

    private function assertChannel(string $channel): void
    {
        if (! preg_match('/^(ticket\.\d+|chat\.[0-9a-f-]{36}|workspace)$/', $channel)) {
            throw new InvalidArgumentException('Invalid realtime channel.');
        }
    }
}
