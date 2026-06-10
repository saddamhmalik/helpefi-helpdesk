<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Services\MessagingSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagingSettingController extends Controller
{
    public function __construct(private MessagingSettingService $messaging)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Messaging', [
            'messaging' => $this->messaging->snapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'account_sid' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_from' => ['nullable', 'string', 'max:50'],
            'sms_from' => ['nullable', 'string', 'max:50'],
        ]);

        $this->messaging->update($data);

        return back()->with('success', 'Messaging settings saved.');
    }
}
