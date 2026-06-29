<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Repositories\MarketingContentDraftRepository;
use App\Domains\Platform\Services\MarketingContentDraftService;
use App\Domains\Platform\Services\MarketingContentGenerationService;
use App\Domains\Platform\Services\MarketingContentPublishService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketingContentController extends Controller
{
    public function __construct(
        private MarketingContentDraftService $drafts,
        private MarketingContentGenerationService $generator,
        private MarketingContentPublishService $publisher,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Content/Index', [
            'drafts' => $this->drafts->listForAdmin(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Content/Form', [
            'draft' => null,
            'options' => $this->drafts->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->drafts->generationRules());
        $draft = $this->generator->generate($data);

        return redirect()
            ->route('central.admin.content.edit', ['draft' => $draft->id])
            ->with('success', 'AI content draft generated. Review and edit before publishing.');
    }

    public function edit(int $draft): Response
    {
        return Inertia::render('Central/Admin/Content/Form', [
            'draft' => $this->drafts->findForAdmin($draft),
            'options' => $this->drafts->options(),
        ]);
    }

    public function update(Request $request, int $draft): RedirectResponse
    {
        $data = $request->validate($this->drafts->validationRules());
        $this->drafts->update($draft, $data);

        return redirect()
            ->route('central.admin.content.edit', ['draft' => $draft])
            ->with('success', 'Draft saved.');
    }

    public function regenerate(int $draft): RedirectResponse
    {
        $model = app(MarketingContentDraftRepository::class)->find($draft);
        $this->generator->regenerate($model);

        return redirect()
            ->route('central.admin.content.edit', ['draft' => $draft])
            ->with('success', 'Content regenerated.');
    }

    public function publish(int $draft): RedirectResponse
    {
        $result = $this->publisher->publish($draft);

        return redirect()
            ->route('central.admin.content.edit', ['draft' => $draft])
            ->with('success', 'Content published successfully.');
    }

    public function destroy(int $draft): RedirectResponse
    {
        $this->drafts->delete($draft);

        return redirect()
            ->route('central.admin.content.index')
            ->with('success', 'Draft deleted.');
    }
}
