<?php

namespace App\Domains\Sla\Controllers\Api;

use App\Domains\Sla\Services\SlaEscalationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaEscalationController extends Controller
{
    public function __construct(private SlaEscalationService $escalations)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json($this->escalations->meta());
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'rules' => $this->escalations->listRules(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->escalations->storeValidationRules());
        $rule = $this->escalations->saveRule($data);

        return response()->json(
            $this->escalations->serializeRule($rule->load('policy:id,name')),
            201,
        );
    }

    public function destroy(int $rule): JsonResponse
    {
        $this->escalations->deleteRule($rule);

        return response()->json(['message' => 'Escalation rule deleted.']);
    }
}
