<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\MemberExportService;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    public function __construct(private MemberExportService $exportService)
    {
    }

    public function csv(): StreamedResponse
    {
        return $this->exportService->csv();
    }
}
