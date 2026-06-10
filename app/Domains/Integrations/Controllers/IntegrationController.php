<?php

namespace App\Domains\Integrations\Controllers;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Services\IntegrationConnectionService;
use App\Domains\Integrations\Services\WebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private WebhookService $webhookService,
        private IntegrationConnectionService $connections,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Integrations', [
            'webhooks' => $this->webhookService->all(),
            'meta' => array_merge($this->webhookService->meta(), $this->connections->meta()),
            'connections' => $this->connections->snapshot(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $webhook = $this->webhookService->create($this->validatedWebhook($request));

        return back()->with([
            'success' => 'Webhook created.',
            'webhook_secret' => $webhook->makeVisible('secret')->secret,
        ]);
    }

    public function update(Request $request, int $webhook): RedirectResponse
    {
        $this->webhookService->update($webhook, $this->validatedWebhook($request));

        return back()->with('success', 'Webhook updated.');
    }

    public function destroy(int $webhook): RedirectResponse
    {
        $this->webhookService->delete($webhook);

        return back()->with('success', 'Webhook deleted.');
    }

    public function test(int $webhook): RedirectResponse
    {
        $successful = $this->webhookService->sendTest($webhook);

        return back()->with(
            $successful ? 'success' : 'error',
            $successful ? 'Test webhook delivered.' : 'Test webhook delivery failed.',
        );
    }

    public function regenerateSecret(int $webhook): RedirectResponse
    {
        $webhook = $this->webhookService->regenerateSecret($webhook);

        return back()->with([
            'success' => 'Signing secret regenerated.',
            'webhook_secret' => $webhook->makeVisible('secret')->secret,
        ]);
    }

    public function updateSlack(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'channel' => ['nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['boolean'],
        ]);

        $this->connections->updateSlack($data);

        return back()->with('success', 'Slack integration saved.');
    }

    public function updateJira(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_url' => ['nullable', 'url', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
            'project_key' => ['nullable', 'string', 'max:50'],
            'issue_type' => ['nullable', 'string', 'max:50'],
            'done_transition' => ['nullable', 'string', 'max:50'],
            'reopen_transition' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $connection = $this->connections->updateJira($data);

        return back()->with([
            'success' => 'Jira integration saved.',
            'integration_secret' => $connection->config['webhook_secret'] ?? null,
        ]);
    }

    public function updateLinear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'string', 'max:255'],
            'done_state' => ['nullable', 'string', 'max:50'],
            'open_state' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $connection = $this->connections->updateLinear($data);

        return back()->with([
            'success' => 'Linear integration saved.',
            'integration_secret' => $connection->config['webhook_secret'] ?? null,
        ]);
    }

    public function testSlack(): RedirectResponse
    {
        $successful = $this->connections->testSlack();

        return back()->with(
            $successful ? 'success' : 'error',
            $successful ? 'Slack test message sent.' : 'Slack test message failed.',
        );
    }

    public function updateShopify(Request $request): RedirectResponse
    {
        $this->connections->updateShopify($request->validate([
            'shop' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Shopify integration saved.');
    }

    public function updateHubspot(Request $request): RedirectResponse
    {
        $this->connections->updateHubspot($request->validate([
            'access_token' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'HubSpot integration saved.');
    }

    public function updateSalesforce(Request $request): RedirectResponse
    {
        $this->connections->updateSalesforce($request->validate([
            'consumer_key' => ['nullable', 'string', 'max:255'],
            'consumer_secret' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'security_token' => ['nullable', 'string', 'max:255'],
            'login_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Salesforce integration saved.');
    }

    public function updateTeams(Request $request): RedirectResponse
    {
        $this->connections->updateTeams($request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'Microsoft Teams integration saved.');
    }

    public function updateZapier(Request $request): RedirectResponse
    {
        $connection = $this->connections->updateZapier($request->validate([
            'is_active' => ['boolean'],
        ]));

        return back()->with([
            'success' => 'Zapier integration saved.',
            'integration_secret' => $connection->config['subscribe_secret'] ?? null,
        ]);
    }

    public function testShopify(): RedirectResponse
    {
        $successful = $this->connections->testShopify();

        return back()->with($successful ? 'success' : 'error', $successful ? 'Shopify connection verified.' : 'Shopify connection failed.');
    }

    public function testHubspot(): RedirectResponse
    {
        $successful = $this->connections->testHubspot();

        return back()->with($successful ? 'success' : 'error', $successful ? 'HubSpot connection verified.' : 'HubSpot connection failed.');
    }

    public function testSalesforce(): RedirectResponse
    {
        $successful = $this->connections->testSalesforce();

        return back()->with($successful ? 'success' : 'error', $successful ? 'Salesforce connection verified.' : 'Salesforce connection failed.');
    }

    public function testTeams(): RedirectResponse
    {
        $successful = $this->connections->testTeams();

        return back()->with($successful ? 'success' : 'error', $successful ? 'Teams notification sent.' : 'Teams notification failed.');
    }

    private function validatedWebhook(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
