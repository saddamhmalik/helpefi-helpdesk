<?php

namespace App\Domains\Notifications\Controllers;

use App\Domains\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Settings/Notifications', [
            'settings' => $this->notifications->settingsSnapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'notify_ticket_assigned' => ['required', 'boolean'],
            'notify_customer_reply' => ['required', 'boolean'],
            'notify_sla_breach' => ['required', 'boolean'],
        ]);

        $this->notifications->updateSettings($data);

        return back()->with('success', 'Notification settings saved.');
    }
}
