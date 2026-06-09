<?php

namespace App\Domains\Ai\Clients;

use App\Domains\Ai\Contracts\AiCompletionClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpAiClient implements AiCompletionClient
{
    public function available(): bool
    {
        return filled(config('ai.api_key'));
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::timeout(30)
            ->withToken(config('ai.api_key'))
            ->post(rtrim(config('ai.base_url'), '/').'/chat/completions', [
                'model' => config('ai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI provider request failed.');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return trim($content);
    }
}
