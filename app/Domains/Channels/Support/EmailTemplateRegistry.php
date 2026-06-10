<?php

namespace App\Domains\Channels\Support;

use App\Domains\Channels\Models\EmailTemplate;

class EmailTemplateRegistry
{
    public static function definitions(): array
    {
        return [
            [
                'slug' => EmailTemplate::SLUG_TICKET_REPLY,
                'name' => 'Ticket reply to customer',
                'trigger' => 'Agent sends a public reply on a ticket',
                'subject' => 'Re: [{{ticket_number}}] {{ticket_subject}}',
                'body_html' => <<<'HTML'
<p><strong>{{agent_name}}</strong> replied to your request <strong>[{{ticket_number}}]</strong>:</p>
{{reply_body}}
<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
<p style="font-size:13px;color:#64748b;">To reply, respond to this email or include [{{ticket_number}}] in the subject line.</p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                    ['key' => 'agent_name', 'label' => 'Agent name'],
                    ['key' => 'reply_body', 'label' => 'Reply message HTML'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_AUTO_FIRST_RESPONSE,
                'name' => 'Auto first response',
                'trigger' => 'Automatic acknowledgement when a customer emails in',
                'subject' => 'Re: [{{ticket_number}}] {{ticket_subject}}',
                'body_html' => <<<'HTML'
<p>We received your request [{{ticket_number}}]:</p>
{{reply_body}}
<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">
<p><strong>Your message:</strong></p>
<div style="border-left:3px solid #cbd5e1;padding-left:12px;color:#475569;">{{original_message_body}}</div>
<p>---<br>To reply, respond to this email or include [{{ticket_number}}] in the subject line.</p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                    ['key' => 'reply_body', 'label' => 'Auto-reply HTML'],
                    ['key' => 'original_message_body', 'label' => 'Customer message HTML'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_TEAM_INVITATION,
                'name' => 'Team invitation',
                'trigger' => 'Admin invites a new team member',
                'subject' => 'You have been invited to {{app_name}}',
                'body_html' => <<<'HTML'
<p>You have been invited to join <strong>{{app_name}}</strong>.</p>
<p>{{inviter_name}} invited you as an {{role}}.</p>
<p><a href="{{accept_url}}">Accept your invitation</a></p>
<p style="color:#64748b;font-size:13px;">This link expires on {{expires_at}}.</p>
<p style="color:#64748b;font-size:13px;">If you did not expect this invitation, you can ignore this email.</p>
HTML,
                'placeholders' => [
                    ['key' => 'app_name', 'label' => 'Workspace name'],
                    ['key' => 'inviter_name', 'label' => 'Inviter name'],
                    ['key' => 'role', 'label' => 'Invited role'],
                    ['key' => 'accept_url', 'label' => 'Accept invitation URL'],
                    ['key' => 'expires_at', 'label' => 'Expiry date/time'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_CSAT_SURVEY,
                'name' => 'CSAT survey',
                'trigger' => 'Ticket is closed and CSAT email is enabled',
                'subject' => 'How did we do on {{ticket_number}}?',
                'body_html' => <<<'HTML'
<p>Your support request <strong>{{ticket_number}}</strong> has been resolved.</p>
<p>How satisfied were you with our support?</p>
<p>{{rating_links}}</p>
<p><a href="{{survey_url}}">Leave detailed feedback</a></p>
<p style="color:#64748b;font-size:12px;margin-top:24px;">Thank you for helping us improve.</p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'survey_url', 'label' => 'Survey page URL'],
                    ['key' => 'rating_links', 'label' => 'Star rating links HTML'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_TICKET_EXPORT,
                'name' => 'Ticket PDF export',
                'trigger' => 'Agent emails a ticket PDF to a recipient',
                'subject' => '[{{ticket_number}}] {{ticket_subject}}',
                'body_html' => <<<'HTML'
<p>Attached is the PDF export for ticket <strong>{{ticket_number}}</strong>: {{ticket_subject}}</p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_SCHEDULED_REPORT,
                'name' => 'Scheduled report',
                'trigger' => 'A saved report schedule runs',
                'subject' => 'Scheduled report: {{report_name}}',
                'body_html' => <<<'HTML'
<p>Hi {{recipient_name}},</p>
<p>Your scheduled <strong>{{report_type}}</strong> report <strong>{{report_name}}</strong> is attached.</p>
HTML,
                'placeholders' => [
                    ['key' => 'recipient_name', 'label' => 'Recipient name'],
                    ['key' => 'report_name', 'label' => 'Report name'],
                    ['key' => 'report_type', 'label' => 'Report type label'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_SIDE_CONVERSATION,
                'name' => 'Side conversation',
                'trigger' => 'Agent emails an external contact from a side conversation',
                'subject' => 'Re: [{{ticket_number}}] {{ticket_subject}}',
                'body_html' => <<<'HTML'
<p><strong>{{agent_name}}</strong> sent a message regarding ticket <strong>[{{ticket_number}}]</strong>:</p>
{{reply_body}}
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                    ['key' => 'agent_name', 'label' => 'Agent name'],
                    ['key' => 'reply_body', 'label' => 'Message HTML'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_TICKET_ASSIGNED,
                'name' => 'Ticket assigned',
                'trigger' => 'An agent is assigned to a ticket',
                'subject' => 'Assigned: {{ticket_number}}',
                'body_html' => <<<'HTML'
<p>You were assigned to ticket <strong>{{ticket_number}}</strong>.</p>
<p>{{ticket_subject}}</p>
<p><a href="{{action_url}}">View ticket</a></p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                    ['key' => 'action_url', 'label' => 'Ticket URL'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_CUSTOMER_REPLY,
                'name' => 'Customer reply',
                'trigger' => 'A customer replies on a ticket',
                'subject' => 'Customer reply: {{ticket_number}}',
                'body_html' => <<<'HTML'
<p>A customer replied on <strong>{{ticket_number}}</strong>.</p>
<p>{{message_preview}}</p>
<p><a href="{{action_url}}">View ticket</a></p>
HTML,
                'placeholders' => [
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'message_preview', 'label' => 'Reply preview text'],
                    ['key' => 'action_url', 'label' => 'Ticket URL'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_SLA_BREACH,
                'name' => 'SLA breach',
                'trigger' => 'A ticket SLA target is breached',
                'subject' => 'SLA breach: {{ticket_number}}',
                'body_html' => <<<'HTML'
<p><strong>{{breach_label}}</strong> on ticket <strong>{{ticket_number}}</strong>.</p>
<p>{{ticket_subject}}</p>
<p><a href="{{action_url}}">View ticket</a></p>
HTML,
                'placeholders' => [
                    ['key' => 'breach_label', 'label' => 'Breach type label'],
                    ['key' => 'ticket_number', 'label' => 'Ticket number'],
                    ['key' => 'ticket_subject', 'label' => 'Ticket subject'],
                    ['key' => 'action_url', 'label' => 'Ticket URL'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_APPROVAL_REQUESTED,
                'name' => 'Approval requested',
                'trigger' => 'An approver must review a service desk request',
                'subject' => 'Approval required: {{request_subject}}',
                'body_html' => <<<'HTML'
<p>A service desk request is waiting for your approval.</p>
<p>{{request_subject}}</p>
<p><a href="{{action_url}}">Review request</a></p>
HTML,
                'placeholders' => [
                    ['key' => 'request_subject', 'label' => 'Request subject'],
                    ['key' => 'action_url', 'label' => 'Review URL'],
                ],
            ],
            [
                'slug' => EmailTemplate::SLUG_APPROVAL_DECIDED,
                'name' => 'Approval decided',
                'trigger' => 'An approval request is approved or rejected',
                'subject' => 'Request {{decision}}: {{request_subject}}',
                'body_html' => <<<'HTML'
<p>Your service desk request was <strong>{{decision}}</strong>.</p>
<p>{{request_subject}}</p>
<p><a href="{{action_url}}">View ticket</a></p>
HTML,
                'placeholders' => [
                    ['key' => 'decision', 'label' => 'approved or rejected'],
                    ['key' => 'request_subject', 'label' => 'Request subject'],
                    ['key' => 'action_url', 'label' => 'Ticket URL'],
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['slug'] === $slug) {
                return $definition;
            }
        }

        return null;
    }

    public static function placeholdersFor(string $slug): array
    {
        return self::find($slug)['placeholders'] ?? [];
    }

    public static function allPlaceholders(): array
    {
        $merged = [];

        foreach (self::definitions() as $definition) {
            foreach ($definition['placeholders'] as $placeholder) {
                $merged[$placeholder['key']] = $placeholder;
            }
        }

        return array_values($merged);
    }
}
