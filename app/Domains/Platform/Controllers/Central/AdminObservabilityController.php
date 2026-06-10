<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminObservabilityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return TelescopeServiceProvider::redirectResponse($request);
    }
}
