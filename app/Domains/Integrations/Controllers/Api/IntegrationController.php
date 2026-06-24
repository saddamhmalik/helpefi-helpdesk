<?php

namespace App\Domains\Integrations\Controllers\Api;

use App\Domains\Integrations\Services\WebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(private WebhookService $webhookService)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json($this->webhookService->meta());
    }

    public function index(): JsonResponse
    {
        return response()->json($this->webhookService->all());
    }

    public function store(Request $request): JsonResponse
    {
        $webhook = $this->webhookService->create($this->validatedWebhook($request));
        $plainSecret = $webhook->secret;

        return response()->json([
            ...$webhook->toArray(),
            'secret' => $plainSecret,
        ], 201);
    }

    public function update(Request $request, int $webhook): JsonResponse
    {
        return response()->json(
            $this->webhookService->update($webhook, $this->validatedWebhook($request))
        );
    }

    public function destroy(int $webhook): JsonResponse
    {
        $this->webhookService->delete($webhook);

        return response()->json(['message' => 'Webhook deleted.']);
    }

    public function test(int $webhook): JsonResponse
    {
        $successful = $this->webhookService->sendTest($webhook);

        return response()->json([
            'successful' => $successful,
        ], $successful ? 200 : 422);
    }

    public function regenerateSecret(int $webhook): JsonResponse
    {
        $webhook = $this->webhookService->regenerateSecret($webhook);
        $plainSecret = $webhook->secret;

        return response()->json([
            ...$webhook->toArray(),
            'secret' => $plainSecret,
        ]);
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
