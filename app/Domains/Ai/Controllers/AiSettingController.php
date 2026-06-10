<?php

namespace App\Domains\Ai\Controllers;

use App\Domains\Ai\Services\AiAssistService;
use App\Domains\Ai\Services\AiDeflectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    public function __construct(
        private AiAssistService $aiAssistService,
        private AiDeflectionService $deflection,
    ) {
    }

    public function edit(): Response
    {
        return Inertia::render('Settings/Ai', [
            'settings' => array_merge(
                $this->aiAssistService->status(),
                $this->deflection->settingsSnapshot(),
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'model' => ['nullable', 'string', 'max:100'],
            'deflection_enabled' => ['required', 'boolean'],
            'deflection_portal_enabled' => ['required', 'boolean'],
            'deflection_widget_enabled' => ['required', 'boolean'],
            'triage_enabled' => ['required', 'boolean'],
        ]);

        $this->aiAssistService->updateSettings([
            'enabled' => $data['enabled'],
            'model' => $data['model'] ?? null,
            'triage_enabled' => $data['triage_enabled'],
        ]);

        $this->deflection->updateSettings([
            'deflection_enabled' => $data['deflection_enabled'],
            'deflection_portal_enabled' => $data['deflection_portal_enabled'],
            'deflection_widget_enabled' => $data['deflection_widget_enabled'],
        ]);

        return back()->with('success', 'AI settings saved.');
    }
}
