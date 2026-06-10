<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Brands\Support\BrandContext;
use App\Domains\Brands\Services\BrandService;
use App\Domains\Channels\Services\ChannelService;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Services\TicketService;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PortalService
{
    public function __construct(
        private KnowledgeService $knowledgeService,
        private KnowledgeLocaleService $localeService,
        private KnowledgeSettingService $knowledgeSettings,
        private ContactService $contactService,
        private TicketService $ticketService,
        private ChannelService $channelService,
        private BrandContext $brandContext,
        private BrandService $brandService,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function home(): array
    {
        $brandId = $this->brandContext->id();
        $locale = $this->localeService->current();

        return [
            'collections' => $this->knowledgeService->publicCollections($brandId),
            'featured' => $this->knowledgeService->featuredPublished(6, $brandId, $locale),
            'locale' => $locale,
            'locales' => $this->knowledgeSettings->localeOptions(),
        ];
    }

    public function collection(string $slug): array
    {
        $brandId = $this->brandContext->id();
        $locale = $this->localeService->current();
        $collection = $this->knowledgeService->collectionBySlugForBrand($slug, $brandId);

        if (! $collection->is_public) {
            throw new ModelNotFoundException;
        }

        return [
            'collection' => $collection,
            'articles' => $this->knowledgeService->publishedArticles($collection->id, null, 15, $brandId, $locale),
            'locale' => $locale,
            'locales' => $this->knowledgeSettings->localeOptions(),
        ];
    }

    public function article(string $slug): array
    {
        $brandId = $this->brandContext->id();
        $locale = $this->localeService->current();
        $article = $this->knowledgeService->publishedArticleBySlug($slug, $brandId, $locale);

        return [
            'article' => $article,
            'translations' => $this->knowledgeService->translations($article->id),
            'locale' => $locale,
            'locales' => $this->knowledgeSettings->localeOptions(),
        ];
    }

    public function search(?string $query): array
    {
        $locale = $this->localeService->current();

        return [
            'query' => $query,
            'articles' => $this->knowledgeService->publishedArticles(null, $query, 15, $this->brandContext->id(), $locale),
            'locale' => $locale,
            'locales' => $this->knowledgeSettings->localeOptions(),
        ];
    }

    public function submitTicket(array $data, ?User $user = null): Ticket
    {
        $brand = $this->brandContext->brand();

        if ($user?->hasRole('customer') && $user->contact_id) {
            $contact = $user->contact;
            $data['email'] = $contact->email;
            $data['name'] = $contact->name;
        } else {
            $contact = $this->contactService->findOrCreateByEmail($data['email'], $data['name']);
        }

        $openStatus = $this->ticketService->statuses()->firstWhere('slug', 'open')
            ?? $this->ticketService->statuses()->first();

        $priorityId = $brand->default_ticket_priority_id
            ?? $this->ticketService->priorities()->firstWhere('slug', 'normal')?->id
            ?? $this->ticketService->priorities()->first()?->id;

        $payload = [
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'contact_id' => $contact->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $priorityId,
            'channel_id' => $this->channelService->portalChannel()->id,
            'brand_id' => $brand->id,
        ];

        if (array_key_exists('custom_fields', $data)) {
            $payload['custom_fields'] = $this->helpdeskSettings->resolveFieldValuesForBrand(
                'ticket',
                $data['custom_fields'] ?? [],
                $brand,
            );
        }

        $ticket = $this->ticketService->create($payload);

        return $this->ticketService->show($ticket->id);
    }

    public function trackTicket(string $number, string $email): ?Ticket
    {
        return $this->findTicketForContactEmail($number, $email);
    }

    public function ticketsForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        if (! $user->contact_id) {
            return Ticket::query()->whereRaw('0 = 1')->paginate($perPage);
        }

        return Ticket::query()
            ->with(['status:id,name,slug,color,is_closed', 'priority:id,name,slug', 'slaTimer'])
            ->where('contact_id', $user->contact_id)
            ->where('brand_id', $this->brandContext->id())
            ->whereNull('merged_into_ticket_id')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function customerTicket(User $user, int $ticketId): Ticket
    {
        if (! $user->contact_id) {
            throw new ModelNotFoundException;
        }

        return Ticket::query()
            ->with([
                'status:id,name,slug,color,is_closed',
                'priority:id,name,slug',
                'slaTimer',
                'csatResponse',
                'messages' => fn ($q) => $q->where('is_internal', false)->with(['user:id,name', 'contact:id,name', 'channel:id,name,slug,type'])->orderBy('created_at'),
            ])
            ->where('contact_id', $user->contact_id)
            ->where('brand_id', $this->brandContext->id())
            ->whereNull('merged_into_ticket_id')
            ->findOrFail($ticketId);
    }

    public function ticketFieldDefinitions(): array
    {
        return $this->helpdeskSettings->ticketFieldDefinitionsForBrand($this->brandContext->brand());
    }

    private function findTicketForContactEmail(string $number, string $email): ?Ticket
    {
        return Ticket::query()
            ->with([
                'status:id,name,slug,color,is_closed',
                'priority:id,name,slug',
                'contact:id,name,email',
                'slaTimer',
                'csatResponse',
                'messages' => fn ($q) => $q->where('is_internal', false)->with(['user:id,name', 'contact:id,name', 'channel:id,name,slug,type'])->orderBy('created_at'),
            ])
            ->where('number', $number)
            ->where('brand_id', $this->brandContext->id())
            ->whereNull('merged_into_ticket_id')
            ->whereHas('contact', fn ($q) => $q->where('email', $email))
            ->first();
    }
}
