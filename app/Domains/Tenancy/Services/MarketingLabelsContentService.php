<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class MarketingLabelsContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function labels(array $extra = []): array
    {
        $content = config('marketing_labels_content', []);

        if (! is_array($content)) {
            return [];
        }

        return $this->interpolator->with($extra)->interpolate($content);
    }
}
