<?php

namespace App\Domains\Ai\Repositories;

use App\Domains\Ai\Models\AiSetting;

class AiSettingRepository
{
    public function current(): AiSetting
    {
        return AiSetting::query()->firstOrCreate([], [
            'enabled' => true,
            'model' => null,
            'deflection_enabled' => false,
            'deflection_portal_enabled' => true,
            'deflection_widget_enabled' => true,
            'triage_enabled' => false,
        ]);
    }

    public function update(AiSetting $setting, array $data): AiSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
