<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Repositories\MarketingImageRepository;
use Illuminate\Http\Response;

class MarketingImageService
{
    public function __construct(
        private MarketingImageRepository $images,
    ) {
    }

    public function response(
        string $path,
        int $width,
        string $format,
        int $quality = 78,
        bool $blur = false,
    ): ?Response {
        $source = $this->images->publicImageAbsolutePath($path);
        if (! is_string($source)) {
            return null;
        }

        $width = max(16, min(4096, $width));
        $quality = max(30, min(92, $quality));

        $sourceExt = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $targetFormat = $this->normalizeFormat($format, $sourceExt);
        $cacheKey = hash('sha256', implode('|', [$path, (string) filemtime($source), (string) filesize($source), (string) $width, $targetFormat, (string) $quality, $blur ? '1' : '0']));
        $cacheExt = $this->cacheExtension($targetFormat, $sourceExt);
        $cachePath = $this->images->cacheAbsolutePath($cacheKey, $cacheExt);

        if (! $this->images->hasCached($cachePath)) {
            $bytes = $this->render($source, $width, $targetFormat, $quality, $blur);

            if ($bytes === null || $bytes === '') {
                return $this->originalFileResponse($source);
            }

            file_put_contents($cachePath, $bytes);
        }

        $bytes = $this->images->readBytes($cachePath);

        return response($bytes, 200, [
            'Content-Type' => $this->contentType($targetFormat, $sourceExt),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function normalizeFormat(string $format, string $sourceExt): string
    {
        $f = strtolower(trim($format));

        if ($f === '' || $f === 'auto') {
            return 'auto';
        }

        if (in_array($f, ['avif', 'webp', 'png', 'jpg', 'jpeg'], true)) {
            return $f;
        }

        return $sourceExt;
    }

    private function cacheExtension(string $targetFormat, string $sourceExt): string
    {
        if ($targetFormat === 'auto') {
            return $this->bestAvailableFormat() ?? $sourceExt;
        }

        return $targetFormat === 'jpeg' ? 'jpg' : $targetFormat;
    }

    private function contentType(string $targetFormat, string $sourceExt): string
    {
        $fmt = $targetFormat === 'auto'
            ? ($this->bestAvailableFormat() ?? $sourceExt)
            : $targetFormat;

        return match ($fmt) {
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    private function bestAvailableFormat(): ?string
    {
        if (function_exists('imageavif')) {
            return 'avif';
        }

        if (function_exists('imagewebp')) {
            return 'webp';
        }

        return null;
    }

    private function render(string $source, int $width, string $targetFormat, int $quality, bool $blur): ?string
    {
        $info = @getimagesize($source);
        if (! is_array($info) || ! isset($info[0], $info[1], $info['mime'])) {
            return null;
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];

        if ($srcW <= 0 || $srcH <= 0) {
            return null;
        }

        $ratio = $srcH / $srcW;
        $dstW = min($width, $srcW);
        $dstH = max(1, (int) round($dstW * $ratio));

        $mime = (string) $info['mime'];
        $src = match ($mime) {
            'image/png' => @imagecreatefrompng($source),
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null,
            'image/gif' => @imagecreatefromgif($source),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($source) : null,
            default => null,
        };

        if (! $src) {
            return null;
        }

        $dst = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        if ($blur) {
            @imagefilter($dst, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($dst, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $finalFormat = $targetFormat === 'auto'
            ? ($this->bestAvailableFormat() ?? $this->fallbackFormatFromMime($mime))
            : $targetFormat;

        ob_start();
        $ok = match ($finalFormat) {
            'avif' => function_exists('imageavif') ? @imageavif($dst, null, $quality) : false,
            'webp' => function_exists('imagewebp') ? @imagewebp($dst, null, $quality) : false,
            'png' => @imagepng($dst),
            'jpg', 'jpeg' => @imagejpeg($dst, null, $quality),
            default => false,
        };

        $bytes = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    private function originalFileResponse(string $source): ?Response
    {
        if (! is_file($source)) {
            return null;
        }

        $info = @getimagesize($source);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $contentType = $mime !== '' ? $mime : 'application/octet-stream';

        return response((string) file_get_contents($source), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function fallbackFormatFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }
}

