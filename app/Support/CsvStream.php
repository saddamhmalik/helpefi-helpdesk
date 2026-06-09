<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvStream
{
    public static function download(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public static function timestampedFilename(string $prefix): string
    {
        return $prefix.'-'.now()->format('Y-m-d-His').'.csv';
    }
}
