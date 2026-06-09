<?php

namespace App\Domains\Settings\Controllers\Api;

use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class HelpdeskSettingController extends Controller
{
    public function __construct(private HelpdeskSettingService $settings)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->settings->snapshot());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate($this->settings->updateValidationRules());

        try {
            return response()->json($this->settings->update($data));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'ticket_number_prefix' => $exception->getMessage(),
            ]);
        }
    }
}
