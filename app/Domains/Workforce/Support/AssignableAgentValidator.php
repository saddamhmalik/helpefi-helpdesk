<?php

namespace App\Domains\Workforce\Support;

use App\Domains\Workforce\Services\WorkforceService;
use Illuminate\Validation\ValidationException;

class AssignableAgentValidator
{
    public function __construct(private WorkforceService $workforce)
    {
    }

    public function assert(array $userIds, string $field = 'user_ids'): void
    {
        $allowed = array_flip($this->workforce->assignableAgentIds());

        foreach ($userIds as $userId) {
            if (! isset($allowed[(int) $userId])) {
                throw ValidationException::withMessages([
                    $field => 'One or more selected users are not assignable agents.',
                ]);
            }
        }
    }

    public function filter(array $userIds): array
    {
        $allowed = array_flip($this->workforce->assignableAgentIds());

        return array_values(array_filter(
            array_map('intval', $userIds),
            fn (int $userId) => isset($allowed[$userId]),
        ));
    }
}
