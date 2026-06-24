<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class MarketingHomeContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function content(): array
    {
        $content = config('marketing_home_content', []);

        if (! is_array($content)) {
            return [];
        }

        return $this->interpolator->interpolate($content);
    }
}
