<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\ServiceDesk\Models\ApprovalRequestStep;
use App\Domains\ServiceDesk\Models\ServiceDeskSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApprovalRequestRepository
{
    public function find(int $id): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->with([
                'ticket.contact:id,name,email',
                'catalogItem:id,name',
                'requestedBy:id,name,email',
                'steps.approver:id,name,email',
            ])
            ->findOrFail($id);
    }

    public function findForTicket(int $ticketId): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->with(['steps.approver:id,name,email'])
            ->where('ticket_id', $ticketId)
            ->latest('id')
            ->first();
    }

    public function create(array $data, array $approverUserIds): ApprovalRequest
    {
        $request = ApprovalRequest::query()->create($data);

        foreach (array_values($approverUserIds) as $index => $approverUserId) {
            ApprovalRequestStep::query()->create([
                'approval_request_id' => $request->id,
                'step_order' => $index + 1,
                'approver_user_id' => $approverUserId,
                'status' => ApprovalRequestStep::STATUS_PENDING,
            ]);
        }

        return $this->find($request->id);
    }

    public function update(ApprovalRequest $request, array $data): ApprovalRequest
    {
        $request->update($data);

        return $this->find($request->id);
    }

    public function updateStep(ApprovalRequestStep $step, array $data): ApprovalRequestStep
    {
        $step->update($data);

        return $step->fresh(['approver:id,name,email']);
    }

    public function currentStep(ApprovalRequest $request): ?ApprovalRequestStep
    {
        return $request->steps->firstWhere('step_order', $request->current_step);
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->with([
                'ticket:id,number,subject,contact_id',
                'ticket.contact:id,name,email',
                'catalogItem:id,name',
                'requestedBy:id,name,email',
                'steps.approver:id,name,email',
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['mine'])) {
            $userId = (int) ($filters['user_id'] ?? 0);
            $query->where('status', ApprovalRequest::STATUS_PENDING)
                ->whereHas('steps', function ($stepQuery) use ($userId) {
                    $stepQuery->where('approver_user_id', $userId)
                        ->where('status', ApprovalRequestStep::STATUS_PENDING)
                        ->whereColumn('approval_request_steps.step_order', 'approval_requests.current_step');
                });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function pendingCountForApprover(int $userId): int
    {
        return $this->pendingCounts($userId)['pending_mine'];
    }

    public function pendingCount(): int
    {
        return $this->pendingCounts(0)['pending'];
    }

    public function pendingCounts(int $userId): array
    {
        $row = ApprovalRequest::query()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->selectRaw('COUNT(*) as pending')
            ->selectRaw('SUM(CASE WHEN EXISTS (
                SELECT 1 FROM approval_request_steps
                WHERE approval_request_steps.approval_request_id = approval_requests.id
                AND approval_request_steps.approver_user_id = ?
                AND approval_request_steps.status = ?
                AND approval_request_steps.step_order = approval_requests.current_step
            ) THEN 1 ELSE 0 END) as pending_mine', [$userId, ApprovalRequestStep::STATUS_PENDING])
            ->first();

        return [
            'pending' => (int) ($row->pending ?? 0),
            'pending_mine' => (int) ($row->pending_mine ?? 0),
        ];
    }

    public function settings(): ServiceDeskSetting
    {
        return ServiceDeskSetting::query()->firstOrCreate([]);
    }

    public function updateSettings(ServiceDeskSetting $settings, array $data): ServiceDeskSetting
    {
        $settings->update($data);

        return $settings->fresh();
    }
}
