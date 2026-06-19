<?php

namespace App\Domains\Channels\Support;

class ThankYouMessageDetector
{
    private const MAX_CANDIDATE_LENGTH = 500;

    private const MAX_SEGMENT_LENGTH = 220;

    private const MAX_SEGMENTS = 4;

    public function isThankYouNote(string $body): bool
    {
        $segments = $this->extractLeadingSegments($body);

        if ($segments === []) {
            return false;
        }

        $candidate = implode(' ', $segments);

        if (mb_strlen($candidate) > self::MAX_CANDIDATE_LENGTH) {
            return false;
        }

        if (str_contains($candidate, '?')) {
            return false;
        }

        if (preg_match('/\b(still|not working|issue|problem|help needed|please fix|broken|error|doesn\'?t work|does not work|unable to|cannot|can\'?t|waiting|follow up|follow-up)\b/i', $candidate)) {
            return false;
        }

        foreach ($segments as $segment) {
            if ($this->matchesThankYou($segment)) {
                return true;
            }
        }

        return $this->matchesThankYou($candidate);
    }

    private function extractLeadingSegments(string $body): array
    {
        $text = strip_tags($body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\R{2,}/', "\n\n", $text) ?? $text;

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/', $text) ?: []),
            fn ($paragraph) => $paragraph !== '',
        ));

        if ($paragraphs === []) {
            return [];
        }

        $paragraphs = $this->stripTrailingSignature($paragraphs);
        $segments = [];

        foreach ($paragraphs as $paragraph) {
            if (count($segments) >= self::MAX_SEGMENTS) {
                break;
            }

            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $paragraph)),
                fn ($line) => $line !== '',
            ));

            foreach ($lines as $line) {
                if (count($segments) >= self::MAX_SEGMENTS) {
                    break 2;
                }

                if (mb_strlen($line) > self::MAX_SEGMENT_LENGTH) {
                    break 2;
                }

                $segments[] = $line;
            }
        }

        return array_map(
            fn ($segment) => trim($segment, " \t\n\r\0\x0B.!,"),
            array_values(array_filter($segments, fn ($segment) => $segment !== '')),
        );
    }

    private function stripTrailingSignature(array $paragraphs): array
    {
        while ($paragraphs !== []) {
            $lastIndex = count($paragraphs) - 1;
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $paragraphs[$lastIndex])),
                fn ($line) => $line !== '',
            ));

            $removed = false;

            while ($lines !== [] && $this->looksLikeSignature(end($lines))) {
                array_pop($lines);
                $removed = true;
            }

            if ($lines === []) {
                array_pop($paragraphs);

                continue;
            }

            if ($removed) {
                $paragraphs[$lastIndex] = implode("\n", $lines);
            }

            break;
        }

        return $paragraphs;
    }

    private function looksLikeSignature(string $text): bool
    {
        $normalized = trim($text);

        if ($normalized === '' || $normalized === '--') {
            return true;
        }

        if (preg_match('/^(?:sent from my|get outlook for|sent from mail for)/i', $normalized)) {
            return true;
        }

        if (preg_match('/^(?:best|kind)?\s*regards,?\s*$/i', $normalized)) {
            return true;
        }

        if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3}$/', $normalized)) {
            return true;
        }

        return false;
    }

    private function matchesThankYou(string $text): bool
    {
        if ($this->matchesPatterns($text)) {
            return true;
        }

        foreach ($this->splitSentences($text) as $sentence) {
            if ($this->matchesPatterns($sentence)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPatterns(string $text): bool
    {
        foreach ($this->patterns() as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function splitSentences(string $text): array
    {
        return array_values(array_filter(array_map(
            fn ($sentence) => trim(trim($sentence), " \t\n\r\0\x0B.!?,"),
            preg_split('/[.!?]+\s+/u', $text) ?: [],
        ), fn ($sentence) => $sentence !== ''));
    }

    private function patterns(): array
    {
        return [
            '/^thanks?(?:\s+you)?(?:\s+so\s+much)?(?:\s+for(?:\s+(?:your|the))?)?(?:\s+help)?$/i',
            '/^thank\s+you(?:\s+so\s+much)?(?:\s+for(?:\s+(?:your|the))?)?(?:\s+help)?$/i',
            '/^thank\s+you\s+for\s+(?:your\s+help|the\s+help|resolving(?:\s+this)?|fixing(?:\s+this)?|getting\s+this\s+(?:fixed|sorted|resolved))$/i',
            '/^(?:many\s+)?thanks(?:\s+for(?:\s+(?:your|the))?\s+help)?$/i',
            '/^(?:much\s+)?appreciated$/i',
            '/^i\s+appreciate(?:\s+it|\s+your\s+help)?$/i',
            '/^thx$/i',
            '/^ty$/i',
            '/^cheers$/i',
            '/^perfect$/i',
            '/^great(?:\s*,?\s*thanks?)?$/i',
            '/^awesome$/i',
            '/^that\s+(?:worked|fixed\s+it|solved\s+it)(?:\s*,?\s*thanks?)?$/i',
            '/^you(?:\'?re|\s+are)\s+(?:the\s+)?best$/i',
            '/^problem\s+solved$/i',
            '/^all\s+(?:good|set|done)$/i',
            '/^got\s+it(?:\s*,?\s*thanks?)?$/i',
            '/^wonderful(?:\s*,?\s*thanks?)?$/i',
        ];
    }
}
