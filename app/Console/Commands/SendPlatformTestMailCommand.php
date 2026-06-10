<?php

namespace App\Console\Commands;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPlatformTestMailCommand extends Command
{
    protected $signature = 'platform:send-test-mail {email : Recipient address}';

    protected $description = 'Send a test platform email and print mail configuration diagnostics';

    public function handle(): int
    {
        $recipient = (string) $this->argument('email');
        $mailer = (string) config('mail.default');
        $from = (string) config('platform_mail.from.address');

        $this->line('Mailer: '.$mailer);
        $this->line('From: '.$from.' ('.config('platform_mail.from.name').')');

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — messages are written to storage/logs/laravel.log only, not delivered.');
        }

        foreach ([PlatformEmailTemplate::SLUG_REGISTRATION, PlatformEmailTemplate::SLUG_WORKSPACE_WELCOME] as $slug) {
            $exists = PlatformEmailTemplate::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->exists();

            $this->line('Template '.$slug.': '.($exists ? 'active' : 'MISSING or inactive'));
        }

        if ($mailer === 'smtp') {
            $this->line('SMTP host: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        }

        $this->info('Sending test email to '.$recipient.'...');

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').' sent at '.now()->toDateTimeString().'.',
                function ($message) use ($recipient) {
                    $message->to($recipient)
                        ->subject('Test email from '.config('app.name'));
                },
            );
        } catch (\Throwable $exception) {
            $this->error('Send failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($mailer === 'log') {
            $this->warn('Check storage/logs/laravel.log for the message body.');
        } else {
            $this->info('Test email dispatched. Check the inbox (and spam folder).');
        }

        return self::SUCCESS;
    }
}
