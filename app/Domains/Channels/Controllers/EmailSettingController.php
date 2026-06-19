<?php

namespace App\Domains\Channels\Controllers;

use App\Domains\Channels\Services\EmailInboxService;
use App\Domains\Channels\Services\EmailSettingsPageService;
use App\Domains\Channels\Services\InboundMailboxPollService;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingController extends Controller
{
    public function __construct(
        private EmailInboxService $inboxes,
        private OutboundMailService $mail,
        private InboundMailboxPollService $pollService,
        private EmailSettingsPageService $emailPage,
        private TicketFormReferenceService $ticketReferenceData,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Email', array_merge(
            $this->emailPage->staticPayload(),
            $this->ticketReferenceData->only(['departments', 'teams']),
            [
                'inboxes' => $this->inboxes->listForSettings(),
                'outbound' => $this->mail->settingsSnapshot(),
            ],
        ));
    }

    public function updateAdvanced(Request $request): RedirectResponse
    {
        $data = $request->validate($this->helpdeskSettings->emailAdvancedValidationRules());
        $this->helpdeskSettings->updateEmailAdvanced($data);

        return back()->with('success', 'Email policies saved.');
    }

    public function storeInbox(Request $request): RedirectResponse
    {
        $data = $request->validate($this->inboxValidationRules(requireInboundMethod: false));

        $this->inboxes->assertUniqueAddress($data['address']);
        $created = $this->inboxes->create($data);

        return back()->with([
            'success' => 'Email inbox added.',
            'created_inbox_id' => $created['id'],
        ]);
    }

    public function updateInbox(Request $request, int $inbox): RedirectResponse
    {
        $data = $request->validate($this->inboxValidationRules(requireInboundMethod: true));

        $this->inboxes->assertUniqueAddress($data['address'], $inbox);
        $this->inboxes->update($inbox, $data);

        return back()->with('success', 'Email inbox saved.');
    }

    public function destroyInbox(int $inbox): RedirectResponse
    {
        $this->inboxes->delete($inbox);

        return back()->with('success', 'Email inbox removed.');
    }

    public function regenerateInboxToken(int $inbox): RedirectResponse
    {
        $this->inboxes->regenerateToken($inbox);

        return back()->with('success', 'Inbound token regenerated.');
    }

    public function testInboxMailbox(int $inbox): RedirectResponse
    {
        $model = $this->inboxes->find($inbox);

        try {
            $this->pollService->testConnection($model);
            $model->update(['poll_error' => null]);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['mailbox' => $exception->getMessage()]);
        }

        return back()->with('success', 'Mailbox connection successful.');
    }

    public function pollInboxMailbox(int $inbox): RedirectResponse
    {
        try {
            $stats = $this->pollService->pollInbox($this->inboxes->find($inbox));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['mailbox' => $exception->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Polled mailbox: %d fetched, %d created, %d replies, %d duplicates, %d failed.',
            $stats['fetched'],
            $stats['created'],
            $stats['reply'],
            $stats['duplicate'],
            $stats['failed'],
        ));
    }

    public function updateOutbound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reply_enabled' => ['required', 'boolean'],
            'use_inbox_smtp' => ['required', 'boolean'],
            'email_inbox_id' => ['nullable', 'integer', 'exists:email_inboxes,id'],
            'driver' => ['required', 'in:smtp,log'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email', 'max:255'],
            'automatic_bcc' => ['nullable', 'email', 'max:255'],
            'use_agent_name_in_from' => ['boolean'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl,null'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['encryption'] ?? null) === 'null') {
            $data['encryption'] = null;
        }

        try {
            $this->mail->updateSettings($data);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['outbound' => $exception->getMessage()]);
        }

        return back()->with('success', 'Outbound email settings saved.');
    }

    public function testOutbound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'enabled' => ['nullable', 'boolean'],
            'reply_enabled' => ['nullable', 'boolean'],
            'use_inbox_smtp' => ['nullable', 'boolean'],
            'email_inbox_id' => ['nullable', 'integer', 'exists:email_inboxes,id'],
            'driver' => ['nullable', 'in:smtp,log'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl,null'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $to = $data['to'];
        unset($data['to']);

        if (($data['encryption'] ?? null) === 'null') {
            $data['encryption'] = null;
        }

        if (isset($data['host'])) {
            $data['host'] = app(\App\Domains\Channels\Services\OutboundSmtpResolver::class)->normalizeHost(
                $data['host'],
                $data['from_address'] ?? null,
            );
        }

        try {
            $this->mail->sendTest($to, $data);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['to' => $exception->getMessage()]);
        }

        return back()->with('success', 'Test email sent.');
    }

    public function testInboxOutbound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_inbox_id' => ['required', 'integer', 'exists:email_inboxes,id'],
            'to' => ['required', 'email'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->mail->testInboxSmtp((int) $data['email_inbox_id'], $data['to'], $data['password'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['inbox_smtp' => $exception->getMessage()]);
        }

        return back()->with('success', 'Inbox SMTP test email sent.');
    }

    private function inboxValidationRules(bool $requireInboundMethod): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'email', 'max:255'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['email', 'max:255'],
            'is_active' => ['boolean'],
            'inbound_method' => $requireInboundMethod
                ? ['required', 'in:webhook,poll,oauth']
                : ['nullable', 'in:webhook,poll,oauth'],
            'oauth_provider' => ['nullable', 'in:google,microsoft,zoho'],
            'mailbox_provider' => ['nullable', 'string', 'max:50'],
            'mailbox_protocol' => ['nullable', 'in:imap,pop3'],
            'mailbox_host' => ['nullable', 'string', 'max:255'],
            'mailbox_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mailbox_encryption' => ['nullable', 'in:ssl,tls,none'],
            'mailbox_username' => ['nullable', 'string', 'max:255'],
            'mailbox_password' => ['nullable', 'string', 'max:255'],
            'mailbox_folder' => ['nullable', 'string', 'max:255'],
        ];
    }
}
