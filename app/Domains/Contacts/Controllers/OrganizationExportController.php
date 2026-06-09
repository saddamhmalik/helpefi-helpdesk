<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Services\OrganizationExportService;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationExportController extends Controller
{
    public function __construct(private OrganizationExportService $exportService)
    {
    }

    public function csv(): StreamedResponse
    {
        return $this->exportService->csv();
    }
}
