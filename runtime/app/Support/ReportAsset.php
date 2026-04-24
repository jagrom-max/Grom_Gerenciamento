<?php

namespace App\Support;

class ReportAsset
{
    public static function dataUri(string $relativePath): string
    {
        $absolutePath = self::resolveOptimizedPath($relativePath);

        if (! is_file($absolutePath)) {
            return asset($relativePath);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $binary = file_get_contents($absolutePath);

        if ($binary === false) {
            return asset($relativePath);
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($binary));
    }

    private static function resolveOptimizedPath(string $relativePath): string
    {
        $optimizedPath = self::optimizedPdfVariantPath($relativePath);

        if ($optimizedPath !== null && is_file(public_path($optimizedPath))) {
            return public_path($optimizedPath);
        }

        return public_path($relativePath);
    }

    private static function optimizedPdfVariantPath(string $relativePath): ?string
    {
        $directory = dirname($relativePath);
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);

        if ($directory === '.' || $directory === '') {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $filename . '_pdf.' . $extension;
    }
}
