<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\MigrateLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MigrateLandingController extends Controller
{
    public function __construct(private MigrateLandingContentService $content)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/MigrateIndex', [
            ...CentralMarketingPresenter::shared(),
            'migrateHub' => $this->content->hub(),
        ]);
    }

    public function show(string $source): Response
    {
        $definition = MigrateLandingDefinition::find($source);
        $pageContent = $this->content->forSlug($source);

        abort_unless($definition !== null && $pageContent !== null, 404);

        return Inertia::render('Central/MigrateLanding', [
            ...CentralMarketingPresenter::shared(),
            'source' => $source,
            'migrateMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
