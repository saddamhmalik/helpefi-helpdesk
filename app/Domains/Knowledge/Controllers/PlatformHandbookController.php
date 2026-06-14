<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Knowledge\Services\PlatformHandbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformHandbookController extends Controller
{
    public function __construct(private PlatformHandbookService $handbook)
    {
    }

    public function index(Request $request): Response
    {
        $collection = $this->handbook->collection();
        $user = $request->user();

        return Inertia::render('Handbook/Index', [
            'collection' => $collection ? [
                'name' => $collection->name,
                'description' => $collection->description,
            ] : null,
            'sections' => $this->handbook->sections(),
            'canManageVisibility' => (bool) ($user?->hasRole('admin')),
        ]);
    }
}
