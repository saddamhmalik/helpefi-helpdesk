<?php

namespace App\Domains\Platform\Services;

class MarketingContentDuplicateService
{
    private const SIMILARITY_WARN = 0.55;

    private const SIMILARITY_BLOCK = 0.82;

    public function __construct(private MarketingContentCorpusService $corpus)
    {
    }

    public function analyze(string $text, ?string $excludeId = null): array
    {
        $normalized = $this->normalize($text);
        $fingerprint = hash('sha256', $normalized);

        if ($normalized === '') {
            return [
                'fingerprint' => null,
                'warnings' => [],
                'blocked' => false,
            ];
        }

        $warnings = [];

        foreach ($this->corpus->allEntries() as $entry) {
            if ($excludeId !== null && ($entry['id'] ?? '') === $excludeId) {
                continue;
            }

            $entryText = $this->normalize((string) ($entry['text'] ?? ''));

            if ($entryText === '') {
                continue;
            }

            if (hash('sha256', $entryText) === $fingerprint) {
                $warnings[] = [
                    'severity' => 'block',
                    'similarity' => 1.0,
                    'source' => $entry['id'] ?? 'unknown',
                    'title' => $entry['title'] ?? '',
                    'message' => 'Near-identical content already exists.',
                ];

                continue;
            }

            $similarity = $this->jaccardSimilarity($normalized, $entryText);

            if ($similarity >= self::SIMILARITY_WARN) {
                $warnings[] = [
                    'severity' => $similarity >= self::SIMILARITY_BLOCK ? 'block' : 'warn',
                    'similarity' => round($similarity, 3),
                    'source' => $entry['id'] ?? 'unknown',
                    'title' => $entry['title'] ?? '',
                    'message' => $similarity >= self::SIMILARITY_BLOCK
                        ? 'Content is too similar to existing material.'
                        : 'Content overlaps with existing material — revise for uniqueness.',
                ];
            }
        }

        usort($warnings, fn (array $a, array $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));

        return [
            'fingerprint' => $fingerprint,
            'warnings' => array_slice($warnings, 0, 8),
            'blocked' => collect($warnings)->contains(fn (array $w) => ($w['severity'] ?? '') === 'block'),
        ];
    }

    public function extractTextFromPayload(array $payload, string $title = ''): string
    {
        $parts = [$title];

        array_walk_recursive($payload, function ($value) use (&$parts) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = $value;
            }
        });

        return implode(' ', $parts);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function jaccardSimilarity(string $a, string $b): float
    {
        $shinglesA = $this->shingles($a);
        $shinglesB = $this->shingles($b);

        if ($shinglesA === [] || $shinglesB === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($shinglesA, $shinglesB));
        $union = count(array_unique(array_merge($shinglesA, $shinglesB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function shingles(string $text, int $size = 3): array
    {
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) < $size) {
            return $tokens === [] ? [] : [implode(' ', $tokens)];
        }

        $shingles = [];

        for ($i = 0; $i <= count($tokens) - $size; $i++) {
            $shingles[] = implode(' ', array_slice($tokens, $i, $size));
        }

        return $shingles;
    }
}
