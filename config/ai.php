<?php

$groqKey = env('GROQ_API_KEY');

return [
    'provider' => $groqKey ? 'groq' : (env('OPENAI_API_KEY') ? 'openai' : null),
    'api_key' => $groqKey ?: env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'base_url' => $groqKey
        ? rtrim((string) env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/')
        : rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'model' => $groqKey
        ? env('GROQ_MODEL', 'llama-3.3-70b-versatile')
        : env('OPENAI_MODEL', 'gpt-4o-mini'),
    'embedding_api_key' => env('OPENAI_API_KEY'),
    'embedding_base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'marketing_demo_enabled' => env('AI_MARKETING_DEMO_ENABLED', true),
];
