<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Assets\Services\AssetImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetImportController extends Controller
{
    public function __construct(private AssetImportService $imports)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $this->imports->import($request->file('file'));

        return redirect()
            ->route('assets.index')
            ->with('success', "Imported {$result['created']} assets. Skipped {$result['skipped']} rows.");
    }
}
