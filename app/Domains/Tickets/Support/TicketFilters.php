<?php

namespace App\Domains\Tickets\Support;

class TicketFilters
{
    public const KEYS = [
        'status_id',
        'priority_id',
        'assigned_to',
        'unassigned',
        'mine',
        'channel_id',
        'department_id',
        'team_id',
        'search',
        'contact',
        'created_from',
        'created_to',
        'watching',
        'type',
    ];

    public static function normalize(array $filters): array
    {
        $normalized = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];

            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            $normalized[$key] = match ($key) {
                'status_id', 'priority_id', 'assigned_to', 'channel_id', 'department_id', 'team_id' => (int) $value,
                'unassigned', 'mine', 'watching' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'search', 'contact', 'created_from', 'created_to' => trim((string) $value),
                'type' => (string) $value,
                default => $value,
            };
        }

        if (($normalized['unassigned'] ?? false) && isset($normalized['assigned_to'])) {
            unset($normalized['assigned_to']);
        }

        if (($normalized['mine'] ?? false) && ! isset($normalized['assigned_to'])) {
            unset($normalized['assigned_to']);
        }

        return $normalized;
    }

    public static function rules(): array
    {
        return [
            'status_id' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'],
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'watching' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'in:incident,service_request,change,problem'],
        ];
    }
}
