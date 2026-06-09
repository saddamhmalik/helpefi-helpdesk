<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Assets\Services\AssetExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetExportController extends Controller
{
    public function __construct(private AssetExportService $exports)
    {
    }

    public function csv(Request $request): StreamedResponse
    {
        return $this->exports->csv($request->only([
            'search',
            'status',
            'asset_type_id',
            'organization_id',
            'unassigned',
            'warranty_expiring',
        ]));
    }
}
