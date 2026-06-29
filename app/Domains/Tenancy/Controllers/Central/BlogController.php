<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingBlogInternalLinks;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $category = is_string($request->query('category')) ? trim($request->query('category')) : '';
        $tags = $request->query('tags', []);

        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map(
                fn (string $t) => trim($t),
                preg_split('/\s*,\s*/', $tags) ?: []
            )));
        }

        if (! is_array($tags)) {
            $tags = [];
        }

        $tags = array_values(array_filter(array_map(
            fn ($t) => is_string($t) ? trim($t) : '',
            $tags
        )));

        $data = MarketingBlogDefinition::forIndexPaginated(
            $q !== '' ? $q : null,
            $category !== '' ? $category : null,
            $tags
        );

        return Inertia::render('Central/Blog/Index', [
            ...CentralMarketingPresenter::shared(),
            'posts' => $data['posts'],
            'pagination' => $data['pagination'],
            'filters' => $data['filters'],
            'availableCategories' => $data['availableCategories'],
            'availableTags' => $data['availableTags'],
        ]);
    }

    public function show(string $slug): Response
    {
        $post = MarketingBlogDefinition::find($slug);

        abort_unless($post !== null, 404);

        return Inertia::render('Central/Blog/Show', [
            ...CentralMarketingPresenter::shared(),
            'post' => $post,
            'recommendedPosts' => MarketingBlogDefinition::recommended($slug, 3),
            'relatedPosts' => MarketingBlogDefinition::related($slug),
            'internalLinks' => MarketingBlogInternalLinks::forSlug($slug),
        ]);
    }
}
