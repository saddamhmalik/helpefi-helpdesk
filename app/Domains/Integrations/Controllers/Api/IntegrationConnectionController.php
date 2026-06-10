<?php

namespace App\Domains\Integrations\Controllers\Api;

use App\Domains\Integrations\Services\IntegrationConnectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IntegrationConnectionController extends Controller
{
    public function __construct(private IntegrationConnectionService $connections)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->connections->meta());
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->connections->snapshot());
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            $connection = $this->connections->updateProvider($provider, $this->validatedProvider($request, $provider));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['provider' => $exception->getMessage()]);
        }

        return response()->json([
            'provider' => $provider,
            'is_active' => $connection->is_active,
            'config' => $this->connections->maskedProviderConfig($provider, $connection->config ?? []),
        ]);
    }

    public function test(Request $request, string $provider): JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            $successful = $this->connections->testProvider($provider);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['provider' => $exception->getMessage()]);
        }

        return response()->json(['successful' => $successful], $successful ? 200 : 422);
    }

    private function validatedProvider(Request $request, string $provider): array
    {
        return match ($provider) {
            'slack' => $request->validate([
                'webhook_url' => ['nullable', 'url', 'max:2048'],
                'channel' => ['nullable', 'string', 'max:255'],
                'events' => ['nullable', 'array'],
                'events.*' => ['string'],
                'is_active' => ['boolean'],
            ]),
            'jira' => $request->validate([
                'site_url' => ['nullable', 'url', 'max:2048'],
                'email' => ['nullable', 'email', 'max:255'],
                'api_token' => ['nullable', 'string', 'max:255'],
                'project_key' => ['nullable', 'string', 'max:50'],
                'issue_type' => ['nullable', 'string', 'max:50'],
                'done_transition' => ['nullable', 'string', 'max:50'],
                'reopen_transition' => ['nullable', 'string', 'max:50'],
                'is_active' => ['boolean'],
            ]),
            'linear' => $request->validate([
                'api_key' => ['nullable', 'string', 'max:255'],
                'team_id' => ['nullable', 'string', 'max:255'],
                'done_state' => ['nullable', 'string', 'max:50'],
                'open_state' => ['nullable', 'string', 'max:50'],
                'is_active' => ['boolean'],
            ]),
            'shopify' => $request->validate([
                'shop' => ['nullable', 'string', 'max:255'],
                'access_token' => ['nullable', 'string', 'max:255'],
                'is_active' => ['boolean'],
            ]),
            'hubspot' => $request->validate([
                'access_token' => ['nullable', 'string', 'max:255'],
                'is_active' => ['boolean'],
            ]),
            'salesforce' => $request->validate([
                'consumer_key' => ['nullable', 'string', 'max:255'],
                'consumer_secret' => ['nullable', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:255'],
                'security_token' => ['nullable', 'string', 'max:255'],
                'login_url' => ['nullable', 'url', 'max:2048'],
                'is_active' => ['boolean'],
            ]),
            'microsoft_teams' => $request->validate([
                'webhook_url' => ['nullable', 'url', 'max:2048'],
                'is_active' => ['boolean'],
            ]),
            'zapier' => $request->validate([
                'is_active' => ['boolean'],
            ]),
            default => throw ValidationException::withMessages(['provider' => 'Unknown integration provider.']),
        };
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
