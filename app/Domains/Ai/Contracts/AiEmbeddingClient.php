<?php

namespace App\Domains\Ai\Contracts;

interface AiEmbeddingClient
{
    public function available(): bool;

    public function embed(string $text): array;
}
