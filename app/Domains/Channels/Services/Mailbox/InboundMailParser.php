<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Data\InboundMailAttachment;
use App\Domains\Channels\Data\InboundMailMessage;

class InboundMailParser
{
    public static function parse(string $raw, ?string $fallbackId = null): InboundMailMessage
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = self::splitHeadersAndBody($raw);
        $headers = self::parseHeaders($headerBlock);
        $contentType = self::headerValue($headers, 'content-type', 'text/plain');
        $parsed = self::extractContent($body, $contentType, $headers);

        $from = self::resolveSender($headers);
        $messageId = trim(self::headerValue($headers, 'message-id', ''), '<> ');
        $subject = self::decodeHeader(self::headerValue($headers, 'subject', ''));
        $to = self::parseAddress(self::headerValue($headers, 'to', ''));
        $ccEmails = self::parseAddressList(self::headerValue($headers, 'cc', ''));
        $inReplyTo = self::parseMessageIds(self::headerValue($headers, 'in-reply-to', ''));
        $references = self::parseMessageIds(self::headerValue($headers, 'references', ''));

        if ($messageId === '') {
            $messageId = $fallbackId ?? sha1($raw);
        }

        return new InboundMailMessage(
            messageId: $messageId,
            fromEmail: $from['email'] ?: 'unknown@unknown.test',
            fromName: $from['name'],
            subject: $subject !== '' ? $subject : null,
            body: trim($parsed['body']) !== '' ? trim($parsed['body']) : '(empty message)',
            toEmail: $to['email'] ?: null,
            pollUid: $fallbackId,
            inReplyTo: $inReplyTo,
            references: $references,
            attachments: $parsed['attachments'],
            ccEmails: $ccEmails,
        );
    }

    private static function parseAddressList(string $value): array
    {
        $value = self::decodeHeader(trim($value));

        if ($value === '') {
            return [];
        }

        $emails = [];

        foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) as $part) {
            $address = self::parseAddress(trim($part));

            if ($address['email'] !== '') {
                $emails[] = $address['email'];
            }
        }

        return array_values(array_unique($emails));
    }

    private static function parseMessageIds(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        preg_match_all('/<([^>]+)>/', $value, $matches);

        $ids = array_map('trim', $matches[1] ?? []);

        if ($ids === [] && str_contains($value, '@')) {
            $ids = [trim($value, " <>\\t\\n\\r")];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private static function resolveSender(array $headers): array
    {
        $from = self::parseAddress(self::headerValue($headers, 'from', ''));
        $replyTo = self::parseAddress(self::headerValue($headers, 'reply-to', ''));

        if ($replyTo['email'] !== '') {
            return [
                'name' => $replyTo['name'] ?: $from['name'],
                'email' => $replyTo['email'],
            ];
        }

        return $from;
    }

    private static function splitHeadersAndBody(string $raw): array
    {
        $parts = preg_split("/\n\n/", $raw, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private static function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $current = null;

        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\s+/', $line) && $current !== null) {
                $headers[$current] .= ' '.trim($line);

                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $current = strtolower(trim($name));
            $headers[$current] = trim($value);
        }

        return $headers;
    }

    private static function headerValue(array $headers, string $name, string $default = ''): string
    {
        return $headers[$name] ?? $default;
    }

    private static function parseAddress(string $value): array
    {
        $value = self::decodeHeader(trim($value));

        if (preg_match('/(.*)<([^>]+)>/', $value, $matches)) {
            return [
                'name' => trim($matches[1], ' "') ?: null,
                'email' => strtolower(trim($matches[2])),
            ];
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['name' => null, 'email' => strtolower($value)];
        }

        return ['name' => $value !== '' ? $value : null, 'email' => ''];
    }

    private static function decodeHeader(string $value): string
    {
        return mb_decode_mimeheader($value) ?: $value;
    }

    private static function extractContent(string $body, string $contentType, array $headers): array
    {
        if (stripos($contentType, 'multipart/') === 0) {
            $boundary = self::extractBoundary($contentType);

            if ($boundary) {
                return self::extractFromMultipart($body, $boundary);
            }
        }

        return [
            'body' => self::finalizeBody(
                self::decodeBody($body, self::headerValue($headers, 'content-transfer-encoding', '')),
                $contentType,
            ),
            'attachments' => [],
        ];
    }

    private static function extractFromMultipart(string $body, string $boundary): array
    {
        $parts = preg_split('/--'.preg_quote($boundary, '/').'(?:--)?\s*/', $body) ?: [];
        $plain = null;
        $plainType = '';
        $html = null;
        $attachments = [];
        $resolvedBody = null;

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || $part === '--') {
                continue;
            }

            [$headerBlock, $partBody] = self::splitHeadersAndBody($part);
            $headers = self::parseHeaders($headerBlock);
            $type = self::headerValue($headers, 'content-type', '');
            $encoding = self::headerValue($headers, 'content-transfer-encoding', '');

            if (stripos($type, 'message/') === 0) {
                continue;
            }

            if (stripos($type, 'multipart/') === 0) {
                $nestedBoundary = self::extractBoundary($type);

                if ($nestedBoundary) {
                    $nested = self::extractFromMultipart($partBody, $nestedBoundary);
                    $attachments = array_merge($attachments, $nested['attachments']);

                    if ($nested['body'] !== '') {
                        $resolvedBody ??= $nested['body'];
                    }
                }

                continue;
            }

            if (self::isAttachmentPart($headers, $type)) {
                $filename = self::extractFilename($headers, $type);
                $decoded = self::decodeBody($partBody, $encoding);

                if ($filename && $decoded !== '') {
                    $attachments[] = new InboundMailAttachment(
                        filename: $filename,
                        content: $decoded,
                        mimeType: self::extractMimeType($type),
                    );
                }

                continue;
            }

            if (stripos($type, 'text/plain') !== false) {
                $plain = self::decodeBody($partBody, $encoding);
                $plainType = $type;

                continue;
            }

            if (stripos($type, 'text/html') !== false && $html === null) {
                $html = self::decodeBody($partBody, $encoding);
            }
        }

        if ($resolvedBody !== null) {
            return [
                'body' => $resolvedBody,
                'attachments' => $attachments,
            ];
        }

        $bodyText = '';

        if ($plain !== null && ! self::isLowQualityPlain($plain)) {
            $bodyText = self::finalizeBody($plain, $plainType);
        } elseif ($html !== null) {
            $bodyText = self::finalizeBody(self::htmlToText($html), 'text/plain');
        } elseif ($plain !== null) {
            $bodyText = self::finalizeBody($plain, $plainType);
        }

        return [
            'body' => $bodyText,
            'attachments' => $attachments,
        ];
    }

    private static function isAttachmentPart(array $headers, string $type): bool
    {
        $disposition = self::headerValue($headers, 'content-disposition', '');

        if (stripos($disposition, 'inline') !== false) {
            return false;
        }

        if (stripos($disposition, 'attachment') !== false) {
            return true;
        }

        if (stripos($type, 'text/plain') !== false || stripos($type, 'text/html') !== false) {
            return false;
        }

        if (stripos($type, 'message/') === 0) {
            return false;
        }

        $filename = self::extractFilename($headers, $type);

        return $filename !== null && $disposition !== '';
    }

    private static function extractFilename(array $headers, string $contentType): ?string
    {
        $disposition = self::headerValue($headers, 'content-disposition', '');

        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches)) {
            return self::decodeHeader(trim($matches[1], " \t\"'"));
        }

        if (preg_match('/name\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $contentType, $matches)) {
            return self::decodeHeader(trim($matches[1], " \t\"'"));
        }

        return null;
    }

    private static function extractMimeType(string $contentType): ?string
    {
        if (preg_match('/^([^;]+)/', $contentType, $matches)) {
            return trim(strtolower($matches[1]));
        }

        return null;
    }

    private static function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function isLowQualityPlain(string $plain): bool
    {
        $trimmed = trim($plain);

        if ($trimmed === '') {
            return true;
        }

        if (preg_match('/^\[image:/mi', $trimmed)) {
            return true;
        }

        $withoutImages = preg_replace('/^\[image:[^\]]+\]\s*$/mi', '', $plain) ?? $plain;

        return trim($withoutImages) === '';
    }

    private static function htmlToText(string $html): string
    {
        $html = app(EmailQuoteStripper::class)->stripHtml($html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<(br|BR)\s*\/?>/', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<li[^>]*>/i', '- ', $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return $text;
    }

    private static function finalizeBody(string $body, string $contentType): string
    {
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\r", "\n", $body);

        if (stripos($contentType, 'format=flowed') !== false) {
            $body = self::unwrapFlowed($body);
        }

        $body = preg_replace('/^\[image:[^\]]+\]\s*$/mi', '', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body);
    }

    private static function unwrapFlowed(string $text): string
    {
        $lines = explode("\n", $text);
        $output = [];
        $buffer = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, ' ')) {
                if ($buffer !== '') {
                    $output[] = rtrim($buffer);
                    $buffer = '';
                }

                $output[] = $line;

                continue;
            }

            if ($buffer !== '') {
                $buffer = rtrim($buffer).' '.ltrim($line);
            } else {
                $buffer = $line;
            }

            if (! str_ends_with($line, ' ')) {
                $output[] = rtrim($buffer);
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $output[] = rtrim($buffer);
        }

        return implode("\n", $output);
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => base64_decode(str_replace(["\r", "\n", ' '], '', $body)) ?: $body,
            'quoted-printable' => quoted_printable_decode($body) ?: $body,
            default => $body,
        };
    }
}
