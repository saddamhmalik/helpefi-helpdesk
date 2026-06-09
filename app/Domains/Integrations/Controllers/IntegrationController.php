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
