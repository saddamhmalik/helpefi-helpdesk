<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Jobs\DeliverPlatformNoticeJob;
use App\Domains\Platform\Models\PlatformNotice;
use App\Domains\Platform\Repositories\PlatformNoticeRepository;
use App\Domains\Platform\Repositories\PlatformTenantRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Platform\Support\PlatformNoticeUrlGenerator;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlatformNoticeService
{
    public function __construct(
        private PlatformNoticeRepository $notices,
        private PlatformTenantRepository $tenants,
        private PlatformNoticeUrlGenerator $urls,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function list(): array
    {
        return $this->notices->all()
            ->map(fn (PlatformNotice $notice) => $this->present($notice))
            ->all();
    }

    public function find(int $id): array
    {
        return $this->present($this->notices->find($id));
    }

    public function tenantOptions(): array
    {
        return $this->tenants->options();
    }

    public function create(array $data, ?UploadedFile $image = null): array
    {
        $payload = $this->buildPayload($data);
        $payload['created_by'] = Auth::guard('platform')->id();
        $payload['status'] = PlatformNotice::STATUS_DRAFT;

        if ($image) {
            $payload = array_merge($payload, $this->storeImage($image));
        }

        $notice = $this->notices->create($payload);

        $this->audit->record('platform.notice.created', $notice);

        return $this->present($notice);
    }

    public function update(int $id, array $data, ?UploadedFile $image = null, bool $removeImage = false): array
    {
        $notice = $this->notices->find($id);
        $before = $this->present($notice);
        $payload = $this->buildPayload($data, $notice);

        if ($removeImage) {
            $this->deleteImage($notice);
            $payload['image_path'] = null;
        }

        if ($image) {
            $this->deleteImage($notice);
            $payload = array_merge($payload, $this->storeImage($image));
        }

        $notice = $this->notices->update($notice, $payload);

        $this->audit->recordChanges('platform.notice.updated', $notice, $before, $this->present($notice));

        return $this->present($notice);
    }

    public function publish(int $id): array
    {
        $notice = $this->notices->find($id);

        if ($notice->status === PlatformNotice::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'This notice is already published.',
            ]);
        }

        $this->validateTargeting($notice->target_scope, $notice->tenant_ids ?? []);

        $notice = $this->notices->update($notice, [
            'status' => PlatformNotice::STATUS_PUBLISHED,
            'published_at' => now(),
            'is_active' => true,
        ]);

        $this->scheduleDelivery($notice);

        $this->audit->record('platform.notice.published', $notice, [
            'target_scope' => $notice->target_scope,
            'tenant_ids' => $notice->tenant_ids,
            'audience' => $notice->audience,
        ]);

        return $this->present($notice);
    }

    public function deactivate(int $id): array
    {
        $notice = $this->notices->find($id);
        $notice = $this->notices->update($notice, ['is_active' => false]);

        $this->audit->record('platform.notice.deactivated', $notice);

        return $this->present($notice);
    }

    public function delete(int $id): void
    {
        $notice = $this->notices->find($id);
        $this->deleteImage($notice);
        $this->notices->delete($notice);

        $this->audit->record('platform.notice.deleted', null, [
            'title' => $notice->title,
            'id' => $notice->id,
        ]);
    }

    public function imageResponse(int $id)
    {
        $notice = $this->notices->find($id);

        if (! $notice->image_path || ! Storage::disk($notice->image_disk)->exists($notice->image_path)) {
            abort(404);
        }

        return Storage::disk($notice->image_disk)->response($notice->image_path);
    }

    public function validationRules(?PlatformNotice $existing = null): array
    {
        $imageMimes = implode(',', config('platform_notices.image.mimes', ['jpg', 'jpeg', 'png']));

        return [
            'title' => ['required', 'string', 'max:200'],
            'body_html' => ['nullable', 'string', 'max:50000'],
            'notice_type' => ['required', 'string', 'in:'.implode(',', array_keys(config('platform_notices.types', [])))],
            'target_scope' => ['required', 'string', 'in:all,selected'],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['string', 'max:255'],
            'audience' => ['required', 'string', 'in:admins,all_agents'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'dismissible' => ['sometimes', 'boolean'],
            'priority' => ['required', 'string', 'in:low,normal,high'],
            'image' => ['nullable', 'file', 'mimes:'.$imageMimes, 'max:'.config('platform_notices.image.max_kb', 5120)],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    private function buildPayload(array $data, ?PlatformNotice $existing = null): array
    {
        $targetScope = $data['target_scope'] ?? PlatformNotice::TARGET_ALL;
        $tenantIds = $targetScope === PlatformNotice::TARGET_SELECTED
            ? array_values(array_unique($data['tenant_ids'] ?? []))
            : null;

        $this->validateTargeting($targetScope, $tenantIds ?? []);

        return [
            'title' => $data['title'],
            'body_html' => isset($data['body_html']) && $data['body_html'] !== null && $data['body_html'] !== ''
                ? MessageBodySanitizer::sanitize($data['body_html'])
                : null,
            'notice_type' => $data['notice_type'],
            'target_scope' => $targetScope,
            'tenant_ids' => $tenantIds,
            'audience' => $data['audience'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'dismissible' => (bool) ($data['dismissible'] ?? true),
            'priority' => $data['priority'] ?? PlatformNotice::PRIORITY_NORMAL,
        ];
    }

    private function validateTargeting(string $targetScope, array $tenantIds): void
    {
        if ($targetScope !== PlatformNotice::TARGET_SELECTED) {
            return;
        }

        if ($tenantIds === []) {
            throw ValidationException::withMessages([
                'tenant_ids' => 'Select at least one workspace.',
            ]);
        }

        $existing = $this->tenants->findMany($tenantIds)->pluck('id')->all();
        $missing = array_diff($tenantIds, $existing);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'tenant_ids' => 'One or more selected workspaces could not be found.',
            ]);
        }
    }

    private function storeImage(UploadedFile $file): array
    {
        $disk = config('platform_notices.image.disk', 'local');
        $directory = trim(config('platform_notices.image.directory', 'platform-notices'), '/');
        $path = $file->store($directory, $disk);

        return [
            'image_path' => $path,
            'image_disk' => $disk,
        ];
    }

    private function scheduleDelivery(PlatformNotice $notice): void
    {
        if ($notice->starts_at && $notice->starts_at->isFuture()) {
            DeliverPlatformNoticeJob::dispatch($notice->id)->delay($notice->starts_at);

            return;
        }

        DeliverPlatformNoticeJob::dispatch($notice->id);
    }

    private function scheduleState(PlatformNotice $notice): string
    {
        if ($notice->status !== PlatformNotice::STATUS_PUBLISHED || ! $notice->is_active) {
            return 'inactive';
        }

        if ($notice->starts_at && $notice->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($notice->ends_at && $notice->ends_at->isPast()) {
            return 'expired';
        }

        return 'live';
    }

    private function deleteImage(PlatformNotice $notice): void
    {
        if (! $notice->image_path) {
            return;
        }

        Storage::disk($notice->image_disk)->delete($notice->image_path);
    }

    private function present(PlatformNotice $notice): array
    {
        $tenantNames = [];

        if ($notice->target_scope === PlatformNotice::TARGET_SELECTED && $notice->tenant_ids) {
            $tenantNames = $this->tenants->findMany($notice->tenant_ids)
                ->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ])
                ->values()
                ->all();
        }

        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body_html' => $notice->body_html,
            'notice_type' => $notice->notice_type,
            'target_scope' => $notice->target_scope,
            'tenant_ids' => $notice->tenant_ids ?? [],
            'tenants' => $tenantNames,
            'audience' => $notice->audience,
            'starts_at' => $notice->starts_at?->toIso8601String(),
            'ends_at' => $notice->ends_at?->toIso8601String(),
            'dismissible' => $notice->dismissible,
            'priority' => $notice->priority,
            'status' => $notice->status,
            'is_active' => $notice->is_active,
            'is_live' => $notice->isCurrentlyActive(),
            'schedule_state' => $this->scheduleState($notice),
            'published_at' => $notice->published_at?->toIso8601String(),
            'image_url' => $this->urls->imageUrl($notice),
            'has_image' => (bool) $notice->image_path,
            'created_by' => $notice->creator ? [
                'id' => $notice->creator->id,
                'name' => $notice->creator->name,
            ] : null,
            'updated_at' => $notice->updated_at?->toIso8601String(),
        ];
    }
}
