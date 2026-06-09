<?php

namespace App\Domains\Automation\Controllers\Api;

use App\Domains\Automation\Services\AutomationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(private AutomationService $automationService)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json($this->automationService->meta());
    }

    public function index(): JsonResponse
    {
        return response()->json($this->automationService->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedRule($request);

        return response()->json($this->automationService->create($data), 201);
    }

    public function update(Request $request, int $rule): JsonResponse
    {
        $data = $this->validatedRule($request);

        return response()->json($this->automationService->update($rule, $data));
    }

    public function destroy(int $rule): JsonResponse
    {
        $this->automationService->delete($rule);

        return response()->json(['message' => 'Rule deleted.']);
    }

    private function validatedRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.value' => ['nullable'],
            'actions.*.minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);
    }
}
