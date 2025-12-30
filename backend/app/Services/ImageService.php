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
        $dirRelative = "products/{$productId}";
        Storage::disk('public')->makeDirectory($dirRelative);

        $ext = pathinfo($basename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);

        $sourcePath = ($uploadedFile instanceof \Illuminate\Http\UploadedFile) ? $uploadedFile->getRealPath() : (string)$uploadedFile;

        Log::info("ImageService: generateVariantsAndManifest start for product {$productId}, basename {$basename}, source={$sourcePath}");

        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            $msg = "ImageService: source file missing or unreadable: {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $contents = @file_get_contents($sourcePath);
        if ($contents === false) {
            $msg = "ImageService: failed to read source file contents: {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $srcImg = @imagecreatefromstring($contents);
        if ($srcImg === false) {
            $msg = "ImageService: imagecreatefromstring failed for {$sourcePath}";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);
        $manifestForFile = [];

        foreach ($this->sizes as $size) {
            $targetNameBase = "{$nameWithoutExt}_{$size}";
            $candidates = [];

            if (function_exists('imagewebp')) {
                $candidates['webp'] = "{$targetNameBase}.webp";
            }
            $candidates['fallback'] = "{$targetNameBase}.{$ext}";

            foreach ($candidates as $format => $targetName) {
                $targetPathFull = storage_path("app/public/{$dirRelative}/{$targetName}");

                try {
                    $newW = (int)$size;
                    $ratio = $origW > 0 ? ($origH / $origW) : 1;
                    $newH = max(1, (int) round($newW * $ratio));

                    $dst = imagecreatetruecolor($newW, $newH);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);

                    $resampled = imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    if ($resampled === false) {
                        Log::error("ImageService: imagecopyresampled failed for {$targetName}");
                        imagedestroy($dst);
                        continue;
                    }

                    $saved = false;
                    if ($format === 'webp' && function_exists('imagewebp')) {
                        $saved = @imagewebp($dst, $targetPathFull, $this->quality);
                    } else {
                        $extL = strtolower(pathinfo($targetName, PATHINFO_EXTENSION));
                        if (in_array($extL, ['jpg','jpeg']) && function_exists('imagejpeg')) {
                            $saved = @imagejpeg($dst, $targetPathFull, $this->quality);
                        } elseif ($extL === 'png' && function_exists('imagepng')) {
                            $pngLevel = max(0, min(9, (int) round((100 - $this->quality) / 10)));
                            $saved = @imagepng($dst, $targetPathFull, $pngLevel);
                        } elseif ($extL === 'gif' && function_exists('imagegif')) {
                            $saved = @imagegif($dst, $targetPathFull);
                        } else {
                            if (function_exists('imagewebp')) {
                                $fallbackPath = storage_path("app/public/{$dirRelative}/{$targetNameBase}.webp");
                                $saved = @imagewebp($dst, $fallbackPath, $this->quality);
                                if ($saved) {
                                    $targetName = "{$targetNameBase}.webp";
                                    $targetPathFull = $fallbackPath;
                                }
                            }
                        }
                    }

                    if ($saved) {
                        $relativeUrl = "{$dirRelative}/{$targetName}";
                        if (!isset($manifestForFile[$size])) $manifestForFile[$size] = [];
                        if ($format === 'webp') {
                            $manifestForFile[$size]['webp'] = "/storage/{$relativeUrl}";
                            $manifestForFile[$size]['fallback'] = $manifestForFile[$size]['fallback'] ?? "/storage/{$relativeUrl}";
                        } else {
                            $manifestForFile[$size]['fallback'] = "/storage/{$relativeUrl}";
                        }

                        Log::info("ImageService: saved {$relativeUrl} for product {$productId} size {$size}");
                    } else {
                        Log::warning("ImageService: failed to save {$targetName} for size {$size}");
                    }

                    imagedestroy($dst);
                } catch (\Throwable $e) {
                    Log::error("ImageService: exception while saving {$targetName}: " . $e->getMessage());
                }
            }
        }

        try { imagedestroy($srcImg); } catch (\Throwable $_) {}

        $manifest = [
            $basename => []
        ];
        foreach ($manifestForFile as $size => $formats) {
            $manifest[$basename][(string)$size] = $formats;
        }

        try {
            $placeholder = $this->generatePlaceholderDataUri($contents);
            if ($placeholder) {
                $manifest[$basename]['placeholder'] = $placeholder;
            }
        } catch (\Throwable $e) {
            Log::warning("ImageService: failed to generate placeholder for {$basename}: " . $e->getMessage());
        }

        Log::info("ImageService: finished generateVariantsAndManifest for {$basename}, manifest keys: " . implode(',', array_keys($manifest[$basename] ?? [])));

        return $manifest;
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
}