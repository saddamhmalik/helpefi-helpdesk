<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use Illuminate\Support\Facades\Http;

class TeamsIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function sendTest(): bool
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_MICROSOFT_TEAMS);
        $webhookUrl = $connection?->config['webhook_url'] ?? null;

        if (! $connection?->is_active || ! $webhookUrl) {
            return false;
        }

        $response = Http::post($webhookUrl, [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => 'helpefi test',
            'themeColor' => '0076D7',
            'title' => 'helpefi Teams integration',
            'text' => 'Test notification from your helpdesk workspace.',
        ]);

        return $response->successful();
    }
}
