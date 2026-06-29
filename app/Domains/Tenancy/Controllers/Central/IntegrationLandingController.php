<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\IntegrationLandingContentService;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationLandingController extends Controller
{
    public function __construct(private IntegrationLandingContentService $content)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/IntegrationsIndex', [
            ...CentralMarketingPresenter::shared(),
            'integrationsHub' => $this->content->hub(),
        ]);
    }

    public function show(string $integration): Response
    {
        $pageContent = $this->content->forSlug($integration);

        abort_unless($pageContent !== null, 404);

        $definition = IntegrationLandingDefinition::find($integration) ?? [
            'slug' => $integration,
            'seo_key' => IntegrationLandingDefinition::seoKey($integration),
            'path' => IntegrationLandingDefinition::path($integration),
        ];

        return Inertia::render('Central/IntegrationLanding', [
            ...CentralMarketingPresenter::shared(),
            'integration' => $integration,
            'integrationMeta' => $definition,
            'content' => $pageContent,
        ]);
    }
}
