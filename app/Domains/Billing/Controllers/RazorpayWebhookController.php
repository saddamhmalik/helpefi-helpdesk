<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Services\RazorpayBillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function __construct(private RazorpayBillingService $razorpay)
    {
    }

    public function __invoke(Request $request): Response
    {
        if (! $this->razorpay->isEnabled()) {
            return response('Razorpay billing is disabled.', 503);
        }

        try {
            $this->razorpay->handleWebhook(
                $request->getContent(),
                $request->header('X-Razorpay-Signature'),
            );
        } catch (\Throwable $exception) {
            Log::warning('Razorpay webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return response('Webhook error.', 400);
        }

        return response('OK', 200);
    }
}
