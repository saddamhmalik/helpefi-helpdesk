<?php

namespace App\Domains\Settings\Services;

use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class HelpdeskSettingService
{
    public const DEFAULT_AUTO_FIRST_RESPONSE_BODY = "Hi {{contact_name}},\n\nThank you for contacting us. We have received your request and created ticket [{{ticket_number}}].\n\nSubject: {{ticket_subject}}\n\nOur team will review your message and respond as soon as possible.\n\nTo reply, respond to this email or include [{{ticket_number}}] in the subject line.";

    public function __construct(
        private HelpdeskSettingRepository $settings,
        private AuditRecorder $audit,
    ) {
    }

    public function updateValidationRules(): array
    {
        return [
            'ticket_number_prefix' => ['required', 'string', 'max:20'],
            'contact_fields' => ['nullable', 'array'],
            'contact_fields.*.name' => ['required_with:contact_fields', 'string', 'max:100'],
            'contact_fields.*.label' => ['required_with:contact_fields', 'string', 'max:255'],
            'contact_fields.*.type' => ['required_with:contact_fields', 'string', 'in:text,textarea,select,number,email'],
            'contact_fields.*.required' => ['boolean'],
            'contact_fields.*.options' => ['nullable', 'array'],
            'contact_fields.*.options.*' => ['string', 'max:255'],
            'ticket_fields' => ['nullable', 'array'],
            'ticket_fields.*.name' => ['required_with:ticket_fields', 'string', 'max:100'],
            'ticket_fields.*.label' => ['required_with:ticket_fields', 'string', 'max:255'],
            'ticket_fields.*.type' => ['required_with:ticket_fields', 'string', 'in:text,textarea,select,number,email'],
            'ticket_fields.*.required' => ['boolean'],
            'ticket_fields.*.options' => ['nullable', 'array'],
            'ticket_fields.*.options.*' => ['string', 'max:255'],
            'user_fields' => ['nullable', 'array'],
            'user_fields.*.name' => ['required_with:user_fields', 'string', 'max:100'],
            'user_fields.*.label' => ['required_with:user_fields', 'string', 'max:255'],
            'user_fields.*.type' => ['required_with:user_fields', 'string', 'in:text,textarea,select,number,email'],
            'user_fields.*.required' => ['boolean'],
            'user_fields.*.options' => ['nullable', 'array'],
            'user_fields.*.options.*' => ['string', 'max:255'],
            'auto_first_response_enabled' => ['boolean'],
            'auto_first_response_body' => ['nullable', 'string', 'max:10000'],
            'email_blocklist' => ['nullable', 'array'],
            'email_blocklist.*' => ['string', 'max:255'],
            'sync_ticket_status_from_external_issues' => ['boolean'],
        ];
    }

    public function snapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'ticket_number_prefix' => $setting->ticket_number_prefix,
            'contact_fields' => $setting->contact_fields ?? [],
            'ticket_fields' => $setting->ticket_fields ?? [],
            'user_fields' => $setting->user_fields ?? [],
            'auto_first_response_enabled' => (bool) $setting->auto_first_response_enabled,
            'auto_first_response_body' => $setting->auto_first_response_body ?? self::DEFAULT_AUTO_FIRST_RESPONSE_BODY,
            'email_blocklist' => $setting->email_blocklist ?? [],
            'sync_ticket_status_from_external_issues' => (bool) ($setting->sync_ticket_status_from_external_issues ?? false),
        ];
    }

    public function update(array $data): array
    {
        $validated = $this->validate($data);
        $setting = $this->settings->update($this->settings->current(), $validated);

        $this->audit->record('settings.helpdesk_updated', null, [
            'ticket_number_prefix' => $setting->ticket_number_prefix,
            'contact_field_count' => count($setting->contact_fields ?? []),
            'ticket_field_count' => count($setting->ticket_fields ?? []),
            'user_field_count' => count($setting->user_fields ?? []),
            'auto_first_response_enabled' => (bool) $setting->auto_first_response_enabled,
            'email_blocklist_count' => count($setting->email_blocklist ?? []),
            'sync_ticket_status_from_external_issues' => (bool) ($setting->sync_ticket_status_from_external_issues ?? false),
        ]);

        return $this->snapshot();
    }

    public function ticketNumberPrefix(): string
    {
        return $this->normalizePrefix($this->settings->current()->ticket_number_prefix ?: 'HD-');
    }

    public function contactFieldDefinitions(): array
    {
        return $this->settings->current()->contact_fields ?? [];
    }

    public function ticketFieldDefinitions(): array
    {
        return $this->settings->current()->ticket_fields ?? [];
    }

    public function ticketFieldDefinitionsForBrand(?\App\Domains\Brands\Models\Brand $brand = null): array
    {
        $global = $this->ticketFieldDefinitions();

        if (! $brand || empty($brand->ticket_fields)) {
            return $global;
        }

        return collect($global)
            ->keyBy('name')
            ->merge(collect($brand->ticket_fields)->keyBy('name'))
            ->values()
            ->all();
    }

    public function resolveFieldValuesForBrand(string $context, array $values, ?\App\Domains\Brands\Models\Brand $brand = null): array
    {
        $definitions = match ($context) {
            'ticket' => $this->ticketFieldDefinitionsForBrand($brand),
            'contact' => $this->contactFieldDefinitions(),
            'user' => $this->userFieldDefinitions(),
            default => [],
        };

        try {
            return $this->validateFieldValues($definitions, $values);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'custom_fields' => $exception->getMessage(),
            ]);
        }
    }

    public function userFieldDefinitions(): array
    {
        return $this->settings->current()->user_fields ?? [];
    }

    public function autoFirstResponseEnabled(): bool
    {
        return (bool) $this->settings->current()->auto_first_response_enabled;
    }

    public function autoFirstResponseBody(): string
    {
        $body = trim((string) ($this->settings->current()->auto_first_response_body ?? ''));

        return $body !== '' ? $body : self::DEFAULT_AUTO_FIRST_RESPONSE_BODY;
    }

    public function emailBlocklist(): array
    {
        return $this->settings->current()->email_blocklist ?? [];
    }

    public function syncTicketStatusFromExternalIssues(): bool
    {
        return (bool) ($this->settings->current()->sync_ticket_status_from_external_issues ?? false);
    }

    public function isEmailBlocked(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');

        foreach ($this->emailBlocklist() as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '@')) {
                if ($email === $entry) {
                    return true;
                }

                continue;
            }

            $entryDomain = ltrim($entry, '@');

            if ($domain !== '' && ($domain === $entryDomain || str_ends_with($domain, '.'.$entryDomain))) {
                return true;
            }
        }

        return false;
    }

    public function renderAutoFirstResponseBody(\App\Domains\Tickets\Models\Ticket $ticket): string
    {
        $ticket->loadMissing('contact');

        $replacements = [
            '{{ticket_number}}' => $ticket->number,
            '{{ticket_subject}}' => $ticket->subject,
            '{{contact_name}}' => $ticket->contact?->name ?: ($ticket->contact?->email ?? 'there'),
            '{{contact_email}}' => $ticket->contact?->email ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->autoFirstResponseBody());
    }

    public function validateContactFieldValues(array $values): array
    {
        return $this->validateFieldValues($this->contactFieldDefinitions(), $values);
    }

    public function validateTicketFieldValues(array $values): array
    {
        return $this->validateFieldValues($this->ticketFieldDefinitions(), $values);
    }

    public function validateUserFieldValues(array $values): array
    {
        return $this->validateFieldValues($this->userFieldDefinitions(), $values);
    }

    public function validateFieldValues(array $definitions, array $values): array
    {
        $validated = [];

        foreach ($definitions as $field) {
            $name = $field['name'] ?? null;

            if (! $name) {
                continue;
            }

            $value = $values[$name] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if (($field['required'] ?? false) && ($value === null || $value === '')) {
                throw new InvalidArgumentException("{$field['label']} is required.");
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (($field['type'] ?? 'text') === 'select') {
                $options = $field['options'] ?? [];

                if ($options !== [] && ! in_array($value, $options, true)) {
                    throw new InvalidArgumentException("Invalid value for {$field['label']}.");
                }
            }

            $validated[$name] = $value;
        }

        return $validated;
    }

    public function resolveFieldValues(string $context, array $values): array
    {
        try {
            return match ($context) {
                'contact' => $this->validateContactFieldValues($values),
                'ticket' => $this->validateTicketFieldValues($values),
                'user' => $this->validateUserFieldValues($values),
                default => [],
            };
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'custom_fields' => $exception->getMessage(),
            ]);
        }
    }

    public function emailDetectAutoReplies(): bool
    {
        return (bool) ($this->settings->current()->email_detect_auto_replies ?? true);
    }

    public function emailUseOriginalSenderForForwarded(): bool
    {
        return (bool) ($this->settings->current()->email_use_original_sender_for_forwarded ?? true);
    }

    public function emailUseReplyToAsRequester(): bool
    {
        return (bool) $this->settings->current()->email_use_reply_to_as_requester;
    }

    public function emailIgnoreTicketIdThreading(): bool
    {
        return (bool) $this->settings->current()->email_ignore_ticket_id_threading;
    }

    public function emailCreateTicketOnSubjectChange(): bool
    {
        return (bool) $this->settings->current()->email_create_ticket_on_subject_change;
    }

    public function emailAllowAgentInitiated(): bool
    {
        return (bool) $this->settings->current()->email_allow_agent_initiated;
    }

    public function emailUseAgentNameInFrom(): bool
    {
        return (bool) $this->settings->current()->email_use_agent_name_in_from;
    }

    public function emailAutomaticBcc(): ?string
    {
        $bcc = trim((string) ($this->settings->current()->email_automatic_bcc ?? ''));

        return $bcc === '' ? null : strtolower($bcc);
    }

    public function emailReplyToAddress(): ?string
    {
        $replyTo = trim((string) ($this->settings->current()->email_reply_to_address ?? ''));

        return $replyTo === '' ? null : strtolower($replyTo);
    }

    public function emailFlexibleRecipients(): bool
    {
        return (bool) ($this->settings->current()->email_flexible_recipients ?? true);
    }

    public function emailAdvancedSnapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'email_allow_agent_initiated' => (bool) $setting->email_allow_agent_initiated,
            'email_use_agent_name_in_from' => (bool) $setting->email_use_agent_name_in_from,
            'email_automatic_bcc' => $setting->email_automatic_bcc ?? '',
            'email_reply_to_address' => $setting->email_reply_to_address ?? '',
            'email_use_reply_to_as_requester' => (bool) $setting->email_use_reply_to_as_requester,
            'email_use_original_sender_for_forwarded' => (bool) ($setting->email_use_original_sender_for_forwarded ?? true),
            'email_flexible_recipients' => (bool) ($setting->email_flexible_recipients ?? true),
            'email_ignore_ticket_id_threading' => (bool) $setting->email_ignore_ticket_id_threading,
            'email_create_ticket_on_subject_change' => (bool) $setting->email_create_ticket_on_subject_change,
            'email_detect_auto_replies' => (bool) ($setting->email_detect_auto_replies ?? true),
            'auto_first_response_enabled' => (bool) $setting->auto_first_response_enabled,
            'auto_first_response_body' => $setting->auto_first_response_body ?? self::DEFAULT_AUTO_FIRST_RESPONSE_BODY,
            'email_blocklist' => $setting->email_blocklist ?? [],
        ];
    }

    public function emailAdvancedValidationRules(): array
    {
        return [
            'email_allow_agent_initiated' => ['boolean'],
            'email_use_agent_name_in_from' => ['boolean'],
            'email_automatic_bcc' => ['nullable', 'email', 'max:255'],
            'email_reply_to_address' => ['nullable', 'email', 'max:255'],
            'email_use_reply_to_as_requester' => ['boolean'],
            'email_use_original_sender_for_forwarded' => ['boolean'],
            'email_flexible_recipients' => ['boolean'],
            'email_ignore_ticket_id_threading' => ['boolean'],
            'email_create_ticket_on_subject_change' => ['boolean'],
            'email_detect_auto_replies' => ['boolean'],
            'auto_first_response_enabled' => ['boolean'],
            'auto_first_response_body' => ['nullable', 'string', 'max:10000'],
            'email_blocklist' => ['nullable', 'array'],
            'email_blocklist.*' => ['string', 'max:255'],
        ];
    }

    public function updateEmailAdvanced(array $data): array
    {
        $validated = $this->validateEmailAdvanced($data);
        $this->settings->update($this->settings->current(), $validated);

        $this->audit->record('settings.email_advanced_updated', null, [
            'email_allow_agent_initiated' => $validated['email_allow_agent_initiated'],
            'email_use_agent_name_in_from' => $validated['email_use_agent_name_in_from'],
        ]);

        return $this->emailAdvancedSnapshot();
    }

    public function ticketNumberPattern(): string
    {
        $prefix = preg_quote($this->ticketNumberPrefix(), '/');

        return '/\['.$prefix.'(\d+)\]/i';
    }

    private function validate(array $data): array
    {
        $prefix = $this->normalizePrefix($data['ticket_number_prefix'] ?? 'HD-');

        if ($prefix === '') {
            throw new InvalidArgumentException('Ticket number prefix is required.');
        }

        return [
            'ticket_number_prefix' => $prefix,
            'contact_fields' => $this->normalizeFieldDefinitions($data['contact_fields'] ?? []),
            'ticket_fields' => $this->normalizeFieldDefinitions($data['ticket_fields'] ?? []),
            'user_fields' => $this->normalizeFieldDefinitions($data['user_fields'] ?? []),
            'auto_first_response_enabled' => (bool) ($data['auto_first_response_enabled'] ?? false),
            'auto_first_response_body' => $this->normalizeAutoFirstResponseBody($data['auto_first_response_body'] ?? null),
            'email_blocklist' => $this->normalizeEmailBlocklist($data['email_blocklist'] ?? []),
            'sync_ticket_status_from_external_issues' => (bool) ($data['sync_ticket_status_from_external_issues'] ?? false),
        ];
    }

    private function normalizeAutoFirstResponseBody(?string $body): ?string
    {
        $body = trim((string) $body);

        return $body === '' ? null : $body;
    }

    private function normalizeEmailBlocklist(array $entries): array
    {
        return collect($entries)
            ->map(fn ($entry) => strtolower(trim((string) $entry)))
            ->filter(fn ($entry) => $entry !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeFieldDefinitions(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field) => ! empty($field['name']) && ! empty($field['label']))
            ->map(function ($field) {
                return [
                    'name' => Str::slug($field['name'], '_'),
                    'label' => $field['label'],
                    'type' => in_array($field['type'] ?? 'text', ['text', 'textarea', 'select', 'number', 'email'], true)
                        ? $field['type']
                        : 'text',
                    'required' => (bool) ($field['required'] ?? false),
                    'options' => array_values(array_filter($field['options'] ?? [], fn ($option) => $option !== null && $option !== '')),
                ];
            })
            ->unique('name')
            ->values()
            ->all();
    }

    private function validateEmailAdvanced(array $data): array
    {
        return [
            'email_allow_agent_initiated' => (bool) ($data['email_allow_agent_initiated'] ?? false),
            'email_use_agent_name_in_from' => (bool) ($data['email_use_agent_name_in_from'] ?? false),
            'email_automatic_bcc' => $this->nullableEmail($data['email_automatic_bcc'] ?? null),
            'email_reply_to_address' => $this->nullableEmail($data['email_reply_to_address'] ?? null),
            'email_use_reply_to_as_requester' => (bool) ($data['email_use_reply_to_as_requester'] ?? false),
            'email_use_original_sender_for_forwarded' => (bool) ($data['email_use_original_sender_for_forwarded'] ?? true),
            'email_flexible_recipients' => (bool) ($data['email_flexible_recipients'] ?? true),
            'email_ignore_ticket_id_threading' => (bool) ($data['email_ignore_ticket_id_threading'] ?? false),
            'email_create_ticket_on_subject_change' => (bool) ($data['email_create_ticket_on_subject_change'] ?? false),
            'email_detect_auto_replies' => (bool) ($data['email_detect_auto_replies'] ?? true),
            'auto_first_response_enabled' => (bool) ($data['auto_first_response_enabled'] ?? false),
            'auto_first_response_body' => $this->normalizeAutoFirstResponseBody($data['auto_first_response_body'] ?? null),
            'email_blocklist' => $this->normalizeEmailBlocklist($data['email_blocklist'] ?? []),
        ];
    }

    private function nullableEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));

        if ($prefix === '') {
            return '';
        }

        if (! str_ends_with($prefix, '-')) {
            $prefix .= '-';
        }

        return $prefix;
    }
}
