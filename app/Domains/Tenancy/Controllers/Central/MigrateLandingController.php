<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MigrateLandingController extends Controller
{
    public function show(string $source): Response
    {
        $definition = MigrateLandingDefinition::find($source);

        abort_unless($definition !== null, 404);

        return Inertia::render('Central/MigrateLanding', [
            ...CentralMarketingPresenter::shared(),
            'source' => $source,
            'migrateMeta' => $definition,
        ]);
    }
}
