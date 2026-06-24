<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Brands\Services\BrandService;
use App\Domains\Knowledge\Support\HelpCenterGuestCache;
use App\Domains\Knowledge\Repositories\KnowledgeRepository;
use Illuminate\Support\Facades\Cache;

class HelpCenterService
{
    public function __construct(
        private BrandService $brands,
        private KnowledgeRepository $knowledge,
    ) {
    }

    public function guestState(): ?array
    {
        if (! tenant('id')) {
            return null;
        }

        try {
            $brand = $this->brands->default();

            return [
                'brandSlug' => $brand->slug,
                'title' => $brand->portal_title ?: 'Help Center',
                'homeUrl' => route('portal.index', ['brand' => $brand->slug]),
                'searchUrl' => route('portal.search', ['brand' => $brand->slug]),
                'trackUrl' => route('portal.track', ['brand' => $brand->slug]),
                'loginUrl' => route('portal.login', ['brand' => $brand->slug]),
                'registerUrl' => route('portal.register', ['brand' => $brand->slug]),
                'submitUrl' => route('portal.submit', ['brand' => $brand->slug]),
                'articleCount' => $this->knowledge->publishedCount(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function cachedGuestState(): ?array
    {
        if (! tenant('id')) {
            return null;
        }

        if (app()->environment('testing')) {
            return $this->guestState();
        }

        return HelpCenterGuestCache::remember(300, fn () => $this->guestState());
    }
}
