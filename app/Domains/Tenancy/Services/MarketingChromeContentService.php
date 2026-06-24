<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class MarketingChromeContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function content(array $extra = []): array
    {
        $content = config('marketing_chrome_content', []);

        if (! is_array($content)) {
            return [];
        }

        return $this->interpolator->with($extra)->interpolate($content);
    }
}
