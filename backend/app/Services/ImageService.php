<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * ImageService (GD implementation) — обновлённый (безопасная LQIP генерация)
 *
 * - generateVariantsAndManifest(...) генерирует варианты изображений (150, 400, 800, 1200, 2000)
 *   и возвращает "manifest" с реальными URL'ами (ключи по basename.ext).
 * - дополнительно генерируется мелкая placeholder‑картинка (LQIP) в виде data:image/...;base64,...
 * - реализация использует GD (imagecreatefromstring, imagewebp, imagejpeg, imagepng) — у вас в контейнере есть расширение gd.
 *
 * Returned manifest structure:
 * [
 *   "basename.webp" => [
 *      "150" => [ "webp" => "/storage/products/37/basename_150.webp", "fallback" => "/storage/products/37/basename_150.webp" ],
 *      "400" => [ ... ],
 *      "placeholder" => "data:image/webp;base64,...."
 *   ],
 * ]
 *
 * Note: AVIF not generated (GD usually doesn't support). Use libvips/imagick if needed later.
 */
class ImageService
{
    protected array $sizes = [
        150,
        400,
        800,
        1200,
        2000,
    ];

    // Quality for saving (jpeg/webp)
    protected int $quality;

    public function __construct()
    {
        $this->quality = (int) config('product.image_quality', 80);
    }

    /**
     * Create resized variants and return manifest.
     *
     * @param \Illuminate\Http\UploadedFile|string $uploadedFile
     * @param int $productId
     * @param string $basename
     * @return array manifest
     *
     * @throws \RuntimeException
     */
    public function generateVariantsAndManifest($uploadedFile, int $productId, string $basename): array
    {
        return $this->generateVariantsAndManifestForDirectory(
            uploadedFile: $uploadedFile,
            directoryRelative: "products/{$productId}",
            basename: $basename
        );
    }


    /**
     * Generate LQIP placeholder using a temp file for encoding (safer than imagewebp(..., null))
     *
     * @param string $sourceContents
     * @param int $targetWidth
     * @return string|null
     */
    protected function generatePlaceholderDataUri(string $sourceContents, int $targetWidth = 20): ?string
    {
        $src = @imagecreatefromstring($sourceContents);
        if ($src === false) return null;

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            return null;
        }

        $ratio = $h / $w;
        $tw = max(1, (int)$targetWidth);
        $th = max(1, (int)round($tw * $ratio));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);

        $res = imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        if ($res === false) {
            imagedestroy($dst);
            imagedestroy($src);
            return null;
        }

        $binary = '';
        $mime = '';

        // Use temporary file to store encoded image then read it
        $tmpPath = tempnam(sys_get_temp_dir(), 'lqip_');
        if ($tmpPath === false) {
            imagedestroy($dst);
            imagedestroy($src);
            return null;
        }

        try {
            if (function_exists('imagewebp')) {
                $ok = @imagewebp($dst, $tmpPath, max(10, min(60, (int)round($this->quality / 2))));
                $mime = 'image/webp';
            } elseif (function_exists('imagejpeg')) {
                $ok = @imagejpeg($dst, $tmpPath, 60);
                $mime = 'image/jpeg';
            } else {
                $ok = false;
            }

            if ($ok && file_exists($tmpPath)) {
                $binary = @file_get_contents($tmpPath);
            } else {
                $binary = '';
            }
        } catch (\Throwable $e) {
            $binary = '';
            Log::warning('ImageService: error while encoding placeholder: ' . $e->getMessage());
        } finally {
            // cleanup temp file
            try { @unlink($tmpPath); } catch (\Throwable $_) {}
            imagedestroy($dst);
            imagedestroy($src);
        }

        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }


    public function generateVariantsAndManifestForDirectory($uploadedFile, string $directoryRelative, string $basename): array
    {
        Storage::disk('public')->makeDirectory($directoryRelative);

        $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
        $sourcePath = $uploadedFile instanceof UploadedFile ? $uploadedFile->getRealPath() : (string) $uploadedFile;

        Log::info("ImageService: start generateVariantsAndManifestForDirectory", [
            'directory' => $directoryRelative,
            'basename' => $basename,
            'source' => $sourcePath,
        ]);

        if (!$sourcePath || !file_exists($sourcePath) || !is_readable($sourcePath)) {
            $msg = "ImageService: source file missing or unreadable: {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $contents = @file_get_contents($sourcePath);
        if ($contents === false || $contents === '') {
            $msg = "ImageService: failed to read source file contents: {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        [$origW, $origH, $mime] = $this->validateImageBinary($contents, $sourcePath);

        $srcImg = @imagecreatefromstring($contents);
        if ($srcImg === false) {
            $msg = "ImageService: imagecreatefromstring failed after validation for {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        if (!function_exists('imagewebp')) {
            imagedestroy($srcImg);
            $msg = "ImageService: GD webp support is not available (imagewebp missing).";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $manifestForFile = [];

        try {
            foreach ($this->sizes as $size) {
                $newW = (int) $size;
                $ratio = $origW > 0 ? ($origH / $origW) : 1;
                $newH = max(1, (int) round($newW * $ratio));

                $dst = imagecreatetruecolor($newW, $newH);
                if ($dst === false) {
                    Log::warning("ImageService: imagecreatetruecolor failed", ['size' => $size]);
                    continue;
                }

                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);

                $resampled = imagecopyresampled(
                    $dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH
                );

                if ($resampled === false) {
                    imagedestroy($dst);
                    continue;
                }

                $targetName = "{$nameWithoutExt}_{$size}.webp";
                $targetPathFull = storage_path("app/public/{$directoryRelative}/{$targetName}");

                $saved = @imagewebp($dst, $targetPathFull, $this->quality);
                imagedestroy($dst);

                if (!$saved) {
                    continue;
                }

                $relativeUrl = "{$directoryRelative}/{$targetName}";
                $manifestForFile[(string)$size] = [
                    'webp' => "/storage/{$relativeUrl}",
                    'fallback' => "/storage/{$relativeUrl}",
                ];
            }
        } finally {
            imagedestroy($srcImg);
        }

        $manifest = [
            $basename => $manifestForFile,
        ];

        try {
            $placeholder = $this->generatePlaceholderDataUri($contents);
            if ($placeholder) {
                $manifest[$basename]['placeholder'] = $placeholder;
            }
        } catch (\Throwable $e) {
            Log::warning("ImageService: failed to generate placeholder", [
                'basename' => $basename,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("ImageService: finished generateVariantsAndManifestForDirectory", [
            'basename' => $basename,
            'keys' => array_keys($manifest[$basename] ?? []),
            'source_mime' => $mime,
            'source_w' => $origW,
            'source_h' => $origH,
            'directory' => $directoryRelative,
        ]);

        return $manifest;
    }

    private function validateImageBinary(string $contents, string $sourcePath = ''): array
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            throw new \RuntimeException("ImageService: invalid image binary. Source: {$sourcePath}");
        }

        $width = (int)($info[0] ?? 0);
        $height = (int)($info[1] ?? 0);
        $mime = (string)($info['mime'] ?? '');

        if ($width <= 0 || $height <= 0) {
            throw new \RuntimeException("ImageService: invalid image dimensions. Source: {$sourcePath}");
        }

        $allowed = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/tiff',
        ];

        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException("ImageService: unsupported mime {$mime}. Source: {$sourcePath}");
        }

        // защита от image bombs (подстрой при необходимости)
        $maxPixels = (int) config('product.max_image_pixels', 40000000); // 40 MP
        if (($width * $height) > $maxPixels) {
            throw new \RuntimeException("ImageService: image too large by pixels ({$width}x{$height}). Source: {$sourcePath}");
        }

        return [$width, $height, $mime];
    }
}