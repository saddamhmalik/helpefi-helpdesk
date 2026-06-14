<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class VerticalLandingController extends Controller
{
    public function show(string $vertical): Response
    {
        $definition = VerticalLandingDefinition::find($vertical);

        abort_unless($definition !== null, 404);

        return Inertia::render('Central/VerticalLanding', [
            ...CentralMarketingPresenter::shared(),
            'vertical' => $vertical,
            'verticalMeta' => $definition,
        ]);
    }
}
