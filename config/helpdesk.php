<?php

return [
    'inbound_email_token' => env('INBOUND_EMAIL_TOKEN'),

    'mailbox_providers' => [
        'gmail' => [
            'label' => 'Gmail',
            'protocol' => 'imap',
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'folder' => 'INBOX',
            'help' => 'Enable IMAP in Gmail settings and use a Google App Password when 2FA is enabled.',
        ],
        'outlook' => [
            'label' => 'Microsoft Outlook / Office 365',
            'protocol' => 'imap',
            'host' => 'outlook.office365.com',
            'port' => 993,
            'encryption' => 'ssl',
            'folder' => 'INBOX',
            'help' => 'Enable IMAP in Outlook settings. Use an app password when MFA is enabled.',
        ],
        'yahoo' => [
            'label' => 'Yahoo Mail',
            'protocol' => 'imap',
            'host' => 'imap.mail.yahoo.com',
            'port' => 993,
            'encryption' => 'ssl',
            'folder' => 'INBOX',
            'help' => 'Generate an app password in Yahoo account security settings.',
        ],
        'icloud' => [
            'label' => 'iCloud Mail',
            'protocol' => 'imap',
            'host' => 'imap.mail.me.com',
            'port' => 993,
            'encryption' => 'ssl',
            'folder' => 'INBOX',
            'help' => 'Use an app-specific password from appleid.apple.com.',
        ],
        'gmail_pop3' => [
            'label' => 'Gmail (POP3)',
            'protocol' => 'pop3',
            'host' => 'pop.gmail.com',
            'port' => 995,
            'encryption' => 'ssl',
            'help' => 'Enable POP in Gmail settings and use a Google App Password.',
        ],
        'outlook_pop3' => [
            'label' => 'Outlook (POP3)',
            'protocol' => 'pop3',
            'host' => 'outlook.office365.com',
            'port' => 995,
            'encryption' => 'ssl',
            'help' => 'Enable POP in Outlook settings.',
        ],
        'custom_imap' => [
            'label' => 'Custom IMAP',
            'protocol' => 'imap',
        ],
        'custom_pop3' => [
            'label' => 'Custom POP3',
            'protocol' => 'pop3',
            'port' => 995,
            'encryption' => 'ssl',
        ],
    ],

    'smtp_providers' => [
        'gmail' => [
            'label' => 'Gmail',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'help' => 'Use the same Google App Password as inbound IMAP.',
        ],
        'outlook' => [
            'label' => 'Microsoft Outlook / Office 365',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'help' => 'Use the same credentials as inbound IMAP, or an app password when MFA is enabled.',
        ],
        'yahoo' => [
            'label' => 'Yahoo Mail',
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls',
            'help' => 'Use your Yahoo app password.',
        ],
        'icloud' => [
            'label' => 'iCloud Mail',
            'host' => 'smtp.mail.me.com',
            'port' => 587,
            'encryption' => 'tls',
            'help' => 'Use an app-specific password from appleid.apple.com.',
        ],
        'custom' => [
            'label' => 'Custom SMTP',
        ],
    ],

    'oauth_smtp_provider_map' => [
        'google' => 'gmail',
        'microsoft' => 'outlook',
        'zoho' => 'custom',
    ],

    'mail_oauth' => [
        'callback_base_url' => rtrim((string) env('MAIL_OAUTH_CALLBACK_URL', env('MARKETING_SITE_URL', env('APP_URL', 'https://helpefi.com'))), '/'),

        'google' => [
            'label' => 'Google / Gmail',
            'help' => 'Sign in with Google — no app password needed. Requires Google Cloud OAuth credentials and the Gmail API enabled in the same project.',
            'setup_console_url' => 'https://console.cloud.google.com/apis/library/gmail.googleapis.com',
            'gmail_api_enable_url' => 'https://console.cloud.google.com/apis/library/gmail.googleapis.com',
            'client_id' => env('GOOGLE_MAIL_CLIENT_ID'),
            'client_secret' => env('GOOGLE_MAIL_CLIENT_SECRET'),
            'scopes' => [
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.modify',
                'https://www.googleapis.com/auth/userinfo.email',
            ],
        ],
        'microsoft' => [
            'label' => 'Microsoft / Outlook',
            'help' => 'Sign in with Microsoft — works with Outlook.com and Microsoft 365.',
            'setup_console_url' => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
            'client_id' => env('MICROSOFT_MAIL_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_MAIL_CLIENT_SECRET'),
            'tenant' => env('MICROSOFT_MAIL_TENANT', 'common'),
            'scopes' => [
                'offline_access',
                'openid',
                'email',
                'https://graph.microsoft.com/Mail.Read',
                'https://graph.microsoft.com/Mail.ReadWrite',
            ],
        ],
        'zoho' => [
            'label' => 'Zoho Mail',
            'help' => 'Sign in with Zoho — for Zoho Mail business accounts.',
            'setup_console_url' => 'https://api-console.zoho.com/',
            'client_id' => env('ZOHO_MAIL_CLIENT_ID'),
            'client_secret' => env('ZOHO_MAIL_CLIENT_SECRET'),
            'region' => env('ZOHO_MAIL_REGION', 'com'),
            'scopes' => [
                'ZohoMail.messages.READ',
                'ZohoMail.messages.UPDATE',
                'ZohoMail.accounts.READ',
                'ZohoMail.folders.READ',
            ],
        ],
    ],
];
