<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Services\ContactService;
use App\Domains\Contacts\Services\ContactTimelineService;
use App\Domains\Contacts\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private ContactService $contactService,
        private ContactTimelineService $timelineService,
        private OrganizationService $organizationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $access = $request->string('access')->toString();
        $access = in_array($access, ['all', 'portal', 'guest'], true) ? $access : 'all';

        return Inertia::render('Contacts/Index', [
            'contacts' => $this->contactService->list(
                15,
                $request->string('search')->toString() ?: null,
                $access === 'all' ? null : $access,
            ),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'access' => $access,
            ],
            'stats' => $this->contactService->stats(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Contacts/Create', [
            'organizations' => $this->organizationService->options(),
            'tags' => $this->contactService->allTags(),
            'customFieldDefinitions' => $this->contactService->fieldDefinitions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['exists:tags,id'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $contact = $this->contactService->create($data, $request->user()->id);

        if ($tagIds) {
            $this->contactService->syncTags($contact->id, $tagIds, $request->user()->id);
        }

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact created.');
    }

    public function show(int $contact): Response
    {
        return Inertia::render('Contacts/Show', [
            'contact' => $this->contactService->show($contact),
            'timeline' => $this->timelineService->forContact($contact),
            'organizations' => $this->organizationService->options(),
            'tags' => $this->contactService->allTags(),
            'customFieldDefinitions' => $this->contactService->fieldDefinitions(),
        ]);
    }

    public function update(Request $request, int $contact): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['exists:tags,id'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $this->contactService->update($contact, $data, $request->user()->id);
        $this->contactService->syncTags($contact, $tagIds, $request->user()->id);

        return back()->with('success', 'Contact updated.');
    }

    public function storeNote(Request $request, int $contact): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $this->contactService->addNote($contact, $request->user()->id, $data['body']);

        return back()->with('success', 'Note added.');
    }

    public function destroy(int $contact): RedirectResponse
    {
        $this->contactService->delete($contact);

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->contactService->searchForRequester(
                $request->string('q')->toString(),
            ),
        ]);
    }
}
