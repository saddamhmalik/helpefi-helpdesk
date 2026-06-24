<?php

namespace App\Domains\Tickets\Support;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public static function applyToQueueQuery(
        Builder $query,
        array $filters,
        ?int $watchingUserId = null,
        bool $searchContacts = true,
    ): Builder {
        $filters = self::normalize($filters);

        if (! empty($filters['status_id'])) {
            $query->where('ticket_status_id', $filters['status_id']);
        }

        if (! empty($filters['priority_id'])) {
            $query->where('ticket_priority_id', $filters['priority_id']);
        }

        if (! empty($filters['mine']) && $watchingUserId) {
            $query->where('assigned_to', $watchingUserId);
        } elseif (! empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        } elseif (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from'].' 00:00:00');
        }

        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to'].' 23:59:59');
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $searchContacts) {
                if (strlen($search) >= 3 && self::supportsFulltextSearch()) {
                    $q->whereFullText(['subject', 'number'], $search);
                } else {
                    $q->where('subject', 'like', "{$search}%")
                        ->orWhere('number', 'like', "{$search}%");
                }

                if ($searchContacts) {
                    $contactIds = self::matchingContactIds($search);

                    if ($contactIds->isNotEmpty()) {
                        $q->orWhereIn('contact_id', $contactIds);
                    }
                }
            });
        }

        if (! empty($filters['contact'])) {
            $contactIds = self::matchingContactIds($filters['contact']);

            if ($contactIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('contact_id', $contactIds);
            }
        }

        if (! empty($filters['watching']) && $watchingUserId) {
            $query->whereIn('id', function ($subquery) use ($watchingUserId) {
                $subquery->select('ticket_id')
                    ->from('ticket_watchers')
                    ->where('user_id', $watchingUserId);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query;
    }

    private static function supportsFulltextSearch(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private static function matchingContactIds(string $search): Collection
    {
        if (strlen($search) < 2) {
            return collect();
        }

        return Contact::query()
            ->where(function ($query) use ($search) {
                $query->where('email', 'like', "{$search}%")
                    ->orWhere('name', 'like', "{$search}%");
            })
            ->limit(50)
            ->pluck('id');
    }
}
