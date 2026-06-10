<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformFeedback;
use App\Domains\Platform\Repositories\PlatformFeedbackRepository;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformFeedbackService
{
    public function __construct(private PlatformFeedbackRepository $feedback)
    {
    }

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->feedback->paginate($filters, $perPage)
            ->through(fn (PlatformFeedback $item) => $this->present($item));
    }

    public function find(int $id): array
    {
        return $this->present($this->feedback->find($id));
    }

    public function summary(): array
    {
        return $this->feedback->summary();
    }

    public function submit(User $user, array $data, ?Request $request = null): PlatformFeedback
    {
        $request ??= request();
        $tenant = tenant();

        return $this->feedback->create([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'type' => $data['type'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => PlatformFeedback::STATUS_OPEN,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function updateStatus(int $id, string $status): PlatformFeedback
    {
        $feedback = $this->feedback->find($id);

        return $this->feedback->update($feedback, [
            'status' => $status,
        ]);
    }

    public function submissionRules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
                PlatformFeedback::TYPE_FEEDBACK,
                PlatformFeedback::TYPE_FEATURE_REQUEST,
            ])],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function statusRules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                PlatformFeedback::STATUS_OPEN,
                PlatformFeedback::STATUS_REVIEWED,
                PlatformFeedback::STATUS_CLOSED,
            ])],
        ];
    }

    private function present(PlatformFeedback $feedback): array
    {
        $feedback->loadMissing('tenant:id,name,slug');

        return [
            'id' => $feedback->id,
            'tenant_id' => $feedback->tenant_id,
            'tenant_name' => $feedback->tenant_name,
            'tenant' => $feedback->tenant ? [
                'id' => $feedback->tenant->id,
                'name' => $feedback->tenant->name,
                'slug' => $feedback->tenant->slug,
            ] : null,
            'user_id' => $feedback->user_id,
            'user_name' => $feedback->user_name,
            'user_email' => $feedback->user_email,
            'type' => $feedback->type,
            'subject' => $feedback->subject,
            'body' => $feedback->body,
            'status' => $feedback->status,
            'ip_address' => $feedback->ip_address,
            'user_agent' => $feedback->user_agent,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ];
    }
}
