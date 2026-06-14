<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CompareLandingController extends Controller
{
    public function show(string $competitor): Response
    {
        $definition = CompareLandingDefinition::find($competitor);

        abort_unless($definition !== null, 404);

        return Inertia::render('Central/CompareLanding', [
            ...CentralMarketingPresenter::shared(),
            'competitor' => $competitor,
            'compareMeta' => $definition,
        ]);
    }
}
