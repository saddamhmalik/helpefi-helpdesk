<?php

namespace App\Domains\Billing\Controllers\Api;

use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private PlanRepository $planRepository,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->billingService->snapshot());
    }

    public function updatePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', $this->planRepository->slugs())],
        ]);

        $this->billingService->changePlan($data['plan']);

        return response()->json($this->billingService->snapshot());
    }
}
