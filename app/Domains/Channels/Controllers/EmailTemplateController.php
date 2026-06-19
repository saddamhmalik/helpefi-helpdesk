<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Channels\Support\EmailTemplateRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function __construct(private EmailTemplateService $templates)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/EmailTemplates/Index', [
            'templates' => $this->templates->list(),
            'placeholders' => EmailTemplateRegistry::allPlaceholders(),
        ]);
    }

    public function edit(int $template): Response
    {
        return Inertia::render('Settings/EmailTemplates/Form', $this->templates->find($template));
    }

    public function update(Request $request, int $template): RedirectResponse
    {
        $this->templates->update($template, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:50000'],
            'is_active' => ['boolean'],
        ]));

        return redirect()->route('settings.email-templates.index')->with('success', 'Email template updated.');
    }

    public function reset(int $template): RedirectResponse
    {
        $this->templates->reset($template);

        return back()->with('success', 'Email template restored to default.');
    }

    public function preview(Request $request, int $template): JsonResponse
    {
        $templateData = $this->templates->find($template);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:50000'],
        ]);

        $placeholderKeys = collect($templateData['placeholders'] ?? [])
            ->pluck('key')
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        return response()->json($this->templates->previewDraft(
            $data['subject'],
            $data['body_html'],
            $placeholderKeys,
        ));
    }
}
