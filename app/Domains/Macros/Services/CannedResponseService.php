<?php

namespace App\Domains\Macros\Services;

use App\Domains\Macros\Models\CannedResponse;
use App\Domains\Macros\Repositories\CannedResponseRepository;
use App\Domains\Macros\Support\MacroPlaceholderResolver;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class CannedResponseService
{
    public function __construct(
        private CannedResponseRepository $responses,
        private MacroPlaceholderResolver $placeholders,
        private TicketRepository $tickets,
        private AuditRecorder $audit,
    ) {
    }

    public function listForUser(int $userId): Collection
    {
        return $this->responses->forUser($userId);
    }

    public function search(int $userId, ?string $term): Collection
    {
        return $this->responses->search($userId, $term ? trim($term) : null);
    }

    public function create(array $data, int $userId): CannedResponse
    {
        $data = $this->normalize($data, $userId);
        $response = $this->responses->create($data);

        $this->audit->record('canned_response.created', $response, [
            'title' => $response->title,
        ], $userId);

        return $response;
    }

    public function update(int $id, array $data, int $userId): CannedResponse
    {
        $response = $this->responses->find($id);
        $this->assertCanManage($response, $userId);

        $data = $this->normalize($data, $userId, $response);
        $updated = $this->responses->update($response, $data);

        $this->audit->record('canned_response.updated', $updated, [
            'title' => $updated->title,
        ], $userId);

        return $updated;
    }

    public function delete(int $id, int $userId): void
    {
        $response = $this->responses->find($id);
        $this->assertCanManage($response, $userId);

        $this->responses->delete($response);

        $this->audit->record('canned_response.deleted', $response, [
            'title' => $response->title,
        ], $userId);
    }

    public function apply(int $responseId, int $userId, ?int $ticketId = null): string
    {
        $response = $this->responses->find($responseId);
        $this->assertCanUse($response, $userId);

        $ticket = $ticketId ? $this->tickets->find($ticketId) : null;
        $agent = User::query()->findOrFail($userId);

        if ($ticket) {
            $ticket->loadMissing(['contact.organization']);
        }

        return $this->placeholders->resolve($response->body, $ticket, $agent);
    }

    public function placeholderHelp(): array
    {
        return $this->placeholders->placeholders();
    }

    private function normalize(array $data, int $userId, ?CannedResponse $existing = null): array
    {
        if (array_key_exists('shortcut', $data)) {
            $shortcut = trim((string) ($data['shortcut'] ?? ''));

            if ($shortcut === '') {
                $data['shortcut'] = null;
            } else {
                $data['shortcut'] = strtolower(ltrim($shortcut, '#'));
            }
        }

        if (! ($data['is_shared'] ?? false) || ! $existing?->is_shared) {
            $data['user_id'] = $userId;
        } elseif ($existing) {
            unset($data['user_id']);
        } else {
            $data['user_id'] = $userId;
        }

        if (trim($data['body'] ?? '') === '') {
            throw new InvalidArgumentException('Macro body is required.');
        }

        return $data;
    }

    private function assertCanManage(CannedResponse $response, int $userId): void
    {
        if ($response->is_shared) {
            return;
        }

        if ($response->user_id !== $userId) {
            throw new InvalidArgumentException('You cannot edit this macro.');
        }
    }

    private function assertCanUse(CannedResponse $response, int $userId): void
    {
        if ($response->is_shared || $response->user_id === $userId) {
            return;
        }

        throw new InvalidArgumentException('Macro not found.');
    }
}
