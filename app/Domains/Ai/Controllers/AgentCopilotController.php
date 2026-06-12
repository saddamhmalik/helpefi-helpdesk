<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Services\AgentCopilotService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCopilotController extends Controller
{
    public function __construct(private AgentCopilotService $copilot)
    {
    }

    public function index(Request $request, int $ticket): JsonResponse
    {
        return response()->json(
            $this->copilot->history($ticket, $request->user()->id)
        );
    }

    public function store(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        return response()->json(
            $this->copilot->chat($ticket, $request->user()->id, $data['message'])
        );
    }

    public function destroy(Request $request, int $ticket): JsonResponse
    {
        $this->copilot->clear($ticket, $request->user()->id);

        return response()->json(['cleared' => true]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        return response()->json(
            $this->copilot->askWorkspace($request->user()->id, $data['message'])
        );
    }
}
