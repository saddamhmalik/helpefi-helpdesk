<?php

namespace App\Domains\Ai\Clients;

use App\Domains\Ai\Contracts\AiEmbeddingClient;
use OpenAI;
use RuntimeException;

class OpenAiEmbeddingClient implements AiEmbeddingClient
{
    public function available(): bool
    {
        return filled(config('ai.api_key'));
    }

    public function embed(string $text): array
    {
        $client = OpenAI::client(config('ai.api_key'), config('ai.organization'));

        $response = $client->embeddings()->create([
            'model' => config('ai.embedding_model'),
            'input' => $text,
        ]);

        $embedding = $response->embeddings[0]->embedding ?? null;

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Embedding provider returned an empty vector.');
        }

        return array_map('floatval', $embedding);
    }
}
