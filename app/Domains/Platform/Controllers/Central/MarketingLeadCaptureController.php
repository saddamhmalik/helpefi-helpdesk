<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Models\MarketingLead;
use App\Domains\Platform\Services\MarketingLeadRateLimiter;
use App\Domains\Platform\Services\MarketingLeadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingLeadCaptureController extends Controller
{
    public function __construct(
        private MarketingLeadService $leads,
        private MarketingLeadRateLimiter $rateLimiter,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if ($this->honeypotFilled($request)) {
            return response()->json(['ok' => true]);
        }

        $validated = $request->validate($this->leads->captureRules());

        $this->rateLimiter->assertWithinLimit($request);

        $metadata = array_filter([
            'page_url' => $validated['page_url'] ?? null,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'chat_transcript' => $validated['chat_transcript'] ?? null,
        ]);

        $this->leads->capture([
            'email' => $validated['email'],
            'name' => $validated['name'] ?? null,
            'company' => $validated['company'] ?? null,
            'source' => $validated['source'] === 'chatbot'
                ? MarketingLead::SOURCE_CHATBOT
                : MarketingLead::SOURCE_HOMEPAGE,
            'intent' => $validated['intent'] ?? ($validated['source'] === 'chatbot' ? 'chat' : 'demo'),
            'message' => $validated['message'] ?? null,
            'marketing_consent' => true,
            'metadata' => $metadata,
        ], $request);

        $this->rateLimiter->recordAttempt($request);

        return response()->json(['ok' => true]);
    }

    private function honeypotFilled(Request $request): bool
    {
        $value = $request->input('website');

        return is_string($value) && trim($value) !== '';
    }
}
