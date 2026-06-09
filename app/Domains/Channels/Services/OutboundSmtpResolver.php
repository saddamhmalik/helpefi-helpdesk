<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use InvalidArgumentException;

class OutboundSmtpResolver
{
    public function inboxSmtpProviderKey(EmailInbox $inbox): string
    {
        if ($inbox->mailbox_provider) {
            return match ($inbox->mailbox_provider) {
                'gmail', 'gmail_pop3' => 'gmail',
                'outlook', 'outlook_pop3' => 'outlook',
                'yahoo' => 'yahoo',
                'icloud' => 'icloud',
                default => 'custom',
            };
        }

        if ($inbox->oauth_provider) {
            return config("helpdesk.oauth_smtp_provider_map.{$inbox->oauth_provider}", 'custom');
        }

        return 'custom';
    }

    public function normalizeHost(?string $host, ?string $fromAddress = null): ?string
    {
        if (! $host) {
            return $host;
        }

        $host = strtolower(trim($host));

        $aliases = [
            'gmail.com' => 'smtp.gmail.com',
            'googlemail.com' => 'smtp.gmail.com',
            'imap.gmail.com' => 'smtp.gmail.com',
            'outlook.com' => 'smtp.office365.com',
            'hotmail.com' => 'smtp.office365.com',
            'live.com' => 'smtp.office365.com',
            'yahoo.com' => 'smtp.mail.yahoo.com',
            'icloud.com' => 'smtp.mail.me.com',
        ];

        if (isset($aliases[$host])) {
            return $aliases[$host];
        }

        if ($fromAddress && str_ends_with(strtolower($fromAddress), '@gmail.com') && $host === 'gmail.com') {
            return 'smtp.gmail.com';
        }

        return $host;
    }

    public function normalizePassword(?string $password): ?string
    {
        if ($password === null || $password === '') {
            return $password;
        }

        return preg_replace('/\s+/', '', $password) ?: $password;
    }

    public function preset(string $providerKey): array
    {
        return config("helpdesk.smtp_providers.{$providerKey}", config('helpdesk.smtp_providers.custom', []));
    }

    public function inboxSnapshot(EmailInbox $inbox): array
    {
        $providerKey = $this->inboxSmtpProviderKey($inbox);
        $preset = $this->preset($providerKey);

        return [
            'inbox_id' => $inbox->id,
            'address' => $inbox->address,
            'name' => $inbox->name,
            'provider_key' => $providerKey,
            'provider_label' => $preset['label'] ?? 'Custom SMTP',
            'host' => $preset['host'] ?? null,
            'port' => $preset['port'] ?? 587,
            'encryption' => $preset['encryption'] ?? 'tls',
            'username' => $inbox->mailbox_username ?: $inbox->address,
            'has_inbound_password' => (bool) $inbox->mailbox_password,
            'can_use_same_credentials' => $this->canUseSameCredentials($inbox),
            'help' => $preset['help'] ?? null,
        ];
    }

    public function canUseSameCredentials(EmailInbox $inbox): bool
    {
        return $inbox->inbound_method === 'poll' && (bool) $inbox->mailbox_password;
    }

    public function resolve(MailSetting $setting): array
    {
        if ($setting->use_inbox_smtp) {
            return $this->resolveFromInbox($setting);
        }

        if ($setting->driver !== 'smtp') {
            return [
                'driver' => $setting->driver,
                'from_address' => $setting->from_address,
                'from_name' => $setting->from_name,
            ];
        }

        if (! $setting->host) {
            throw new InvalidArgumentException('SMTP host is required.');
        }

        if (! $setting->from_address) {
            throw new InvalidArgumentException('From address is required.');
        }

        if (! $setting->username) {
            throw new InvalidArgumentException('SMTP username is required.');
        }

        $password = $this->resolveCredentialPassword(
            $setting->from_address,
            $setting->username,
            $setting->password,
        );

        if (! $password) {
            throw new InvalidArgumentException('SMTP password is required.');
        }

        return [
            'driver' => 'smtp',
            'host' => $this->normalizeHost($setting->host, $setting->from_address),
            'port' => $setting->port ?? 587,
            'encryption' => $setting->encryption,
            'username' => $setting->username,
            'password' => $password,
            'from_address' => $setting->from_address,
            'from_name' => $setting->from_name,
        ];
    }

    public function resolveCredentialPassword(?string $address, ?string $username, ?string $configuredPassword): ?string
    {
        foreach (array_unique(array_filter([
            strtolower(trim($address ?? '')),
            strtolower(trim($username ?? '')),
        ])) as $email) {
            if (! str_contains($email, '@')) {
                continue;
            }

            $inbox = EmailInbox::query()
                ->where('address', $email)
                ->where('inbound_method', 'poll')
                ->first();

            if ($inbox?->mailbox_password) {
                return $this->normalizePassword($inbox->mailbox_password);
            }
        }

        return $this->normalizePassword($configuredPassword);
    }

    public function resolveFromInbox(MailSetting $setting): array
    {
        $inbox = $setting->emailInbox;

        if (! $inbox) {
            throw new InvalidArgumentException('Select an inbound inbox for outgoing email.');
        }

        $providerKey = $this->inboxSmtpProviderKey($inbox);
        $preset = $this->preset($providerKey);
        $password = $inbox->inbound_method === 'poll' && $inbox->mailbox_password
            ? $this->normalizePassword($inbox->mailbox_password)
            : $this->normalizePassword($setting->password ?: $inbox->mailbox_password);

        if (! $password) {
            throw new InvalidArgumentException(
                $inbox->inbound_method === 'oauth'
                    ? 'Enter an app password for SMTP sending. OAuth covers inbound only — Gmail/Outlook still need an app password to send.'
                    : 'Save an inbound mailbox password on this inbox first, or enter an SMTP password below.',
            );
        }

        $host = $preset['host'] ?? $setting->host;

        if (! $host) {
            throw new InvalidArgumentException('SMTP host is required for this inbox provider.');
        }

        return [
            'driver' => 'smtp',
            'host' => $host,
            'port' => $preset['port'] ?? $setting->port ?? 587,
            'encryption' => $preset['encryption'] ?? $setting->encryption ?? 'tls',
            'username' => $inbox->mailbox_username ?: $inbox->address,
            'password' => $password,
            'from_address' => $inbox->address,
            'from_name' => $inbox->name,
        ];
    }
}
