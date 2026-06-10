<?php

namespace App\Domains\Knowledge\Controllers\Api;

use App\Domains\Knowledge\Services\KnowledgeSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeSettingController extends Controller
{
    public function __construct(private KnowledgeSettingService $settings)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->settings->snapshot());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kb_locales' => ['required', 'array', 'min:1'],
            'kb_locales.*' => ['required', 'string', 'max:10'],
            'kb_default_locale' => ['required', 'string', 'max:10'],
        ]);

        return response()->json($this->settings->update($data));
    }
}
