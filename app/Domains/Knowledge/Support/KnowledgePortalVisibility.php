<?php

namespace App\Domains\Knowledge\Support;

use Illuminate\Database\Eloquent\Builder;

final class KnowledgePortalVisibility
{
    public static function applyPublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public static function applyPortal(Builder $query): void
    {
        $query
            ->where('is_published', true)
            ->where('is_public', true)
            ->where(function (Builder $query) {
                $query
                    ->where(function (Builder $query) {
                        $query
                            ->where('is_system', false)
                            ->whereHas('collection', fn (Builder $collection) => $collection->where('is_public', true));
                    })
                    ->orWhere('is_system', true);
            });
    }
}
