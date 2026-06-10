<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Services\OpenApiService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenApiController extends Controller
{
    public function __construct(private OpenApiService $openApi)
    {
    }

    public function spec(Request $request): JsonResponse
    {
        return response()->json($this->openApi->spec($request));
    }

    public function docs(Request $request): View
    {
        return view('api-docs', [
            'specUrl' => url('/api/v1/openapi.json'),
        ]);
    }
}
