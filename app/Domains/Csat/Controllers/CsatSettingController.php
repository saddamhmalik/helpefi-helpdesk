<?php

namespace App\Domains\Csat\Controllers;

use App\Domains\Csat\Services\CsatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CsatSettingController extends Controller
{
    public function __construct(private CsatService $csat)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Settings/Csat', [
            'settings' => $this->csat->settingsSnapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'comment_required' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
        ]);

        $this->csat->updateSettings($data);

        return back()->with('success', 'CSAT settings saved.');
    }
}
