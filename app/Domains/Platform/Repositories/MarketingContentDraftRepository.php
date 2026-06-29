<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingContentDraft;
use Illuminate\Database\Eloquent\Collection;

class MarketingContentDraftRepository
{
    public function allForAdmin(): Collection
    {
        return MarketingContentDraft::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function find(int $id): MarketingContentDraft
    {
        return MarketingContentDraft::query()
            ->with(['creator:id,name', 'updater:id,name', 'publishedPage'])
            ->findOrFail($id);
    }

    public function create(array $data): MarketingContentDraft
    {
        return MarketingContentDraft::query()->create($data);
    }

    public function update(MarketingContentDraft $draft, array $data): MarketingContentDraft
    {
        $draft->update($data);

        return $draft->fresh(['creator:id,name', 'updater:id,name', 'publishedPage']);
    }

    public function delete(MarketingContentDraft $draft): void
    {
        $draft->delete();
    }

    public function fingerprints(): array
    {
        return MarketingContentDraft::query()
            ->whereNotNull('content_fingerprint')
            ->whereIn('status', [
                MarketingContentDraft::STATUS_DRAFT,
                MarketingContentDraft::STATUS_READY,
                MarketingContentDraft::STATUS_PUBLISHED,
            ])
            ->pluck('content_fingerprint', 'id')
            ->all();
    }

    public function textCorpus(): array
    {
        return MarketingContentDraft::query()
            ->whereIn('status', [
                MarketingContentDraft::STATUS_DRAFT,
                MarketingContentDraft::STATUS_READY,
                MarketingContentDraft::STATUS_PUBLISHED,
            ])
            ->get(['id', 'title', 'generated_content', 'edited_content'])
            ->map(fn (MarketingContentDraft $draft) => [
                'id' => 'draft:'.$draft->id,
                'title' => $draft->title,
                'text' => $this->extractText($draft->effectiveContent(), $draft->title),
            ])
            ->all();
    }

    private function extractText(array $content, string $title): string
    {
        $parts = [$title];

        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_array($value)) {
                $parts[] = $this->flattenArray($value);
            }
        }

        return implode(' ', array_filter($parts));
    }

    private function flattenArray(array $items): string
    {
        $parts = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $parts[] = $item;
            } elseif (is_array($item)) {
                foreach ($item as $v) {
                    if (is_string($v)) {
                        $parts[] = $v;
                    }
                }
            }
        }

        return implode(' ', $parts);
    }
}
