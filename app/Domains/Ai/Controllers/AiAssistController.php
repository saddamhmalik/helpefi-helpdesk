<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Services\AiAssistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistController extends Controller
{
    public function __construct(private AiAssistService $aiAssistService)
    {
    }

    public function suggestReply(Request $request, int $ticket): JsonResponse
    {
        return response()->json(
            $this->aiAssistService->suggestReply($ticket, $request->user()->id)
        );
    }

    public function summarize(int $ticket): JsonResponse
    {
        return response()->json(
            $this->aiAssistService->summarize($ticket)
        );
    }

    public function kbAssist(int $ticket): JsonResponse
    {
        return response()->json(
            $this->aiAssistService->kbAssist($ticket)
        );
    }
}
