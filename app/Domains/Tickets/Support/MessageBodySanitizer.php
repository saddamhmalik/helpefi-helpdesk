<?php

namespace App\Domains\Tickets\Support;

class MessageBodySanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote>';

    public static function sanitize(string $body): string
    {
        if (! str_contains($body, '<')) {
            return trim($body);
        }

        $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body) ?? $body;
        $body = strip_tags($body, self::ALLOWED_TAGS);
        $body = preg_replace('/\s(on\w+|style|class)\s*=\s*("|\').*?\2/i', '', $body) ?? $body;
        $body = preg_replace('/javascript\s*:/i', '', $body) ?? $body;

        return trim($body);
    }

    public static function toPlainText(string $body): string
    {
        if (! str_contains($body, '<')) {
            return trim($body);
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $body) ?? $body;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function isEmpty(string $body): bool
    {
        return self::toPlainText($body) === '';
    }
}
