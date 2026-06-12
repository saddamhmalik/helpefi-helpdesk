<?php

namespace App\Domains\Ai\Contracts;

interface AiCompletionClient
{
    public function available(): bool;

    public function complete(string $systemPrompt, string $userPrompt): string;

    public function chat(array $messages): string;
}
