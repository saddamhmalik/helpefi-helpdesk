<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Assets\Services\AssetDiscoveryService;
use App\Domains\Assets\Services\AssetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AssetDiscoveryController extends Controller
{
    public function __construct(
        private AssetDiscoveryService $discovery,
        private AssetService $assets,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Assets/Discovery', [
            'scans' => $this->discovery->listScans(),
            'meta' => $this->assets->meta(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subnet' => ['required', 'string', 'max:64'],
        ]);

        try {
            $scan = $this->discovery->startScan($data['subnet'], $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['subnet' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assets.discovery.show', $scan)
            ->with('success', 'Network scan started.');
    }

    public function show(int $scan): Response
    {
        return Inertia::render('Assets/DiscoveryShow', [
            'scan' => $this->discovery->showScan($scan),
            'meta' => $this->assets->meta(),
        ]);
    }

    public function import(Request $request, int $scan): RedirectResponse
    {
        $data = $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer'],
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'device_names' => ['nullable', 'array'],
            'device_names.*' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->discovery->importDevices(
            $scan,
            $data['device_ids'],
            (int) $data['asset_type_id'],
            $data['device_names'] ?? [],
        );

        return back()->with(
            'success',
            "Imported {$result['imported']} new assets and updated {$result['updated']} existing matches."
        );
    }
}
