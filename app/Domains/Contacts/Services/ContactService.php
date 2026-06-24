<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\ContactNote;
use App\Domains\Contacts\Repositories\ContactActivityRepository;
use App\Domains\Contacts\Repositories\ContactRepository;
use App\Domains\Contacts\Repositories\TagRepository;
use App\Domains\Reports\Support\DashboardWidgetCache;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(
        private ContactRepository $contacts,
        private ContactActivityRepository $activities,
        private TagRepository $tags,
        private AuditRecorder $audit,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function fieldDefinitions(): array
    {
        return $this->helpdeskSettings->contactFieldDefinitions();
    }

    public function list(int $perPage = 15, ?string $search = null, ?string $access = null): LengthAwarePaginator
    {
        return $this->contacts->paginate($perPage, $search, $access);
    }

    public function stats(): array
    {
        return $this->contacts->stats();
    }

    public function options(): Collection
    {
        return $this->contacts->allForSelect();
    }

    public function searchForRequester(string $query, int $limit = 8): array
    {
        return $this->contacts->searchForRequester($query, $limit)
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ])
            ->all();
    }

    public function show(int $id): Contact
    {
        return $this->contacts->find($id);
    }

    public function findOrCreateByEmail(string $email, string $name, ?int $userId = null): Contact
    {
        $existing = Contact::query()->where('email', $email)->exists();

        $contact = $this->contacts->findOrCreateByEmail($email, $name);
        $contact->load(['organization', 'tags']);

        if (! $existing) {
            if (empty($contact->organization_id)) {
                $orgId = $this->activities->findOrganizationByEmailDomain($email);
                if ($orgId) {
                    $contact = $this->contacts->update($contact, ['organization_id' => $orgId]);
                    $contact->load('organization');
                }
            }

            $this->activities->log($contact->id, $userId, 'created', 'Contact created via portal');
        }

        return $contact;
    }

    public function findOrCreateByPhone(string $phone, string $name): Contact
    {
        return $this->contacts->findOrCreateByPhone($phone, $name);
    }

    public function create(array $data, ?int $userId = null): Contact
    {
        if (empty($data['organization_id']) && ! empty($data['email'])) {
            $data['organization_id'] = $this->activities->findOrganizationByEmailDomain($data['email']);
        }

        $data['custom_fields'] = $this->resolveCustomFields($data['custom_fields'] ?? []);

        $contact = $this->contacts->create($data);
        $contact->load(['organization', 'tags']);

        $this->activities->log(
            $contact->id,
            $userId,
            'created',
            'Contact created',
        );

        if ($contact->organization_id) {
            $this->activities->log(
                $contact->id,
                $userId,
                'organization_linked',
                'Linked to organization '.$contact->organization->name,
            );
        }

        $this->audit->record('contact.created', $contact, [
            'email' => $contact->email,
            'name' => $contact->name,
        ], $userId);

        DashboardWidgetCache::forget();

        return $contact;
    }

    public function update(int $id, array $data, ?int $userId = null): Contact
    {
        $contact = $this->contacts->find($id);
        $previousOrg = $contact->organization_id;
        $before = $contact->only(array_keys($data));

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->resolveCustomFields($data['custom_fields'] ?? []);
        }

        $contact = $this->contacts->update($contact, $data);
        $contact->load('organization');

        $this->activities->log($contact->id, $userId, 'updated', 'Contact details updated');

        if ($contact->organization_id && $contact->organization_id !== $previousOrg) {
            $this->activities->log(
                $contact->id,
                $userId,
                'organization_linked',
                'Linked to organization '.$contact->organization?->name,
            );
        }

        $updated = $this->contacts->find($id);
        $this->audit->recordChanges('contact.updated', $updated, $before, $updated->only(array_keys($data)), [
            'email' => $updated->email,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $contact = $this->contacts->find($id);
        $this->contacts->delete($contact);

        $this->audit->record('contact.deleted', $contact, [
            'email' => $contact->email,
            'name' => $contact->name,
        ]);

        DashboardWidgetCache::forget();
    }

    public function addNote(int $contactId, int $userId, string $body): ContactNote
    {
        $body = MessageBodySanitizer::sanitize($body);

        if (MessageBodySanitizer::isEmpty($body)) {
            throw ValidationException::withMessages([
                'body' => 'Note cannot be empty.',
            ]);
        }

        $note = $this->activities->addNote($contactId, $userId, $body);

        $this->activities->log(
            $contactId,
            $userId,
            'note_added',
            'Note added',
            ['note_id' => $note->id],
        );

        $contact = $this->contacts->find($contactId);
        $this->audit->record('contact.note_added', $contact, [
            'note_id' => $note->id,
        ], $userId);

        return $note->load('user:id,name');
    }

    public function syncTags(int $contactId, array $tagIds, ?int $userId = null): Contact
    {
        $this->tags->syncForContact($contactId, $tagIds);

        $this->activities->log($contactId, $userId, 'tags_updated', 'Tags updated');

        return $this->contacts->find($contactId);
    }

    public function allTags(): Collection
    {
        return $this->tags->all();
    }

    private function resolveCustomFields(array $values): array
    {
        return $this->helpdeskSettings->resolveFieldValues('contact', $values);
    }
}
