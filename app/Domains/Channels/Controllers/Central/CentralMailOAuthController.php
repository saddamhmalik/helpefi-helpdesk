<?php

namespace App\Domains\Channels\Controllers\Central;

use App\Domains\Channels\Services\InboundMailboxPollService;
use App\Domains\Channels\Services\OAuth\MailOAuthService;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CentralMailOAuthController extends Controller
{
    public function __construct(
        private MailOAuthService $oauth,
        private InboundMailboxPollService $pollService,
        private TenantDomainService $domains,
    ) {
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $state = $request->string('state')->toString();

        if ($request->filled('error')) {
            return $this->redirectToTenant($state, [
                'oauth_error' => $request->string('error_description', $request->string('error'))->toString(),
            ]);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $cached = $this->oauth->pullState($state);

        if (! $cached || ($cached['provider'] ?? null) !== $provider) {
            return redirect($this->fallbackUrl())->with('error', 'OAuth session expired or invalid.');
        }

        $tenant = Tenant::query()->find($cached['tenant_id'] ?? null);

        if (! $tenant) {
            return redirect($this->fallbackUrl())->with('error', 'Workspace not found.');
        }

        tenancy()->initialize($tenant);

        try {
            $inbox = $this->oauth->completeConnect($provider, $request->string('code')->toString(), $cached);
            $stats = $this->pollService->pollInbox($inbox);
        } catch (InvalidArgumentException $exception) {
            tenancy()->end();

            return $this->redirectToTenantSettings($tenant, [
                'oauth_error' => $exception->getMessage(),
            ]);
        }

        tenancy()->end();

        return $this->redirectToTenantSettings($tenant, [
            'oauth' => 'connected',
            'provider' => $provider,
            'email' => $inbox->oauth_connected_email,
            'inbox' => (string) $inbox->id,
            'fetched' => (string) ($stats['fetched'] ?? 0),
            'created' => (string) ($stats['created'] ?? 0),
            'reply' => (string) ($stats['reply'] ?? 0),
        ]);
    }

    private function redirectToTenant(string $state, array $query): RedirectResponse
    {
        $cached = $this->oauth->peekState($state);
        $tenant = isset($cached['tenant_id']) ? Tenant::query()->find($cached['tenant_id']) : null;

        if ($tenant instanceof Tenant) {
            return $this->redirectToTenantSettings($tenant, $query);
        }

        return redirect($this->fallbackUrl())->with('error', $query['oauth_error'] ?? 'OAuth failed.');
    }

    private function redirectToTenantSettings(Tenant $tenant, array $query): RedirectResponse
    {
        $base = $this->domains->primaryUrl($tenant);

        if (! $base) {
            return redirect($this->fallbackUrl())->with('error', $query['oauth_error'] ?? 'Workspace URL not available.');
        }

        return redirect()->away($base.'/settings/email?'.http_build_query($query));
    }

    private function fallbackUrl(): string
    {
        return (string) config('helpdesk.mail_oauth.callback_base_url', config('app.url'));
    }
}
