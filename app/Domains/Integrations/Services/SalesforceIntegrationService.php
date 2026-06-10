<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use Illuminate\Support\Facades\Config;
use Omniphx\Forrest\Providers\Laravel\Facades\Forrest;

class SalesforceIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function lookupContactByEmail(string $email): ?array
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SALESFORCE);

        if (! $connection?->is_active) {
            return null;
        }

        $this->applyCredentials($connection->config ?? []);
        Forrest::authenticate();

        $result = Forrest::query(
            "SELECT Id, Name, Email, Phone, Title, Account.Name, Account.Type, Account.AnnualRevenue, Owner.Name "
            ."FROM Contact WHERE Email = '".addslashes($email)."' LIMIT 1"
        );

        $record = $result['records'][0] ?? null;

        if (! $record) {
            return null;
        }

        $instanceUrl = rtrim((string) (Forrest::getInstanceURL() ?? ''), '/');
        $dealValue = isset($record['Account']['AnnualRevenue'])
            ? (float) $record['Account']['AnnualRevenue']
            : null;

        return [
            'provider' => IntegrationConnection::PROVIDER_SALESFORCE,
            'id' => $record['Id'],
            'profile' => [
                'name' => $record['Name'] ?? $email,
                'email' => $record['Email'] ?? $email,
                'company' => $record['Account']['Name'] ?? null,
                'phone' => $record['Phone'] ?? null,
                'lifecycle_stage' => $record['Account']['Type'] ?? $record['Title'] ?? null,
                'deal_value' => $dealValue,
                'owner' => $record['Owner']['Name'] ?? null,
                'url' => $instanceUrl !== ''
                    ? $instanceUrl.'/lightning/r/Contact/'.$record['Id'].'/view'
                    : null,
            ],
        ];
    }

    public function testConnection(): bool
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SALESFORCE);

        if (! $connection?->is_active) {
            return false;
        }

        $this->applyCredentials($connection->config ?? []);
        Forrest::authenticate();
        Forrest::query('SELECT Id FROM Organization LIMIT 1');

        return true;
    }

    private function applyCredentials(array $config): void
    {
        Config::set('forrest.credentials', [
            'consumerKey' => $config['consumer_key'] ?? '',
            'consumerSecret' => $config['consumer_secret'] ?? '',
            'callbackUri' => route('settings.integrations'),
            'loginURL' => $config['login_url'] ?? 'https://login.salesforce.com',
            'username' => $config['username'] ?? '',
            'password' => ($config['password'] ?? '').($config['security_token'] ?? ''),
        ]);
        Config::set('forrest.authentication', 'UserPassword');
    }
}
