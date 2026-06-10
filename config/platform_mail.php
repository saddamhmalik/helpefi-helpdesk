<?php

return [
    'from' => [
        'address' => env('CENTRAL_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@helpdesk.test')),
        'name' => env('CENTRAL_MAIL_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'helpefi'))),
    ],
];
