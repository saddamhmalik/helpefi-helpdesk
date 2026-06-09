<?php

namespace App\Domains\Tenancy\Controllers;

use App\Domains\Security\Services\AuditLogService;
use App\Domains\Tenancy\Services\TenantWelcomeTokenService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private TenantWelcomeTokenService $tokens,
    ) {
    }

    public function accept(Request $request): RedirectResponse
    {
        $payload = $this->tokens->consume((string) $request->query('token', ''));

        if (! $payload || ($payload['tenant_id'] ?? null) !== tenant('id')) {
            abort(403, 'This welcome link is invalid or has expired.');
        }

        $email = $payload['email'] ?? '';

        if ($email === '') {
            abort(403);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unable to sign in to this workspace.');
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
