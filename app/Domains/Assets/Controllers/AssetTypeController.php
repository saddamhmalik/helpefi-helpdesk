<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Assets\Services\AssetTypeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AssetTypeController extends Controller
{
    public function __construct(private AssetTypeService $assetTypes)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Assets/Types', [
            'types' => $this->assetTypes->list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->assetTypes->create($data['name']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Asset type created.');
    }

    public function update(Request $request, int $type): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->assetTypes->update($type, $data['name']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Asset type updated.');
    }

    public function destroy(int $type): RedirectResponse
    {
        try {
            $this->assetTypes->delete($type);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Asset type deleted.');
    }
}
