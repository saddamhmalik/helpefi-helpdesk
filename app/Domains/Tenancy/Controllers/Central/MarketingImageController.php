<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\MarketingImageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketingImageController extends Controller
{
    public function __invoke(Request $request, MarketingImageService $images): Response
    {
        $path = (string) $request->query('path', '');
        $width = (int) $request->query('w', 0);
        $format = (string) $request->query('fmt', 'auto');
        $quality = (int) $request->query('q', 78);
        $blur = (bool) $request->boolean('blur', false);

        if ($path === '' || $width <= 0) {
            return response('Not Found', 404);
        }

        if (! $request->isMethod('get')) {
            return response('Not Found', 404);
        }

        $response = $images->response($path, $width, $format, $quality, $blur);

        return $response ?? response('Not Found', 404);
    }
}

