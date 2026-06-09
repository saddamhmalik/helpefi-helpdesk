<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Central/Login', [
            ...CentralMarketingPresenter::shared(),
            'prefillSlug' => $request->query('workspace'),
            'prefillEmail' => $request->query('email'),
        ]);
    }

    public function redirect(Request $request): HttpResponse|RedirectResponse
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

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $domain = $tenant->domains()->value('domain');
        $url = "{$scheme}://{$domain}/login";

        if ($request->filled('email')) {
            $url .= '?email='.urlencode($request->string('email')->toString());
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->away($url);
    }
}
