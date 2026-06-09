<?php

use App\Domains\Channels\Models\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Channel::query()
            ->where('type', Channel::TYPE_CHAT)
            ->get()
            ->each(function (Channel $channel): void {
                $settings = $channel->settings ?? [];

                if (filled($settings['widget_key'] ?? null)) {
                    return;
                }

                $settings['widget_key'] = Str::random(32);
                $channel->update(['settings' => $settings]);
            });
    }

    public function down(): void
    {
    }
};
