<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Models\PlatformNotice;
use App\Domains\Platform\Services\PlatformNoticeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNoticeController extends Controller
{
    public function __construct(private PlatformNoticeService $notices)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Central/Admin/Notices/Index', [
            'notices' => $this->notices->list(),
            'types' => config('platform_notices.types', []),
            'audiences' => config('platform_notices.audiences', []),
            'priorities' => config('platform_notices.priorities', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Admin/Notices/Form', [
            'notice' => null,
            'tenants' => $this->notices->tenantOptions(),
            'types' => config('platform_notices.types', []),
            'audiences' => config('platform_notices.audiences', []),
            'priorities' => config('platform_notices.priorities', []),
            'targetScopes' => config('platform_notices.target_scopes', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->notices->validationRules());
        $this->notices->create($data, $request->file('image'));

        return redirect()->route('central.admin.notices.index')->with('success', 'Notice saved as draft.');
    }

    public function edit(int $notice): Response
    {
        return Inertia::render('Central/Admin/Notices/Form', [
            'notice' => $this->notices->find($notice),
            'tenants' => $this->notices->tenantOptions(),
            'types' => config('platform_notices.types', []),
            'audiences' => config('platform_notices.audiences', []),
            'priorities' => config('platform_notices.priorities', []),
            'targetScopes' => config('platform_notices.target_scopes', []),
        ]);
    }

    public function update(Request $request, int $notice): RedirectResponse
    {
        $existing = PlatformNotice::query()->findOrFail($notice);
        $data = $request->validate($this->notices->validationRules($existing));

        $this->notices->update(
            $notice,
            $data,
            $request->file('image'),
            (bool) ($data['remove_image'] ?? false),
        );

        return redirect()->route('central.admin.notices.index')->with('success', 'Notice updated.');
    }

    public function publish(int $notice): RedirectResponse
    {
        $this->notices->publish($notice);

        return back()->with('success', 'Notice published to selected workspaces.');
    }

    public function deactivate(int $notice): RedirectResponse
    {
        $this->notices->deactivate($notice);

        return back()->with('success', 'Notice deactivated.');
    }

    public function destroy(int $notice): RedirectResponse
    {
        $this->notices->delete($notice);

        return redirect()->route('central.admin.notices.index')->with('success', 'Notice deleted.');
    }

    public function image(int $notice): StreamedResponse
    {
        return $this->notices->imageResponse($notice);
    }
}
