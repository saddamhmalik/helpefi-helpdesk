<?php

namespace App\Domains\Ai\Clients;

use App\Domains\Ai\Contracts\AiEmbeddingClient;
use OpenAI;
use RuntimeException;

class OpenAiEmbeddingClient implements AiEmbeddingClient
{
    public function available(): bool
    {
        return filled(config('ai.embedding_api_key'));
    }

    public function embed(string $text): array
    {
        $factory = OpenAI::factory()
            ->withApiKey((string) config('ai.embedding_api_key'));

        $organization = config('ai.organization');

        if (is_string($organization) && $organization !== '') {
            $factory = $factory->withOrganization($organization);
        }

        $baseUrl = config('ai.embedding_base_url');

        if (is_string($baseUrl) && $baseUrl !== '') {
            $factory = $factory->withBaseUri(rtrim($baseUrl, '/'));
        }

        $client = $factory->make();

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
