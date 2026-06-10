<?php

namespace App\Domains\Settings\Repositories;

use App\Domains\Settings\Models\HelpdeskSetting;

class HelpdeskSettingRepository
{
    public function current(): HelpdeskSetting
    {
        return HelpdeskSetting::query()->firstOrCreate([], [
            'ticket_number_prefix' => 'HD-',
            'contact_fields' => [],
            'ticket_fields' => [],
            'user_fields' => [],
            'auto_first_response_enabled' => false,
            'auto_first_response_body' => null,
            'email_blocklist' => [],
            'kb_deflection_enabled' => true,
            'kb_locales' => ['en'],
            'kb_default_locale' => 'en',
        ]);
    }

    public function update(HelpdeskSetting $setting, array $data): HelpdeskSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
