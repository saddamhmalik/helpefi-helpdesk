<?php

namespace App\Domains\Ai\Controllers\Api;

use App\Domains\Ai\Models\AiDeflectionEvent;
use App\Domains\Ai\Services\AiDeflectionService;
use App\Domains\Chat\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AiDeflectionController extends Controller
{
    public function __construct(
        private AiDeflectionService $deflection,
        private ChatService $chat,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'in:portal,widget'],
        ]);

        if ($data['channel'] === AiDeflectionEvent::CHANNEL_WIDGET) {
            try {
                $this->chat->resolveChannel($this->widgetKey($request));
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
        }

        return response()->json([
            'enabled' => $this->deflection->isEnabledForChannel($data['channel']),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'channel' => ['required', 'string', 'in:portal,widget'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        if ($data['channel'] === AiDeflectionEvent::CHANNEL_WIDGET) {
            try {
                $this->chat->resolveChannel($this->widgetKey($request));
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
        }

        try {
            return response()->json($this->deflection->ask(
                $data['query'],
                $data['channel'],
                $data['session_id'] ?? null,
            ));
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'in:portal,widget'],
            'helpful' => ['required', 'boolean'],
            'article_id' => ['nullable', 'integer', 'exists:knowledge_articles,id'],
        ]);

        if ($data['channel'] === AiDeflectionEvent::CHANNEL_WIDGET) {
            try {
                $this->chat->resolveChannel($this->widgetKey($request));
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
        }

        try {
            $this->deflection->feedback(
                $data['session_id'],
                $data['channel'],
                $data['helpful'],
                $data['article_id'] ?? null,
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json(['ok' => true]);
    }

    public function escalate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'in:portal,widget'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        if ($data['channel'] === AiDeflectionEvent::CHANNEL_WIDGET) {
            try {
                $this->chat->resolveChannel($this->widgetKey($request));
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }
        }

        try {
            return response()->json(
                $this->deflection->escalate($data, $data['channel']),
                201,
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }
    }

    public function metrics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin') || $request->user()?->hasRole('agent'), 403);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'channel' => ['nullable', 'string', 'in:portal,widget'],
        ]);

        return response()->json($this->deflection->summary($filters));
    }

    private function widgetKey(Request $request): string
    {
        return (string) ($request->header('X-Widget-Key') ?: $request->query('key', ''));
    }
}
