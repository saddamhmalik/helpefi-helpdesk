<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use App\Domains\Platform\Services\PlatformEmailTemplateService;
use App\Domains\Platform\Support\PlatformEmailPlaceholders;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmailTemplateController extends Controller
{
    public function __construct(private PlatformEmailTemplateService $templates)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Emails/Index', [
            'templates' => $this->templates->list(),
            'placeholders' => PlatformEmailPlaceholders::definitions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Emails/Form', [
            'template' => null,
            'placeholders' => PlatformEmailPlaceholders::definitions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $this->templates->create($data);

        return redirect()->route('central.admin.emails.index')->with('success', 'Email template created.');
    }

    public function edit(int $template): Response
    {
        return Inertia::render('Central/Admin/Emails/Form', [
            'template' => $this->templates->find($template),
            'placeholders' => PlatformEmailPlaceholders::definitions(),
        ]);
    }

    public function update(Request $request, int $template): RedirectResponse
    {
        $existing = PlatformEmailTemplate::query()->findOrFail($template);
        $data = $this->validateTemplate($request, $existing);

        $this->templates->update($template, $data);

        return redirect()->route('central.admin.emails.index')->with('success', 'Email template updated.');
    }

    public function destroy(int $template): RedirectResponse
    {
        $this->templates->delete($template);

        return redirect()->route('central.admin.emails.index')->with('success', 'Email template deleted.');
    }

    private function validateTemplate(Request $request, ?PlatformEmailTemplate $existing = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:50000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if (! $existing?->is_system) {
            $rules['slug'] = [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('platform_email_templates', 'slug')->ignore($existing?->id),
            ];
        }

        return $request->validate($rules);
    }
}
