<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Jobs\SendAutoFirstResponseJob;
use App\Domains\Channels\Jobs\SendSideConversationJob;
use App\Domains\Channels\Jobs\SendTicketReplyJob;
use App\Domains\Channels\Mail\AutoFirstResponseMail;
use App\Domains\Channels\Mail\SideConversationMail;
use App\Domains\Channels\Mail\TicketReplyMail;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Repositories\MailSettingRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Models\SideConversationMessage;
use App\Domains\SideConversations\Services\SideConversationThreadService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketCcService;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class OutboundMailService
{
    public const MAILER = 'helpdesk';

    public function __construct(
        private MailSettingRepository $settings,
        private OutboundSmtpResolver $smtpResolver,
        private EmailInboxService $inboxes,
        private AuditRecorder $audit,
        private TicketCcService $ticketCcs,
        private \App\Domains\Settings\Services\HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function applyGlobalConfig(): void
    {
        if (! Schema::hasTable('mail_settings')) {
            return;
        }

        $this->applyQueueConfig();

        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled) {
            return;
        }

        try {
            $this->registerMailer($setting);
        } catch (InvalidArgumentException) {
            return;
        }

        $config = $this->smtpResolver->resolve($setting);
        Config::set('mail.default', self::MAILER);
        Config::set('mail.from.address', $config['from_address'] ?? $setting->from_address);
        Config::set('mail.from.name', $config['from_name'] ?? $setting->from_name);
    }

    public function resolveMailerName(): string
    {
        $this->applyGlobalConfig();

        $setting = $this->settings->current();

        if ($setting->enabled && $this->mailerIsConfigured(self::MAILER)) {
            return self::MAILER;
        }

        return (string) config('mail.default', 'smtp');
    }

    private function mailerIsConfigured(string $name): bool
    {
        $mailers = config('mail.mailers', []);

        return is_array($mailers) && array_key_exists($name, $mailers);
    }

    public function sendTicketReply(Ticket $ticket, TicketMessage $message, User $agent): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        $ticket->loadMissing(['contact', 'emailInbox']);

        if (! $ticket->contact?->email) {
            return;
        }

        SendTicketReplyJob::dispatch($ticket->id, $message->id, $agent->id);
    }

    public function sendSideConversation(SideConversation $conversation, SideConversationMessage $message, User $agent): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        SendSideConversationJob::dispatch($conversation->id, $message->id, $agent->id);
    }

    public function deliverSideConversation(SideConversation $conversation, SideConversationMessage $message, User $agent): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        $conversation->loadMissing('ticket.emailInbox');
        $ticket = $conversation->ticket;

        if (! $ticket || ! $conversation->recipient_email) {
            return;
        }

        [$fromAddress, $fromName] = $this->resolveFrom($ticket, $setting);

        $replyMessageId = SideConversationThreadService::outboundMessageId($conversation->id, $message->id);
        $message->update(['external_id' => $replyMessageId]);

        $this->registerMailer($setting);

        $isReply = $conversation->messages()->whereKeyNot($message->id)->exists();

        try {
            Mail::mailer(self::MAILER)->to($conversation->recipient_email)->send(
                new SideConversationMail(
                    $ticket,
                    $conversation,
                    $message,
                    $agent,
                    $fromAddress,
                    $fromName,
                    $replyMessageId,
                    $isReply,
                ),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send side conversation email', $exception));
        }
    }

    public function deliverCsatSurvey(string $to, Ticket $ticket, string $surveyUrl, array $rateUrls): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled) {
            return;
        }

        $ticket->loadMissing('emailInbox');
        [$fromAddress, $fromName] = $this->resolveFrom($ticket, $setting);

        $this->registerMailer($setting);

        try {
            Mail::mailer(self::MAILER)->to($to)->send(
                new \App\Domains\Channels\Mail\CsatSurveyMail($ticket, $surveyUrl, $rateUrls, $fromAddress, $fromName),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send CSAT survey email', $exception));
        }
    }

    public function sendAutoFirstResponse(Ticket $ticket, TicketMessage $message, TicketMessage $customerMessage): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        $ticket->loadMissing(['contact', 'emailInbox']);

        if (! $ticket->contact?->email) {
            return;
        }

        SendAutoFirstResponseJob::dispatch($ticket->id, $message->id, $customerMessage->id);
    }

    public function deliverAutoFirstResponse(Ticket $ticket, TicketMessage $message, TicketMessage $customerMessage): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        $ticket->loadMissing(['contact', 'emailInbox']);

        if (! $ticket->contact?->email) {
            return;
        }

        [$fromAddress, $fromName] = $this->resolveFrom($ticket, $setting);

        $replyMessageId = EmailThreadService::outboundMessageId($ticket, $message->id);
        $message->update(['external_id' => $replyMessageId]);

        $this->registerMailer($setting);

        try {
            $mail = Mail::mailer(self::MAILER)->to($ticket->contact->email);
            $ccEmails = $this->ticketCcs->recipientsForTicket($ticket);

            if ($ccEmails !== []) {
                $mail->cc($ccEmails);
            }

            $mail->send(
                new AutoFirstResponseMail($ticket, $message, $customerMessage, $fromAddress, $fromName, $replyMessageId),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send auto first response email', $exception));
        }
    }

    public function deliverTicketReply(Ticket $ticket, TicketMessage $message, User $agent): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled || ! $setting->reply_enabled) {
            return;
        }

        $ticket->loadMissing(['contact', 'emailInbox']);

        if (! $ticket->contact?->email) {
            return;
        }

        [$fromAddress, $fromName] = $this->resolveFrom($ticket, $setting, $agent);

        $replyMessageId = EmailThreadService::outboundMessageId($ticket, $message->id);
        $message->update(['external_id' => $replyMessageId]);

        $this->registerMailer($setting);

        try {
            $mail = Mail::mailer(self::MAILER)->to($ticket->contact->email);
            $ccEmails = $this->ticketCcs->recipientsForTicket($ticket);

            if ($ccEmails !== []) {
                $mail->cc($ccEmails);
            }

            $this->applyOutboundExtras($mail, $setting);

            $mail->send(
                new TicketReplyMail(
                    $ticket,
                    $message,
                    $agent,
                    $fromAddress,
                    $fromName,
                    $replyMessageId,
                    $this->replyToAddress($setting, $fromAddress, $fromName),
                ),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send reply email', $exception));
        }
    }

    public function deliverTicketExport(Ticket $ticket, string $pdfContent, string $to, bool $includeConversation): void
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if (! $setting->enabled) {
            return;
        }

        [$fromAddress, $fromName] = $this->resolveFrom($ticket, $setting);

        $this->registerMailer($setting);

        try {
            Mail::mailer(self::MAILER)->to($to)->send(
                new \App\Domains\Tickets\Mail\TicketExportMail($ticket, $pdfContent, $fromAddress, $fromName, $includeConversation),
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send ticket export email', $exception));
        }
    }

    public function sendTest(string $to, array $overrides = []): void
    {
        $setting = $this->previewSetting($overrides);

        if (! $setting->enabled) {
            throw new InvalidArgumentException('Outbound email is not enabled.');
        }

        $config = $this->smtpResolver->resolve($setting);

        if (! ($config['from_address'] ?? null)) {
            throw new InvalidArgumentException('From address is required.');
        }

        $this->registerMailer($setting);

        try {
            Mail::mailer(self::MAILER)->raw(
                'This is a test email from your helpdesk.',
                function ($mail) use ($to, $config) {
                    $mail->to($to)
                        ->from($config['from_address'], $config['from_name'] ?? config('app.name'))
                        ->subject('helpefi test email');
                },
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Failed to send test email', $exception));
        }
    }

    public function testInboxSmtp(int $inboxId, string $to, ?string $password = null): void
    {
        $inbox = $this->inboxes->find($inboxId);
        $setting = $this->settings->current();
        $setting->setRelation('emailInbox', $inbox);
        $setting->use_inbox_smtp = true;
        $setting->email_inbox_id = $inbox->id;
        $setting->enabled = true;
        $setting->driver = 'smtp';

        if ($password) {
            $setting->password = $this->smtpResolver->normalizePassword($password);
        }

        $config = $this->smtpResolver->resolveFromInbox($setting);
        $this->applyResolvedSmtpConfig($config);

        try {
            Mail::mailer(self::MAILER)->raw(
                'This is a test email sent using your inbound inbox SMTP settings.',
                function ($mail) use ($to, $config, $inbox) {
                    $mail->to($to)
                        ->from($config['from_address'], $inbox->name)
                        ->subject('helpefi inbox SMTP test');
                },
            );
        } catch (TransportExceptionInterface $exception) {
            throw new InvalidArgumentException($this->formatTransportError('Inbox SMTP test failed', $exception));
        }
    }

    public function settingsSnapshot(): array
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        return [
            'enabled' => $setting->enabled,
            'reply_enabled' => $setting->reply_enabled,
            'use_inbox_smtp' => (bool) $setting->use_inbox_smtp,
            'email_inbox_id' => $setting->email_inbox_id,
            'driver' => $setting->driver,
            'from_address' => $setting->from_address,
            'from_name' => $setting->from_name,
            'reply_to_address' => $setting->reply_to_address,
            'automatic_bcc' => $setting->automatic_bcc,
            'use_agent_name_in_from' => (bool) $setting->use_agent_name_in_from,
            'host' => $setting->host,
            'port' => $setting->port,
            'encryption' => $setting->encryption,
            'username' => $setting->username,
            'has_password' => (bool) $setting->password,
            'smtp_providers' => config('helpdesk.smtp_providers', []),
            'inbox_smtp_options' => $this->inboxSmtpOptions(),
        ];
    }

    public function updateSettings(array $data): array
    {
        $setting = $this->settings->current();

        $data['delivery_mode'] = MailSetting::DELIVERY_QUEUE;
        $data['queue_connection'] = MailSetting::QUEUE_REDIS;

        if ($data['enabled'] ?? false) {
            $this->validateEnabledSettings($data, $setting);
        }

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        } elseif (! empty($data['password'])) {
            $data['password'] = $this->smtpResolver->normalizePassword($data['password']);
        }

        if (! ($data['use_inbox_smtp'] ?? false)) {
            $data['email_inbox_id'] = null;
        }

        if (isset($data['host'])) {
            $data['host'] = $this->smtpResolver->normalizeHost(
                $data['host'],
                $data['from_address'] ?? $setting->from_address,
            );
        }

        $this->settings->update($setting, $data);

        $this->applyQueueConfig();

        $safeData = collect($data)->except(['password'])->all();
        $this->audit->record('email.outbound_updated', null, ['settings' => $safeData]);

        return $this->settingsSnapshot();
    }

    private function applyQueueConfig(): void
    {
        Config::set('queue.default', MailSetting::QUEUE_REDIS);
    }

    private function inboxSmtpOptions(): array
    {
        return $this->inboxes->list()
            ->map(fn (EmailInbox $inbox) => $this->smtpResolver->inboxSnapshot($inbox))
            ->values()
            ->all();
    }

    private function validateEnabledSettings(array $data, MailSetting $setting): void
    {
        if (($data['driver'] ?? $setting->driver) === 'log') {
            return;
        }

        if ($data['use_inbox_smtp'] ?? $setting->use_inbox_smtp) {
            $inboxId = $data['email_inbox_id'] ?? $setting->email_inbox_id;

            if (! $inboxId) {
                throw new InvalidArgumentException('Select an inbound inbox to use for outgoing email.');
            }

            $preview = $this->settings->current();
            $preview->fill($data);
            $preview->setRelation('emailInbox', $this->inboxes->find((int) $inboxId));

            if (! empty($data['password'])) {
                $preview->password = $this->smtpResolver->normalizePassword($data['password']);
            }

            $this->smtpResolver->resolveFromInbox($preview);

            return;
        }

        if (! ($data['from_address'] ?? $setting->from_address)) {
            throw new InvalidArgumentException('From address is required.');
        }

        if (! ($data['host'] ?? $setting->host)) {
            throw new InvalidArgumentException('SMTP host is required.');
        }

        if (! ($data['username'] ?? $setting->username)) {
            throw new InvalidArgumentException('SMTP username is required.');
        }

        $hasPassword = ($data['password'] ?? null) !== null && ($data['password'] ?? '') !== '';

        if (! $hasPassword && ! $setting->password) {
            throw new InvalidArgumentException('SMTP password is required.');
        }
    }

    private function resolveFrom(Ticket $ticket, MailSetting $setting, ?User $agent = null): array
    {
        if ($ticket->emailInbox?->address) {
            $fromAddress = $ticket->emailInbox->address;
            $fromName = $ticket->emailInbox->name;
        } else {
            try {
                $config = $this->smtpResolver->resolve($setting);
                $fromAddress = $config['from_address'] ?? $setting->from_address ?? config('mail.from.address');
                $fromName = $config['from_name'] ?? $setting->from_name ?? config('mail.from.name');
            } catch (InvalidArgumentException) {
                $fromAddress = $setting->from_address ?? config('mail.from.address');
                $fromName = $setting->from_name ?? config('mail.from.name');
            }
        }

        if (($setting->use_agent_name_in_from || $this->helpdeskSettings->emailUseAgentNameInFrom()) && $agent?->name) {
            $fromName = $agent->name;
        }

        return [$fromAddress, $fromName];
    }

    private function replyToAddress(MailSetting $setting, string $fromAddress, string $fromName): array
    {
        $replyTo = $setting->reply_to_address ?: $this->helpdeskSettings->emailReplyToAddress();

        if ($replyTo) {
            return [$replyTo, $fromName];
        }

        return [$fromAddress, $fromName];
    }

    private function applyOutboundExtras($mail, MailSetting $setting): void
    {
        $bcc = $setting->automatic_bcc ?: $this->helpdeskSettings->emailAutomaticBcc();

        if ($bcc) {
            $mail->bcc($bcc);
        }
    }

    private function registerMailer(MailSetting $setting): void
    {
        $setting->loadMissing('emailInbox');
        $config = $this->smtpResolver->resolve($setting);

        if (($config['driver'] ?? $setting->driver) === 'log') {
            Config::set('mail.mailers.'.self::MAILER, ['transport' => 'log']);

            return;
        }

        $this->applyResolvedSmtpConfig($config);
    }

    private function applyResolvedSmtpConfig(array $config): void
    {
        Config::set('mail.mailers.'.self::MAILER, [
            'transport' => 'smtp',
            'host' => $config['host'],
            'port' => $config['port'] ?? 587,
            'encryption' => $config['encryption'],
            'username' => $config['username'],
            'password' => $config['password'],
            'timeout' => null,
        ]);
    }

    private function previewSetting(array $overrides): MailSetting
    {
        $setting = $this->settings->current()->loadMissing('emailInbox');

        if ($overrides === []) {
            return $setting;
        }

        $preview = $setting->replicate();
        $preview->exists = $setting->exists;
        $preview->setRelation('emailInbox', $setting->emailInbox);

        foreach (['enabled', 'reply_enabled', 'use_inbox_smtp', 'email_inbox_id', 'driver', 'from_address', 'from_name', 'host', 'port', 'encryption', 'username'] as $field) {
            if (array_key_exists($field, $overrides) && $overrides[$field] !== null && $overrides[$field] !== '') {
                $preview->{$field} = $overrides[$field];
            }
        }

        if (! empty($overrides['password'])) {
            $preview->password = $this->smtpResolver->normalizePassword($overrides['password']);
        } else {
            $preview->password = $setting->password;
        }

        if ($preview->use_inbox_smtp && $preview->email_inbox_id) {
            $preview->setRelation('emailInbox', $this->inboxes->find((int) $preview->email_inbox_id));
        }

        return $preview;
    }

    private function formatTransportError(string $prefix, TransportExceptionInterface $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, '535') || str_contains($message, 'BadCredentials')) {
            return $prefix.': Gmail rejected the username or password. Use a Google App Password (not your normal Gmail password), with 2-Step Verification enabled. Create one at https://myaccount.google.com/apppasswords — paste the 16-character code with or without spaces. If inbound IMAP already works, enable "Use same address as inbound inbox" to reuse that app password.';
        }

        return $prefix.': '.$message;
    }
}
