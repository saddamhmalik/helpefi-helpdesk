<?php

namespace App\Domains\Settings\Controllers;

use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TicketSettingController extends Controller
{
    public function __construct(private HelpdeskSettingService $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Settings/Tickets', [
            'settings' => $this->settings->snapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->settings->updateValidationRules());

        try {
            $this->settings->update($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'ticket_number_prefix' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Ticket settings saved.');
    }
}
