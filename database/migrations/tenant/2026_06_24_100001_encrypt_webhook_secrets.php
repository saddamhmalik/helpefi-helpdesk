<?php

use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Support\WebhookSecretCipher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Webhook::query()->each(function (Webhook $webhook): void {
            $secret = (string) $webhook->getRawOriginal('secret');

            if ($secret === '' || str_starts_with($secret, 'eyJpdiI6')) {
                return;
            }

            $webhook->forceFill([
                'secret' => WebhookSecretCipher::encrypt($secret),
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
    }
};
