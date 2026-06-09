<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $search)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');

        return response()->json($this->search->search($query));
    }
}
