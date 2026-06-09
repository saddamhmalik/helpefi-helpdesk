<?php

namespace App\Domains\Contacts\Controllers\Api;

use App\Domains\Contacts\Services\ContactService;
use App\Domains\Contacts\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private ContactService $contactService,
        private OrganizationService $organizationService,
    ) {
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'organizations' => $this->organizationService->options(),
            'tags' => $this->contactService->allTags(),
            'custom_field_definitions' => $this->contactService->fieldDefinitions(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $access = $request->string('access')->toString();
        $access = in_array($access, ['portal', 'guest'], true) ? $access : null;

        $contacts = $this->contactService->list(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $access,
        );

        return response()->json([
            ...$contacts->toArray(),
            'stats' => $this->contactService->stats(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->contactService->searchForRequester(
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function show(int $contact): JsonResponse
    {
        return response()->json($this->contactService->show($contact));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedContact($request);
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $contact = $this->contactService->create($data, $request->user()->id);

        if ($tagIds) {
            $contact = $this->contactService->syncTags($contact->id, $tagIds, $request->user()->id);
        }

        return response()->json($this->contactService->show($contact->id), 201);
    }

    public function update(Request $request, int $contact): JsonResponse
    {
        $data = $this->validatedContact($request);
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $this->contactService->update($contact, $data, $request->user()->id);
        $this->contactService->syncTags($contact, $tagIds, $request->user()->id);

        return response()->json($this->contactService->show($contact));
    }

    public function destroy(int $contact): JsonResponse
    {
        $this->contactService->delete($contact);

        return response()->json(['message' => 'Contact deleted.']);
    }

    public function storeNote(Request $request, int $contact): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $note = $this->contactService->addNote($contact, $request->user()->id, $data['body']);

        return response()->json($note, 201);
    }

    private function validatedContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['exists:tags,id'],
            'custom_fields' => ['nullable', 'array'],
        ]);
    }
}
