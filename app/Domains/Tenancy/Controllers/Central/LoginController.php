<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Central/Login', [
            'centralDomain' => config('tenancy.central_app_domain'),
            'prefillSlug' => $request->query('workspace'),
            'prefillEmail' => $request->query('email'),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $tenant = Tenant::query()->where('slug', $data['slug'])->first();

        if (! $tenant) {
            return back()->withErrors([
                'slug' => 'We could not find a workspace with that URL.',
            ]);
        }

        $domain = $tenant->domains()->value('domain');
        $url = 'http://'.$domain.'/login';

        if ($request->filled('email')) {
            $url .= '?email='.urlencode($request->string('email')->toString());
        }

        return redirect()->away($url);
    }
}
