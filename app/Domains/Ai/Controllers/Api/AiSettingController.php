<?php

namespace App\Domains\Ai\Controllers\Api;

use App\Domains\Ai\Services\AiAssistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingController extends Controller
{
    public function __construct(private AiAssistService $aiAssistService)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->aiAssistService->status());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->aiAssistService->updateSettings($data)
        );
    }
}
