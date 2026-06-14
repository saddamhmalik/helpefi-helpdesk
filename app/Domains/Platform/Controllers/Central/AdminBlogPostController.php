<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Models\MarketingBlogPost;
use App\Domains\Platform\Services\MarketingBlogPostService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBlogPostController extends Controller
{
    public function __construct(private MarketingBlogPostService $posts)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Blog/Index', [
            'posts' => $this->posts->listForAdmin(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Blog/Form', [
            'post' => null,
            'slugOptions' => $this->posts->slugOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->posts->validationRules());
        $this->posts->create($data);

        return redirect()->route('central.admin.blog.index')->with('success', 'Blog post saved.');
    }

    public function edit(int $post): Response
    {
        return Inertia::render('Central/Admin/Blog/Form', [
            'post' => $this->posts->findForAdmin($post),
            'slugOptions' => $this->posts->slugOptions($post),
        ]);
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        $existing = MarketingBlogPost::query()->findOrFail($post);
        $data = $request->validate($this->posts->validationRules($existing));
        $this->posts->update($post, $data);

        return redirect()->route('central.admin.blog.index')->with('success', 'Blog post updated.');
    }

    public function destroy(int $post): RedirectResponse
    {
        $this->posts->delete($post);

        return redirect()->route('central.admin.blog.index')->with('success', 'Blog post deleted.');
    }
}
