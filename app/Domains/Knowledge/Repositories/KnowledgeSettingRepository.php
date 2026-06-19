<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;

class KnowledgeSettingRepository
{
    public function __construct(private HelpdeskSettingRepository $settings)
    {
    }

    public function current(): HelpdeskSetting
    {
        return $this->settings->current();
    }

    public function update(HelpdeskSetting $setting, array $data): HelpdeskSetting
    {
        return $this->settings->update($setting, $data);
    }
}
