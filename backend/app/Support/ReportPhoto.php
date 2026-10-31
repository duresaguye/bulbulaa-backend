<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Re-draws an uploaded photo as a fresh JPEG. This removes hidden data such as the GPS position where it was taken
 * (important for anonymous reports), shrinks large phone photos and turns them upright.
 */
class ReportPhoto
{
    public const MAX_EDGE = 1600;

    public static function store(UploadedFile $file, string $directory, string $disk): string
    {
        $path = $file->getRealPath();
        $image = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('The photo could not be read.');
        }

        $image = self::upright($image, $file);
        $image = self::shrink($image);

        ob_start();
        imagejpeg($image, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $stored = trim($directory, '/').'/'.Str::random(24).'.jpg';
        Storage::disk($disk)->put($stored, $jpeg);

        return $stored;
    }

    /**
     * Phones save portrait photos sideways with a rotation flag; apply it before the flag is discarded.
     */
    private static function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }

    private static function shrink(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $resized ?: $image;
    }
}
