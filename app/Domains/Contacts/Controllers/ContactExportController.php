<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Services\ContactExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __construct(private ContactExportService $exportService)
    {
    }

    public function csv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'access' => ['nullable', 'string', 'in:all,portal,guest'],
        ]);

        $access = $data['access'] ?? 'all';
        $accessFilter = $access === 'all' ? null : $access;

        return $this->exportService->csv(
            $data['search'] ?? null,
            $accessFilter,
        );
    }
}
