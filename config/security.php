<?php

return [
    'api_token_ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 90),

    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
