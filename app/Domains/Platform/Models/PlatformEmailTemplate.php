<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformEmailTemplate extends Model
{
    protected $connection = 'central';

    public const SLUG_REGISTRATION = 'registration_confirmation';

    public const SLUG_REGISTRATION_VERIFICATION = 'registration_verification';

    public const SLUG_WORKSPACE_WELCOME = 'workspace_welcome';

    public const SLUG_TRIAL_NURTURE_DAY_1 = 'trial_nurture_day_1';

    public const SLUG_TRIAL_NURTURE_DAY_3 = 'trial_nurture_day_3';

    public const SLUG_TRIAL_NURTURE_DAY_5 = 'trial_nurture_day_5';

    public const SLUG_TRIAL_NURTURE_DAY_7 = 'trial_nurture_day_7';

    public const SLUG_TRIAL_NURTURE_DAY_10 = 'trial_nurture_day_10';

    public const SLUG_TRIAL_NURTURE_DAY_12 = 'trial_nurture_day_12';

    public const SLUG_TRIAL_NURTURE_DAY_13 = 'trial_nurture_day_13';

    public const SLUG_SUBSCRIPTION_ENDING_7_DAYS = 'subscription_ending_7_days';

    public const SLUG_SUBSCRIPTION_ENDING_3_DAYS = 'subscription_ending_3_days';

    public const SLUG_SUBSCRIPTION_ENDING_1_DAY = 'subscription_ending_1_day';

    public const SLUG_SUBSCRIPTION_ENDING_FINAL = 'subscription_ending_final';

    public static function trialNurtureSlugs(): array
    {
        return [
            self::SLUG_TRIAL_NURTURE_DAY_1,
            self::SLUG_TRIAL_NURTURE_DAY_3,
            self::SLUG_TRIAL_NURTURE_DAY_5,
            self::SLUG_TRIAL_NURTURE_DAY_7,
            self::SLUG_TRIAL_NURTURE_DAY_10,
            self::SLUG_TRIAL_NURTURE_DAY_12,
            self::SLUG_TRIAL_NURTURE_DAY_13,
        ];
    }

    public static function subscriptionEndingSlugs(): array
    {
        return [
            self::SLUG_SUBSCRIPTION_ENDING_7_DAYS,
            self::SLUG_SUBSCRIPTION_ENDING_3_DAYS,
            self::SLUG_SUBSCRIPTION_ENDING_1_DAY,
            self::SLUG_SUBSCRIPTION_ENDING_FINAL,
        ];
    }

    public static function lifecycleReminderSlugs(): array
    {
        return [
            ...self::trialNurtureSlugs(),
            ...self::subscriptionEndingSlugs(),
        ];
    }

    public static function systemSlugs(): array
    {
        return [
            self::SLUG_REGISTRATION,
            self::SLUG_REGISTRATION_VERIFICATION,
            self::SLUG_WORKSPACE_WELCOME,
            ...self::lifecycleReminderSlugs(),
        ];
    }

    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
