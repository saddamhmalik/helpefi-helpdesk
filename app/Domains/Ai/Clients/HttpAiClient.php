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
        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);
    }

    public function chat(array $messages): string
    {
        $response = Http::timeout(60)
            ->withToken((string) config('ai.api_key'))
            ->post(rtrim((string) config('ai.base_url'), '/').'/chat/completions', [
                'model' => config('ai.model'),
                'messages' => $messages,
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
