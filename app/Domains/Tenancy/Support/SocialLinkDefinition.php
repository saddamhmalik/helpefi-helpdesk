<?php

namespace App\Domains\Tenancy\Support;

class SocialLinkDefinition
{
    public static function platforms(): array
    {
        return [
            'x' => ['label' => 'X (Twitter)', 'placeholder' => 'https://x.com/yourbrand'],
            'linkedin' => ['label' => 'LinkedIn', 'placeholder' => 'https://linkedin.com/company/yourbrand'],
            'facebook' => ['label' => 'Facebook', 'placeholder' => 'https://facebook.com/yourbrand'],
            'instagram' => ['label' => 'Instagram', 'placeholder' => 'https://instagram.com/yourbrand'],
            'youtube' => ['label' => 'YouTube', 'placeholder' => 'https://youtube.com/@yourbrand'],
            'github' => ['label' => 'GitHub', 'placeholder' => 'https://github.com/yourbrand'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::platforms());
    }
}
