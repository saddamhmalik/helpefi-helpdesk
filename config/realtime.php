<?php

return [
    'ws_url' => env('REALTIME_WS_URL', 'ws://127.0.0.1:8080'),
    'ws_host' => env('REALTIME_WS_HOST', '127.0.0.1'),
    'ws_port' => (int) env('REALTIME_WS_PORT', 8080),
    'redis_prefix' => env('REALTIME_REDIS_PREFIX', 'helpdesk:realtime:'),
    'token_ttl' => (int) env('REALTIME_TOKEN_TTL', 3600),
    'enabled' => (bool) env('REALTIME_ENABLED', true),
];
