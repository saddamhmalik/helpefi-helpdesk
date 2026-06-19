<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class HelpdeskSetting extends Model
{
    protected $fillable = [
        'ticket_number_prefix',
        'contact_fields',
        'ticket_fields',
        'user_fields',
        'auto_first_response_enabled',
        'auto_first_response_body',
        'email_blocklist',
        'sync_ticket_status_from_external_issues',
        'email_allow_agent_initiated',
        'email_use_agent_name_in_from',
        'email_automatic_bcc',
        'email_reply_to_address',
        'email_use_reply_to_as_requester',
        'email_use_original_sender_for_forwarded',
        'email_flexible_recipients',
        'email_ignore_ticket_id_threading',
        'email_create_ticket_on_subject_change',
        'email_detect_auto_replies',
        'kb_deflection_enabled',
        'kb_locales',
        'kb_default_locale',
        'setup_completed_at',
        'setup_steps_completed',
        'dummy_data_active',
        'dummy_data_choice_at',
        'dummy_data_manifest',
    ];

    protected function casts(): array
    {
        return [
            'contact_fields' => 'array',
            'ticket_fields' => 'array',
            'user_fields' => 'array',
            'auto_first_response_enabled' => 'boolean',
            'email_blocklist' => 'array',
            'sync_ticket_status_from_external_issues' => 'boolean',
            'email_allow_agent_initiated' => 'boolean',
            'email_use_agent_name_in_from' => 'boolean',
            'email_use_reply_to_as_requester' => 'boolean',
            'email_use_original_sender_for_forwarded' => 'boolean',
            'email_flexible_recipients' => 'boolean',
            'email_ignore_ticket_id_threading' => 'boolean',
            'email_create_ticket_on_subject_change' => 'boolean',
            'email_detect_auto_replies' => 'boolean',
            'kb_deflection_enabled' => 'boolean',
            'kb_locales' => 'array',
            'setup_completed_at' => 'datetime',
            'setup_steps_completed' => 'array',
            'dummy_data_active' => 'boolean',
            'dummy_data_choice_at' => 'datetime',
            'dummy_data_manifest' => 'array',
        ];
    }
}
