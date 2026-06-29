<?php

return [
    'site_url' => rtrim((string) env('MARKETING_SITE_URL', env('APP_URL', 'https://helpefi.com')), '/'),

    'organization' => [
        'legal_name' => env('MARKETING_ORG_NAME', 'Helpefi'),
        'contact_email' => env('MARKETING_CONTACT_EMAIL', 'hello@helpefi.com'),
        'contact_type' => 'customer support',
        'logo_path' => '/og-image.png',
        'parent_company_name' => env('MARKETING_PARENT_COMPANY_NAME', 'Codikal'),
        'parent_company_url' => rtrim((string) env('MARKETING_PARENT_COMPANY_URL', 'https://codikal.com'), '/'),
    ],

    'contact_form' => [
        'ip_max_attempts' => (int) env('MARKETING_CONTACT_IP_MAX', 5),
        'ip_decay_minutes' => (int) env('MARKETING_CONTACT_IP_DECAY', 1),
        'email_max_attempts' => (int) env('MARKETING_CONTACT_EMAIL_MAX', 3),
        'email_decay_minutes' => (int) env('MARKETING_CONTACT_EMAIL_DECAY', 60),
        'min_seconds_on_page' => (int) env('MARKETING_CONTACT_MIN_SECONDS', 3),
        'max_form_age_minutes' => (int) env('MARKETING_CONTACT_MAX_AGE', 120),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'analytics' => [
        'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
        'bing_site_verification' => env('BING_SITE_VERIFICATION'),
        'ahrefs_analytics_key' => env('AHREFS_ANALYTICS_KEY', '5IT1VWG7SN8wIl3TxsDObg'),
    ],

    'twitter' => [
        'site' => env('MARKETING_TWITTER_SITE', '@helpefi'),
    ],

    'og_images' => [
        'home' => env('MARKETING_OG_IMAGE_HOME', '/og-image.png'),
        'features_index' => env('MARKETING_OG_IMAGE_FEATURES', '/og-image.png'),
        'static_pricing' => env('MARKETING_OG_IMAGE_PRICING', '/og-image.png'),
        'feature_ai' => env('MARKETING_OG_IMAGE_AI', '/og-image.png'),
        'compare_zendesk' => env('MARKETING_OG_IMAGE_COMPARE_ZENDESK', '/og-image.png'),
        'migrate_zendesk' => env('MARKETING_OG_IMAGE_MIGRATE_ZENDESK', '/og-image.png'),
    ],

    'robots' => [
        'disallow' => [
            '/admin',
            '/dashboard',
            '/login',
            '/register',
            '/api',
            '/storage',
        ],
    ],

    'static_pages' => [
        'pricing' => [
            'path' => '/pricing',
            'sitemap' => true,
            'changefreq' => 'weekly',
            'priority' => '0.95',
        ],
        'integrations' => [
            'path' => '/integrations',
            'sitemap' => true,
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ],
        'industries' => [
            'path' => '/industries',
            'sitemap' => true,
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ],
        'resources' => [
            'path' => '/resources',
            'sitemap' => true,
            'changefreq' => 'weekly',
            'priority' => '0.75',
        ],
        'support' => [
            'path' => '/support',
            'sitemap' => true,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ],
        'about' => [
            'path' => '/about',
            'sitemap' => true,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ],
        'contact' => [
            'path' => '/contact',
            'sitemap' => true,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ],
        'privacy' => [
            'path' => '/privacy',
            'sitemap' => true,
            'changefreq' => 'yearly',
            'priority' => '0.4',
        ],
        'terms' => [
            'path' => '/terms',
            'sitemap' => true,
            'changefreq' => 'yearly',
            'priority' => '0.4',
        ],
    ],
];
