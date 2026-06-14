<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Services\EmailInboxService;
use App\Domains\Channels\Services\InboundMailboxPollService;
use App\Domains\Channels\Services\OAuth\MailOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MailboxOAuthController extends Controller
{
    public function __construct(
        private MailOAuthService $oauth,
        private EmailInboxService $inboxes,
        private InboundMailboxPollService $pollService,
    ) {
    }

    public function redirect(int $inbox, string $provider): RedirectResponse
    {
        try {
            $url = $this->oauth->beginConnect($inbox, $provider);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('settings.email')->withErrors(['oauth' => $exception->getMessage()]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('settings.email')->withErrors([
                'oauth' => $request->string('error_description', $request->string('error'))->toString(),
            ]);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $inbox = $this->oauth->handleCallback($provider, $request->string('code')->toString(), $request->string('state')->toString());
            $stats = $this->pollService->pollInbox($inbox);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('settings.email')->withErrors(['oauth' => $exception->getMessage()]);
        }

        return redirect()->route('settings.email')->with([
            'success' => sprintf(
                'Connected %s via %s. Imported %d message(s): %d ticket(s), %d reply(ies).',
                $inbox->oauth_connected_email,
                ucfirst($provider),
                $stats['fetched'],
                $stats['created'],
                $stats['reply'],
            ),
            'created_inbox_id' => $inbox->id,
        ]);
    }

    public function disconnect(int $inbox): RedirectResponse
    {
        $this->oauth->disconnect($inbox);

        return back()->with('success', 'Mailbox disconnected.');
    }
}
