<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingLead;
use App\Domains\Platform\Repositories\MarketingLeadRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\PendingRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingLeadService
{
    public function __construct(
        private MarketingLeadRepository $leads,
        private PlatformAuditRecorder $audit,
    ) {}

    public function capture(array $data, ?Request $request = null): MarketingLead
    {
        $email = strtolower(trim($data['email'] ?? ''));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Email is required.',
            ]);
        }

        $existing = $this->leads->findLatestByEmail($email);
        $attributes = $this->buildAttributes($data, $request);

        if ($existing && $this->shouldMerge($existing, $attributes)) {
            return $this->mergeIntoExisting($existing, $attributes);
        }

        $lead = $this->leads->create($attributes);

        $this->audit->record('platform.lead.captured', $lead, [
            'source' => $lead->source,
            'intent' => $lead->intent,
        ]);

        return $lead;
    }

    public function captureFromContact(array $data, Request $request): MarketingLead
    {
        return $this->capture([
            'email' => $data['email'],
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'source' => MarketingLead::SOURCE_CONTACT,
            'intent' => $this->intentFromContactTopic($data['topic'] ?? 'other'),
            'topic' => $data['topic'] ?? null,
            'message' => $data['message'] ?? null,
            'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
            'metadata' => [
                'page_url' => $request->headers->get('referer'),
            ],
        ], $request);
    }

    public function captureFromRegistration(PendingRegistration $registration, Request $request): MarketingLead
    {
        return $this->capture([
            'email' => $registration->admin_email,
            'name' => $registration->admin_name,
            'company' => $registration->organization_name,
            'source' => MarketingLead::SOURCE_REGISTRATION,
            'intent' => 'incomplete_signup',
            'message' => 'Started workspace registration but has not verified email yet.',
            'marketing_consent' => true,
            'pending_registration_id' => $registration->id,
            'metadata' => [
                'workspace_slug' => $registration->slug,
                'organization_name' => $registration->organization_name,
                'expires_at' => $registration->expires_at?->toIso8601String(),
            ],
        ], $request);
    }

    public function list(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->leads
            ->paginate($perPage, $filters)
            ->through(fn (MarketingLead $lead) => $this->present($lead));
    }

    public function stats(): array
    {
        return $this->leads->stats();
    }

    public function find(int $id): array
    {
        $lead = $this->leads->find($id);

        if (! $lead) {
            throw (new ModelNotFoundException)->setModel(MarketingLead::class, [$id]);
        }

        return $this->present($lead, detailed: true);
    }

    public function captureRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'source' => ['required', 'string', 'in:homepage,chatbot'],
            'intent' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('platform_marketing_leads.intents', [])))],
            'message' => ['nullable', 'string', 'max:5000'],
            'marketing_consent' => ['accepted'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'chat_transcript' => ['nullable', 'array', 'max:20'],
            'chat_transcript.*.role' => ['required_with:chat_transcript', 'string', 'in:user,assistant'],
            'chat_transcript.*.text' => ['required_with:chat_transcript', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function filterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('platform_marketing_leads.sources', [])))],
            'intent' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('platform_marketing_leads.intents', [])))],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('platform_marketing_leads.statuses', [])))],
            'consent' => ['nullable', 'string', 'in:yes,no'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function statusRules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('platform_marketing_leads.statuses', [])))],
        ];
    }

    public function notesRules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function updateStatus(int $id, string $status): array
    {
        $lead = $this->leads->find($id);

        if (! $lead) {
            throw (new ModelNotFoundException)->setModel(MarketingLead::class, [$id]);
        }

        $updates = ['status' => $status];

        if ($status === MarketingLead::STATUS_CONTACTED && $lead->contacted_at === null) {
            $updates['contacted_at'] = now();
        }

        $this->leads->update($lead, $updates);

        $this->audit->record('platform.lead.status_updated', $lead, [
            'status' => $status,
        ]);

        return $this->present($lead->fresh(), detailed: true);
    }

    public function updateNotes(int $id, ?string $notes): array
    {
        $lead = $this->leads->find($id);

        if (! $lead) {
            throw (new ModelNotFoundException)->setModel(MarketingLead::class, [$id]);
        }

        $this->leads->update($lead, ['notes' => $notes]);

        return $this->present($lead->fresh(), detailed: true);
    }

    public function markRegistrationConverted(string $email): void
    {
        $lead = $this->leads->findLatestByEmail(strtolower(trim($email)));

        if (! $lead) {
            return;
        }

        $this->leads->update($lead, [
            'status' => MarketingLead::STATUS_CONVERTED,
            'intent' => 'incomplete_signup',
        ]);
    }

    private function buildAttributes(array $data, ?Request $request): array
    {
        $consent = (bool) ($data['marketing_consent'] ?? false);
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        if ($request) {
            $metadata = array_merge($metadata, $this->requestMetadata($request));
        }

        return [
            'email' => strtolower(trim($data['email'])),
            'name' => $this->nullableString($data['name'] ?? null),
            'company' => $this->nullableString($data['company'] ?? null),
            'source' => (string) ($data['source'] ?? MarketingLead::SOURCE_HOMEPAGE),
            'intent' => (string) ($data['intent'] ?? 'demo'),
            'status' => MarketingLead::STATUS_NEW,
            'topic' => $this->nullableString($data['topic'] ?? null),
            'message' => $this->nullableString($data['message'] ?? null),
            'marketing_consent_at' => $consent ? now() : null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
            'pending_registration_id' => $data['pending_registration_id'] ?? null,
        ];
    }

    private function shouldMerge(MarketingLead $existing, array $attributes): bool
    {
        if (in_array($existing->status, [MarketingLead::STATUS_CONVERTED, MarketingLead::STATUS_SPAM], true)) {
            return false;
        }

        return $existing->created_at?->gte(now()->subDays(30)) === true;
    }

    private function mergeIntoExisting(MarketingLead $existing, array $attributes): MarketingLead
    {
        $metadata = array_merge($existing->metadata ?? [], $attributes['metadata'] ?? []);
        $metadata['touchpoints'] = array_values(array_unique(array_merge(
            $metadata['touchpoints'] ?? [],
            [$attributes['source']],
        )));

        $updates = [
            'metadata' => $metadata,
        ];

        if ($attributes['name'] && ! $existing->name) {
            $updates['name'] = $attributes['name'];
        }

        if ($attributes['company'] && ! $existing->company) {
            $updates['company'] = $attributes['company'];
        }

        if ($attributes['message']) {
            $updates['message'] = trim(($existing->message ? $existing->message."\n\n" : '').$attributes['message']);
        }

        if ($attributes['marketing_consent_at'] && ! $existing->marketing_consent_at) {
            $updates['marketing_consent_at'] = $attributes['marketing_consent_at'];
        }

        if ($attributes['pending_registration_id']) {
            $updates['pending_registration_id'] = $attributes['pending_registration_id'];
        }

        if ($existing->status === MarketingLead::STATUS_CLOSED) {
            $updates['status'] = MarketingLead::STATUS_NEW;
        }

        return $this->leads->update($existing, $updates);
    }

    private function intentFromContactTopic(string $topic): string
    {
        return match ($topic) {
            'sales' => 'sales',
            'support' => 'support',
            'partnership' => 'partnership',
            'enterprise' => 'enterprise',
            default => 'other',
        };
    }

    private function requestMetadata(Request $request): array
    {
        return array_filter([
            'page_url' => $request->input('page_url') ?: $request->headers->get('referer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
        ]);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function present(MarketingLead $lead, bool $detailed = false): array
    {
        $payload = [
            'id' => $lead->id,
            'email' => $lead->email,
            'name' => $lead->name,
            'company' => $lead->company,
            'source' => $lead->source,
            'intent' => $lead->intent,
            'status' => $lead->status,
            'topic' => $lead->topic,
            'message' => $lead->message,
            'has_marketing_consent' => $lead->marketing_consent_at !== null,
            'marketing_consent_at' => $lead->marketing_consent_at?->toIso8601String(),
            'pending_registration_id' => $lead->pending_registration_id,
            'contacted_at' => $lead->contacted_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['metadata'] = $lead->metadata ?? [];
            $payload['notes'] = $lead->notes;
            $payload['ip_address'] = $lead->ip_address;
            $payload['user_agent'] = $lead->user_agent;
        }

        return $payload;
    }
}
