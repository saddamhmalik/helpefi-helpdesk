<?php

$input = $argv[1] ?? dirname(__DIR__).'/public/logo.png';

$extension = strtolower(pathinfo($input, PATHINFO_EXTENSION));

$src = match ($extension) {
    'jpg', 'jpeg' => @imagecreatefromjpeg($input),
    'png' => @imagecreatefrompng($input),
    'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($input) : false,
    default => false,
};

if ($src === false) {
    $bytes = file_get_contents($input, false, null, 0, 4);
    $src = str_starts_with($bytes, "\xFF\xD8\xFF")
        ? @imagecreatefromjpeg($input)
        : @imagecreatefrompng($input);
}

if ($src === false) {
    fwrite(STDERR, "Unable to read image: {$input}\n");
    exit(1);
}

$width = imagesx($src);
$height = imagesy($src);

$img = imagecreatetruecolor($width, $height);
imagealphablending($img, false);
imagesavealpha($img, true);

$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefilledrectangle($img, 0, 0, $width, $height, $transparent);

imagealphablending($img, true);
imagecopy($img, $src, 0, 0, 0, 0, $width, $height);
imagealphablending($img, false);

for ($x = 0; $x < $width; $x++) {
    for ($y = 0; $y < $height; $y++) {
        $rgba = imagecolorat($img, $x, $y);
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        if ($r >= 245 && $g >= 245 && $b >= 245) {
            imagesetpixel($img, $x, $y, $transparent);
        }
    }
}

$output = preg_match('/\.png$/i', $input) ? $input : preg_replace('/\.[^.]+$/', '.png', $input);

imagepng($img, $output);
imagedestroy($src);
imagedestroy($img);

echo "Saved transparent PNG to {$output}\n";
