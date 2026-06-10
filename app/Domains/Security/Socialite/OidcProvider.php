<?php

namespace App\Domains\Security\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class OidcProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email'];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(rtrim($this->issuer(), '/').'/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return rtrim($this->issuer(), '/').'/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(rtrim($this->issuer(), '/').'/userinfo', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? $user['id'] ?? null,
            'nickname' => $user['preferred_username'] ?? null,
            'name' => $user['name'] ?? $user['given_name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }

    private function issuer(): string
    {
        return (string) ($this->config['issuer'] ?? '');
    }
}
