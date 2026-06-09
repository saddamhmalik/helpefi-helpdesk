<?php

namespace App\Domains\Csat\Repositories;

use App\Domains\Csat\Models\CsatSetting;

class CsatSettingRepository
{
    public function current(): CsatSetting
    {
        return CsatSetting::query()->firstOrCreate([], [
            'enabled' => true,
            'comment_required' => false,
            'email_enabled' => false,
        ]);
    }

    public function update(CsatSetting $setting, array $data): CsatSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
