<?php

namespace App\Domains\Channels\Repositories;

use App\Domains\Channels\Models\MessagingSetting;
use Illuminate\Support\Str;

class MessagingSettingRepository
{
    public function current(): MessagingSetting
    {
        return MessagingSetting::query()->firstOrCreate([], [
            'webhook_token' => Str::random(32),
        ]);
    }

    public function update(MessagingSetting $setting, array $data): MessagingSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
