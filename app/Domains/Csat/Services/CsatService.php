<?php

namespace App\Domains\Csat\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Csat\Repositories\CsatResponseRepository;
use App\Domains\Csat\Repositories\CsatSettingRepository;
use App\Domains\Performance\Services\PerformanceService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CsatService
{
    public function __construct(
        private CsatSettingRepository $settings,
        private CsatResponseRepository $responses,
        private PerformanceService $performance,
    ) {
    }

    public function settingsSnapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'enabled' => $setting->enabled,
            'comment_required' => $setting->comment_required,
            'email_enabled' => $setting->email_enabled,
        ];
    }

    public function updateSettings(array $data): array
    {
        $this->settings->update($this->settings->current(), [
            'enabled' => $data['enabled'] ?? true,
            'comment_required' => $data['comment_required'] ?? false,
            'email_enabled' => $data['email_enabled'] ?? false,
        ]);

        return $this->settingsSnapshot();
    }

    public function isEnabled(): bool
    {
        return $this->settings->current()->enabled;
    }

    public function promptForTicket(Ticket $ticket): array
    {
        $ticket->loadMissing(['status', 'csatResponse']);
        $setting = $this->settings->current();
        $existing = $ticket->csatResponse;

        return [
            'enabled' => $setting->enabled,
            'eligible' => $setting->enabled
                && $ticket->status?->is_closed
                && ! $ticket->merged_into_ticket_id,
            'comment_required' => $setting->comment_required,
            'submitted' => $existing ? [
                'rating' => $existing->rating,
                'comment' => $existing->comment,
                'created_at' => $existing->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function submit(
        Ticket $ticket,
        Contact $contact,
        int $rating,
        ?string $comment,
        string $channel = CsatResponse::CHANNEL_PORTAL,
    ): array {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'rating' => 'Satisfaction surveys are not available.',
            ]);
        }

        if (! $this->isEligible($ticket)) {
            throw ValidationException::withMessages([
                'rating' => 'This ticket is not eligible for feedback.',
            ]);
        }

        if ($ticket->contact_id !== $contact->id) {
            throw ValidationException::withMessages([
                'rating' => 'You cannot submit feedback for this ticket.',
            ]);
        }

        if ($this->responses->findForTicket($ticket->id)) {
            throw ValidationException::withMessages([
                'rating' => 'Feedback has already been submitted for this ticket.',
            ]);
        }

        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating must be between 1 and 5.',
            ]);
        }

        if ($this->settings->current()->comment_required
            && $channel === CsatResponse::CHANNEL_PORTAL
            && ! trim((string) $comment)) {
            throw ValidationException::withMessages([
                'comment' => 'Please leave a comment with your rating.',
            ]);
        }

        $response = $this->responses->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'rating' => $rating,
            'comment' => $comment ? trim($comment) : null,
            'channel' => $channel,
        ]);

        if ($ticket->assigned_to) {
            $this->performance->recordCsat($ticket->assigned_to, $ticket->id, $rating);
        }

        return [
            'rating' => $response->rating,
            'comment' => $response->comment,
            'created_at' => $response->created_at?->toIso8601String(),
        ];
    }

    public function report(array $filters, int $perPage = 50): array
    {
        $summary = $this->responses->summary($filters);

        return [
            'summary' => $summary,
            'rows' => $this->responses->paginateReport($filters, $perPage),
            'format' => 'csat',
        ];
    }

    public function exportRows(array $filters): array
    {
        return [
            'summary' => $this->responses->summary($filters),
            'rows' => $this->responses->reportRows($filters),
        ];
    }

    public function dashboardSummary(): array
    {
        $since = now()->subDays(30)->toDateString();

        return $this->responses->summary(['date_from' => $since]);
    }

    private function isEligible(Ticket $ticket): bool
    {
        return $this->isEnabled()
            && $ticket->status?->is_closed
            && ! $ticket->merged_into_ticket_id;
    }
}
