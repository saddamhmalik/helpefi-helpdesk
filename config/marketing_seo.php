<?php

return [
    'site_url' => rtrim((string) env('MARKETING_SITE_URL', env('APP_URL', 'https://helpefi.com')), '/'),

    'organization' => [
        'legal_name' => env('MARKETING_ORG_NAME', 'Helpefi'),
        'contact_email' => env('MARKETING_CONTACT_EMAIL', 'hello@helpefi.com'),
        'contact_type' => 'customer support',
        'logo_path' => '/og-image.png',
    ],

    'analytics' => [
        'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
        'bing_site_verification' => env('BING_SITE_VERIFICATION'),
    ],

    'twitter' => [
        'site' => env('MARKETING_TWITTER_SITE', '@helpefi'),
    ],

    'robots' => [
        'disallow' => [
            '/admin',
            '/admin/',
            '/dashboard',
            '/login',
            '/register/verify',
            '/api/',
            '/razorpay/',
        ],
    ],

    'static_pages' => [
        'pricing' => [
            'path' => '/pricing',
            'sitemap' => true,
            'changefreq' => 'weekly',
            'priority' => '0.95',
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
