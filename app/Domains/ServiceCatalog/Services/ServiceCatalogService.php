<?php

namespace App\Domains\ServiceCatalog\Services;

use App\Domains\Brands\Support\BrandContext;
use App\Domains\Channels\Services\ChannelService;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceCatalog\Repositories\ServiceCatalogItemRepository;
use App\Domains\ServiceCatalog\Repositories\ServiceCategoryRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Services\TicketService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceCatalogService
{
    public function __construct(
        private ServiceCategoryRepository $categories,
        private ServiceCatalogItemRepository $items,
        private ContactService $contacts,
        private TicketService $tickets,
        private ChannelService $channels,
        private BrandContext $brandContext,
    ) {
    }

    public function adminCatalog(): Collection
    {
        return $this->categories->allWithItems();
    }

    public function publicCatalog(): Collection
    {
        return $this->categories->publicWithItems();
    }

    public function publicItem(string $slug): ServiceCatalogItem
    {
        return $this->items->findPublicBySlug($slug);
    }

    public function meta(Collection $priorities): array
    {
        return [
            'ticket_types' => [
                ['value' => ServiceCatalogItem::TYPE_INCIDENT, 'label' => 'Incident'],
                ['value' => ServiceCatalogItem::TYPE_SERVICE_REQUEST, 'label' => 'Service request'],
                ['value' => ServiceCatalogItem::TYPE_CHANGE, 'label' => 'Change'],
                ['value' => ServiceCatalogItem::TYPE_PROBLEM, 'label' => 'Problem'],
            ],
            'field_types' => [
                ['value' => 'text', 'label' => 'Text'],
                ['value' => 'textarea', 'label' => 'Textarea'],
                ['value' => 'select', 'label' => 'Select'],
            ],
            'priorities' => $priorities,
        ];
    }

    public function createCategory(array $data): Collection
    {
        $this->categories->create($this->validatedCategory($data));

        return $this->categories->allWithItems();
    }

    public function updateCategory(int $id, array $data): Collection
    {
        $this->categories->update($this->categories->find($id), $this->validatedCategory($data));

        return $this->categories->allWithItems();
    }

    public function deleteCategory(int $id): Collection
    {
        $this->categories->delete($this->categories->find($id));

        return $this->categories->allWithItems();
    }

    public function createItem(array $data): Collection
    {
        $this->items->create($this->validatedItem($data));

        return $this->categories->allWithItems();
    }

    public function updateItem(int $id, array $data): Collection
    {
        $this->items->update($this->items->find($id), $this->validatedItem($data));

        return $this->categories->allWithItems();
    }

    public function deleteItem(int $id): Collection
    {
        $this->items->delete($this->items->find($id));

        return $this->categories->allWithItems();
    }

    public function submitRequest(string $slug, array $data, ?User $user = null): Ticket
    {
        $item = $this->items->findPublicBySlug($slug);
        $payload = $this->validatedSubmission($item, $data, $user);

        if ($user?->hasRole('customer') && $user->contact_id) {
            $contact = $user->contact;
            $payload['email'] = $contact->email;
            $payload['name'] = $contact->name;
        } else {
            $contact = $this->contacts->findOrCreateByEmail($payload['email'], $payload['name']);
        }

        $openStatus = $this->tickets->statuses()->firstWhere('slug', 'open')
            ?? $this->tickets->statuses()->first();

        $priorityId = $item->ticket_priority_id
            ?? $this->tickets->priorities()->firstWhere('slug', 'normal')?->id
            ?? $this->tickets->priorities()->first()?->id;

        $description = $this->buildDescription($item, $payload['fields'], $payload['details'] ?? null);

        $ticket = $this->tickets->create([
            'subject' => $item->name,
            'description' => $description,
            'contact_id' => $contact->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $priorityId,
            'channel_id' => $this->channels->portalChannel()->id,
            'brand_id' => $this->brandContext->id(),
            'type' => $item->ticket_type,
            'service_catalog_item_id' => $item->id,
        ]);

        return $this->tickets->show($ticket->id);
    }

    private function validatedCategory(array $data): array
    {
        return Validator::validate($data, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validatedItem(array $data): array
    {
        $validated = Validator::validate($data, [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticket_type' => ['required', 'string', 'in:'.implode(',', [
                ServiceCatalogItem::TYPE_INCIDENT,
                ServiceCatalogItem::TYPE_SERVICE_REQUEST,
                ServiceCatalogItem::TYPE_CHANGE,
                ServiceCatalogItem::TYPE_PROBLEM,
            ])],
            'ticket_priority_id' => ['nullable', 'exists:ticket_priorities,id'],
            'fields' => ['nullable', 'array'],
            'fields.*.name' => ['required_with:fields', 'string', 'max:100'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.type' => ['required_with:fields', 'string', 'in:text,textarea,select'],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['fields'] = collect($validated['fields'] ?? [])
            ->map(fn (array $field) => [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values($field['options'] ?? []),
            ])
            ->values()
            ->all();

        return $validated;
    }

    private function validatedSubmission(ServiceCatalogItem $item, array $data, ?User $user): array
    {
        $rules = [
            'details' => ['nullable', 'string'],
            'fields' => ['array'],
        ];

        if (! $user?->hasRole('customer')) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        foreach ($item->fields ?? [] as $field) {
            $key = 'fields.'.$field['name'];
            $rules[$key] = [$field['required'] ?? false ? 'required' : 'nullable', 'string'];

            if (($field['type'] ?? '') === 'select' && ! empty($field['options'])) {
                $rules[$key][] = 'in:'.implode(',', $field['options']);
            }
        }

        $validated = Validator::validate($data, $rules);

        if ($user?->hasRole('customer')) {
            $validated['name'] = $user->name;
            $validated['email'] = $user->email;
        }

        return $validated;
    }

    private function buildDescription(ServiceCatalogItem $item, array $fields, ?string $details): string
    {
        $lines = ["Service catalog item: {$item->name}"];

        if ($item->description) {
            $lines[] = $item->description;
            $lines[] = '';
        }

        foreach ($item->fields ?? [] as $field) {
            $value = $fields[$field['name']] ?? null;

            if ($value !== null && $value !== '') {
                $lines[] = ($field['label'] ?? $field['name']).': '.$value;
            }
        }

        if ($details) {
            $lines[] = '';
            $lines[] = 'Additional details:';
            $lines[] = $details;
        }

        return implode("\n", $lines);
    }
}
