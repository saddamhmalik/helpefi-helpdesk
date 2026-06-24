<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\MarketingContactBotGuard;
use App\Domains\Tenancy\Services\MarketingContactRateLimiter;
use App\Domains\Tenancy\Services\MarketingContactService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingContactController extends Controller
{
    public function __construct(
        private MarketingContactService $contacts,
        private MarketingContactRateLimiter $rateLimiter,
        private MarketingContactBotGuard $botGuard,
    ) {
    }

    public function index(): Response
    {
        $this->botGuard->beginFormSession();

        return Inertia::render('Central/Contact', [
            ...CentralMarketingPresenter::shared(),
            'turnstileSiteKey' => $this->botGuard->turnstileSiteKey(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->botGuard->isSilentBot($request)) {
            return redirect()->route('central.static.contact');
        }

        $validated = $request->validate($this->contacts->submitRules());

        $this->botGuard->assertTurnstile($request);
        $this->rateLimiter->assertWithinLimit($request);
        $this->contacts->submit($validated, $request);
        $this->rateLimiter->recordAttempt($request);

        return redirect()
            ->route('central.static.contact')
            ->with('contactSubmitted', true)
            ->with('contactReplyEmail', $validated['email']);
    }
}
