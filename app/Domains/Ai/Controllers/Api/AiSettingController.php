<?php

namespace App\Domains\Ai\Controllers\Api;

use App\Domains\Ai\Services\AiAssistService;
use App\Domains\Ai\Services\AiDeflectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingController extends Controller
{
    public function __construct(
        private AiAssistService $aiAssistService,
        private AiDeflectionService $deflection,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json(array_merge(
            $this->aiAssistService->status(),
            $this->deflection->settingsSnapshot(),
        ));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'model' => ['nullable', 'string', 'max:100'],
            'triage_enabled' => ['required', 'boolean'],
            'deflection_enabled' => ['required', 'boolean'],
            'deflection_portal_enabled' => ['required', 'boolean'],
            'deflection_widget_enabled' => ['required', 'boolean'],
        ]);

        $status = $this->aiAssistService->updateSettings([
            'enabled' => $data['enabled'],
            'model' => $data['model'] ?? null,
            'triage_enabled' => $data['triage_enabled'],
        ]);

        $deflection = $this->deflection->updateSettings([
            'deflection_enabled' => $data['deflection_enabled'],
            'deflection_portal_enabled' => $data['deflection_portal_enabled'],
            'deflection_widget_enabled' => $data['deflection_widget_enabled'],
        ]);

        return response()->json(array_merge($status, $deflection));
    }
}
