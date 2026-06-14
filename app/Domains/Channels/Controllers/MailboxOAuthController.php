<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Services\OAuth\MailOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class MailboxOAuthController extends Controller
{
    public function __construct(
        private MailOAuthService $oauth,
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

    public function disconnect(int $inbox): RedirectResponse
    {
        $this->oauth->disconnect($inbox);

        return back()->with('success', 'Mailbox disconnected.');
    }
}
