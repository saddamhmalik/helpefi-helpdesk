<?php

namespace App\Domains\Channels\Services\Mailbox;

class EmailQuoteStripper
{
    public function strip(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        $cutAt = count($lines);

        foreach ($lines as $index => $line) {
            if ($this->isAttributionLine($line)) {
                $cutAt = min($cutAt, $index);
                break;
            }

            if ($this->isMultilineAttributionStart($line, $lines[$index + 1] ?? null)) {
                $cutAt = min($cutAt, $index);
                break;
            }
        }

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (preg_match('/^-{2,}\s*Original Message\s*-{2,}$/iu', $trimmed)) {
                $cutAt = min($cutAt, $index);
            }

            if (preg_match('/^_{10,}$/', $trimmed) && isset($lines[$index + 1]) && str_starts_with(trim($lines[$index + 1]), 'From:')) {
                $cutAt = min($cutAt, $index);
            }
        }

        $body = implode("\n", array_slice($lines, 0, $cutAt));

        return trim($this->stripTrailingQuotedBlock($body));
    }

    public function stripHtml(string $html): string
    {
        foreach ([
            '/<div[^>]+class="[^"]*gmail_quote[^"]*"[^>]*>/i',
            '/<blockquote\b[^>]*>/i',
            '/<div id="divRplyFwdMsg"[^>]*>/i',
            '/<div class="moz-cite-prefix">/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                $html = substr($html, 0, $matches[0][1]);
            }
        }

        return $html;
    }

    private function isAttributionLine(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(?:'
            .'On .+ wrote:\s*'
            .'|Le .+ a écrit\s*:\s*'
            .'|Am .+ schrieb:\s*'
            .'|El .+ escribió:\s*'
            .'|Il .+ ha scritto:\s*'
            .'|Em .+ escreveu:\s*'
            .')$/iu',
            $trimmed,
        );
    }

    private function isMultilineAttributionStart(string $line, ?string $nextLine): bool
    {
        if ($nextLine === null) {
            return false;
        }

        $trimmed = trim($line);
        $nextTrimmed = trim($nextLine);

        if ($trimmed === '' || $nextTrimmed === '') {
            return false;
        }

        return (bool) preg_match('/^On .+$/iu', $trimmed)
            && (bool) preg_match('/^wrote:\s*$/iu', $nextTrimmed);
    }

    private function stripTrailingQuotedBlock(string $body): string
    {
        $lines = explode("\n", $body);
        $quoteStart = null;

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $trimmed = trim($lines[$index]);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^>\s?/', $lines[$index])) {
                $quoteStart = $index;

                while ($quoteStart > 0) {
                    $previous = trim($lines[$quoteStart - 1]);

                    if ($previous === '' || preg_match('/^>\s?/', $lines[$quoteStart - 1])) {
                        if (preg_match('/^>\s?/', $lines[$quoteStart - 1])) {
                            $quoteStart--;
                        } else {
                            break;
                        }

                        continue;
                    }

                    break;
                }

                break;
            }

            break;
        }

        if ($quoteStart === null) {
            return $body;
        }

        return trim(implode("\n", array_slice($lines, 0, $quoteStart)));
    }
}
