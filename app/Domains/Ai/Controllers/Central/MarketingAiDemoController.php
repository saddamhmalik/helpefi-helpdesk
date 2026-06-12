<?php

namespace App\Domains\Ai\Controllers\Central;

use App\Domains\Ai\Services\CentralMarketingAiService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingAiDemoController extends Controller
{
    public function __construct(private CentralMarketingAiService $demo)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($this->demo->ask($validated['query']));
    }
}
