<?php

namespace App\Domains\Platform\Support;

use App\Domains\Platform\Models\PlatformNotice;
use Illuminate\Support\Facades\URL;

class PlatformNoticeUrlGenerator
{
    public function imageUrl(PlatformNotice $notice): ?string
    {
        if (! $notice->image_path) {
            return null;
        }

        $expiresAt = $notice->ends_at ?? now()->addMonths(6);
        $relative = URL::temporarySignedRoute(
            'central.notices.image',
            $expiresAt,
            ['notice' => $notice->id],
            absolute: false,
        );

        return $this->centralBaseUrl().$relative;
    }

    private function centralBaseUrl(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.config('tenancy.central_app_domain');
    }
}
