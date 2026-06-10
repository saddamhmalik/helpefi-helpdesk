<?php

namespace App\Domains\Auth\Services;

use App\Domains\Sla\Repositories\BusinessHoursRepository;
use App\Models\User;
use App\Support\LocaleSupport;

class UserPreferenceService
{
    public function __construct(private BusinessHoursRepository $businessHours)
    {
    }

    public function locale(User $user): string
    {
        return LocaleSupport::resolve($user->locale);
    }

    public function timezone(User $user): string
    {
        if ($user->timezone) {
            return $user->timezone;
        }

        return $this->businessHours->default()?->timezone
            ?? config('app.timezone');
    }

    public function isRtl(User $user): bool
    {
        return LocaleSupport::isRtl($this->locale($user));
    }
}
