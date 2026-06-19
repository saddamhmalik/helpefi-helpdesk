<?php

return [
    'mode' => env('HELPEFI_DEPLOYMENT_MODE', 'saas'),

    'license_key' => env('HELPEFI_LICENSE_KEY'),

    'license_hmac_key' => env('HELPEFI_LICENSE_HMAC_KEY', 'helpefi-self-hosted-license-v1'),

    'license_grace_hours' => (int) env('HELPEFI_LICENSE_GRACE_HOURS', 72),
];
