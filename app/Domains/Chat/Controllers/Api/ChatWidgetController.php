<?php

namespace App\Domains\Chat\Controllers\Api;

use App\Domains\Chat\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChatWidgetController extends Controller
{
    public function __construct(
        private ChatService $chat,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        try {
            $channel = $this->chat->resolveChannel($this->widgetKey($request));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($this->chat->config($channel));
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'session_uuid' => ['nullable', 'uuid'],
        ]);

        try {
            $channel = $this->chat->resolveChannel($this->widgetKey($request));
            $result = $this->chat->startSession($channel, $data, $request->userAgent());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $status = ($result['mode'] ?? '') === 'offline' ? 201 : 200;

        return response()->json($result, $status);
    }

    public function send(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $chatSession = $this->chat->authenticateSession($session, $this->sessionToken($request));
            $result = $this->chat->sendMessage($chatSession, $data['body']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($result);
    }

    public function poll(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'string'],
            'pulse' => ['nullable', 'integer'],
        ]);

        try {
            $chatSession = $this->chat->authenticateSession($session, $this->sessionToken($request));
            $result = $this->chat->poll(
                $chatSession,
                $data['since'] ?? null,
                isset($data['pulse']) ? (int) $data['pulse'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($result);
    }

    private function widgetKey(Request $request): string
    {
        return (string) ($request->header('X-Widget-Key') ?: $request->query('key', ''));
    }

    private function sessionToken(Request $request): string
    {
        return (string) ($request->header('X-Session-Token') ?: $request->query('token', ''));
    }
}
