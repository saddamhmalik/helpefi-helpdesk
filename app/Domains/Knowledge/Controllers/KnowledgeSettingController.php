<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Knowledge\Services\KnowledgeSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeSettingController extends Controller
{
    public function __construct(private KnowledgeSettingService $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Knowledge/Settings', $this->settings->snapshot());
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kb_locales' => ['required', 'array', 'min:1'],
            'kb_locales.*' => ['required', 'string', 'max:10'],
            'kb_default_locale' => ['required', 'string', 'max:10'],
        ]);

        $this->settings->update($data);

        return back()->with('success', 'Knowledge base locales updated.');
    }
}
