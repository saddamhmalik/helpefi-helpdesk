<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Services\OutboundMailService;
use Database\Seeders\EmailSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

class OutboundMailerResolutionTest extends TenantTestCase
{
    public function test_resolve_mailer_name_clears_stale_tenant_mailer_when_disabled(): void
    {
        $this->seed(EmailSeeder::class);

        $setting = MailSetting::query()->firstOrFail();
        $setting->update([
            'enabled' => true,
            'driver' => 'smtp',
            'from_address' => 'noreply@helpdesk.test',
            'host' => 'smtp.mailtrap.io',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'secret',
        ]);

        $outbound = app(OutboundMailService::class);
        $this->assertSame(OutboundMailService::MAILER, $outbound->resolveMailerName());
        $this->assertArrayHasKey(OutboundMailService::MAILER, config('mail.mailers'));

        $setting->update(['enabled' => false]);

        $this->assertSame(config('mail.bootstrap_default', 'log'), $outbound->resolveMailerName());
        $this->assertArrayNotHasKey(OutboundMailService::MAILER, config('mail.mailers'));
        $this->assertSame(config('mail.bootstrap_default', 'log'), config('mail.default'));
    }

    public function test_resolve_mailer_name_clears_stale_tenant_mailer_when_invalid(): void
    {
        $this->seed(EmailSeeder::class);

        $setting = MailSetting::query()->firstOrFail();
        $setting->update([
            'enabled' => true,
            'driver' => 'smtp',
            'from_address' => 'noreply@helpdesk.test',
            'host' => 'smtp.mailtrap.io',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'secret',
        ]);

        $outbound = app(OutboundMailService::class);
        $outbound->resolveMailerName();

        $setting->update(['host' => null]);

        Config::set('mail.default', OutboundMailService::MAILER);

        $this->assertSame(config('mail.bootstrap_default', 'log'), $outbound->resolveMailerName());
        $this->assertArrayNotHasKey(OutboundMailService::MAILER, config('mail.mailers'));
    }

    public function test_resolve_mailer_name_falls_back_when_mail_settings_table_missing(): void
    {
        Schema::drop('mail_settings');

        $outbound = app(OutboundMailService::class);

        $this->assertSame(config('mail.bootstrap_default', 'log'), $outbound->resolveMailerName());
        $this->assertArrayNotHasKey(OutboundMailService::MAILER, config('mail.mailers'));
    }
}
