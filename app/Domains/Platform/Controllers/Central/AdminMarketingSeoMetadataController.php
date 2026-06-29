<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\MarketingSeoMetadataService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketingSeoMetadataController extends Controller
{
    public function __construct(private MarketingSeoMetadataService $meta)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Seo/Index', [
            'entries' => $this->meta->listForAdmin(),
        ]);
    }

    public function edit(string $pageKey): Response
    {
        return Inertia::render('Central/Admin/Seo/Form', [
            'entry' => $this->meta->findForAdmin($pageKey),
        ]);
    }

    public function update(Request $request, string $pageKey): RedirectResponse
    {
        $data = $request->validate($this->meta->validationRules());
        $this->meta->updateManual($pageKey, $data);

        return redirect()->route('central.admin.seo.index')->with('success', 'SEO metadata updated.');
    }

    public function generate(Request $request, string $pageKey): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
        ]);

        $this->meta->generateFromContent($pageKey, $data['content']);

        return redirect()->route('central.admin.seo.edit', ['pageKey' => $pageKey])->with('success', 'AI SEO metadata generated.');
    }
}

