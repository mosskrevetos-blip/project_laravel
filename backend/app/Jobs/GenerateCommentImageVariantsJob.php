<?php

namespace App\Jobs;

use App\Models\ProductCommentMedia;
use App\Services\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCommentImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $mediaId;
    public int $tries = 3;
    public int $timeout = 1200;

    public function __construct(int $mediaId)
    {
        $this->mediaId = $mediaId;
    }

    public function handle(ImageService $imageService): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $media = ProductCommentMedia::query()->find($this->mediaId);
        if (!$media) {
            Log::warning("GenerateCommentImageVariantsJob: media not found id={$this->mediaId}");
            return;
        }

        if ($media->type !== 'image' || !$media->path || !$media->comment_id) {
            Log::warning("GenerateCommentImageVariantsJob: invalid media payload", [
                'media_id' => $media->id,
                'type' => $media->type,
                'path' => $media->path,
                'comment_id' => $media->comment_id,
            ]);
            return;
        }

        $sourcePath = storage_path("app/public/comments/{$media->comment_id}/{$media->path}");

        try {
            $manifest = $imageService->generateVariantsAndManifestForDirectory(
                uploadedFile: $sourcePath,
                directoryRelative: "comments/{$media->comment_id}",
                basename: $media->path
            );

            $media->variants = $manifest[$media->path] ?? null;
            $media->save();

            Log::info("GenerateCommentImageVariantsJob: variants generated", [
                'media_id' => $media->id,
                'comment_id' => $media->comment_id,
                'basename' => $media->path,
                'keys' => array_keys((array)$media->variants),
            ]);
        } catch (\Throwable $e) {
            Log::error("GenerateCommentImageVariantsJob: failed", [
                'media_id' => $media->id,
                'comment_id' => $media->comment_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateCommentImageVariantsJob failed for media {$this->mediaId}: {$exception->getMessage()}");
    }
}