<?php

namespace App\Domains\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminHubController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            return redirect()->route('settings.profile');
        }

        return Inertia::render('Settings/Overview');
    }
}
