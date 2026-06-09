<?php

namespace App\Domains\Macros\Controllers;

use App\Domains\Macros\Services\CannedResponseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CannedResponseController extends Controller
{
    public function __construct(
        private CannedResponseService $macros,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Macros', [
            'responses' => $this->macros->listForUser($request->user()->id),
            'placeholders' => $this->macros->placeholderHelp(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->macros->create($data, $request->user()->id);

        return back()->with('success', 'Macro created.');
    }

    public function update(Request $request, int $cannedResponse): RedirectResponse
    {
        $data = $this->validated($request);

        $this->macros->update($cannedResponse, $data, $request->user()->id);

        return back()->with('success', 'Macro updated.');
    }

    public function destroy(Request $request, int $cannedResponse): RedirectResponse
    {
        $this->macros->delete($cannedResponse, $request->user()->id);

        return back()->with('success', 'Macro deleted.');
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->macros->search(
                $request->user()->id,
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function apply(Request $request, int $cannedResponse): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
        ]);

        return response()->json([
            'body' => $this->macros->apply(
                $cannedResponse,
                $request->user()->id,
                $data['ticket_id'] ?? null,
            ),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'shortcut' => ['nullable', 'string', 'max:64'],
            'body' => ['required', 'string'],
            'is_shared' => ['boolean'],
        ]);
    }
}
