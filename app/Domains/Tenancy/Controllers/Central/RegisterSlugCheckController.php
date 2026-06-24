<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\RegistrationVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterSlugCheckController extends Controller
{
    public function __construct(private RegistrationVerificationService $verification)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate($this->verification->slugCheckRules());

        return response()->json($this->verification->slugAvailability($data['slug']));
    }
}
