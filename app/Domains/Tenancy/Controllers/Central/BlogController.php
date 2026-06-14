<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Central/Blog/Index', [
            ...CentralMarketingPresenter::shared(),
            'posts' => MarketingBlogDefinition::forIndex(),
        ]);
    }

    public function show(string $slug): Response
    {
        $post = MarketingBlogDefinition::find($slug);

        abort_unless($post !== null, 404);

        return Inertia::render('Central/Blog/Show', [
            ...CentralMarketingPresenter::shared(),
            'post' => $post,
            'relatedPosts' => MarketingBlogDefinition::related($slug),
        ]);
    }
}
