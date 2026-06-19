<?php

namespace App\Domains\Channels\Support;

class ThankYouMessageDetector
{
    private const MAX_LENGTH = 280;

    public function isThankYouNote(string $body): bool
    {
        $text = $this->normalize($body);

        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH) {
            return false;
        }

        if (str_contains($text, '?')) {
            return false;
        }

        if (preg_match('/\b(still|not working|issue|problem|help needed|please fix|broken|error|doesn\'?t work|does not work|unable to|cannot|can\'?t|waiting|follow up|follow-up)\b/i', $text)) {
            return false;
        }

        foreach ($this->patterns() as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $body): string
    {
        $text = strip_tags($body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\R{2,}/', "\n", $text) ?? $text;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));

        if ($lines === []) {
            return '';
        }

        $text = $lines[0];

        if (count($lines) > 1 && mb_strlen($text) <= 120) {
            $remainder = implode(' ', array_slice($lines, 1));

            if (mb_strlen($remainder) <= 40 && ! preg_match('/\b(issue|problem|help|please)\b/i', $remainder)) {
                $text = trim($text.' '.$remainder);
            }
        }

        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return trim($text, " \t\n\r\0\x0B.!?,");
    }

    private function patterns(): array
    {
        return [
            '/^thanks?(?:\s+you)?(?:\s+so\s+much)?(?:\s+for(?:\s+(?:your|the))?)?(?:\s+help)?$/i',
            '/^thank\s+you(?:\s+so\s+much)?(?:\s+for(?:\s+(?:your|the))?)?(?:\s+help)?$/i',
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
