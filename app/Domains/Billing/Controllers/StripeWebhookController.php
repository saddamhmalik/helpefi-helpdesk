<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Services\StripeBillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeBillingService $stripe)
    {
    }

    public function __invoke(Request $request): Response
    {
        if (! $this->stripe->isEnabled()) {
            return response('Stripe billing is disabled.', 503);
        }

        try {
            $this->stripe->handleWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature'),
            );
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return response('Webhook error.', 400);
        }

        return response('OK', 200);
    }
}
