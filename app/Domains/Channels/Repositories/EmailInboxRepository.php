<?php

namespace App\Domains\Channels\Repositories;

use App\Domains\Channels\Models\EmailInbox;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class EmailInboxRepository
{
    public function all(): Collection
    {
        return EmailInbox::query()->orderBy('name')->get();
    }

    public function find(int $id): EmailInbox
    {
        return EmailInbox::query()->findOrFail($id);
    }

    public function findByToken(string $token): ?EmailInbox
    {
        return EmailInbox::query()
            ->where('inbound_token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function findByAddress(string $address): ?EmailInbox
    {
        $normalized = strtolower($address);

        $inbox = EmailInbox::query()
            ->where('address', $normalized)
            ->where('is_active', true)
            ->first();

        if ($inbox) {
            return $inbox;
        }

        return EmailInbox::query()
            ->where('is_active', true)
            ->whereNotNull('aliases')
            ->get()
            ->first(fn (EmailInbox $candidate) => in_array($normalized, array_map('strtolower', $candidate->aliases ?? []), true));
    }

    public function pollable(): Collection
    {
        return EmailInbox::query()
            ->where('is_active', true)
            ->whereIn('inbound_method', ['poll', 'oauth'])
            ->where(function ($query) {
                $query->where(function ($oauth) {
                    $oauth->where('inbound_method', 'oauth')
                        ->whereNotNull('oauth_provider')
                        ->whereNotNull('oauth_refresh_token');
                })->orWhere(function ($poll) {
                    $poll->where('inbound_method', 'poll')
                        ->whereNotNull('mailbox_protocol')
                        ->whereNotNull('mailbox_host');
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): EmailInbox
    {
        return EmailInbox::query()->create([
            'name' => $data['name'],
            'address' => strtolower($data['address']),
            'brand_id' => $data['brand_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'aliases' => $data['aliases'] ?? null,
            'inbound_token' => $data['inbound_token'] ?? $this->generateToken(),
            'is_active' => $data['is_active'] ?? true,
            'inbound_method' => $data['inbound_method'] ?? 'webhook',
        ]);
    }

    public function update(EmailInbox $inbox, array $data): EmailInbox
    {
        if (isset($data['address'])) {
            $data['address'] = strtolower($data['address']);
        }

        $inbox->update($data);

        return $inbox->fresh();
    }

    public function delete(EmailInbox $inbox): void
    {
        $inbox->delete();
    }

    public function generateToken(): string
    {
        return Str::random(48);
    }
}
