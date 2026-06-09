<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Security\Services\AuditLogService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function accept(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));

        if ($email === '') {
            abort(403);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->hasRole('admin')) {
            abort(403);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor_verified', true);

        $this->audit->record('auth.login', $user->id, $user->email, properties: [
            'via' => 'workspace_welcome',
        ]);

        return redirect()
            ->route('setup')
            ->with('welcome', true);
    }
}
