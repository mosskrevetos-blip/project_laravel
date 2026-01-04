<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateImageVariantsJob
 *
 * Асинхронно генерирует варианты для одного сохранённого файла и сливает manifest в продукт.
 * Отдельная логика снятия серверных флагов была удалена — job отвечает только за создание вариантов и merge manifest.
 */
class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $productId;
    public string $basename;
    public int $tries = 3;
    public int $timeout = 1200;

    public function __construct(int $productId, string $basename)
    {
        $this->productId = $productId;
        $this->basename = $basename;
    }

    public function handle(ImageService $imageService)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            Log::info("GenerateImageVariantsJob: starting for product {$this->productId}, file {$this->basename}");

            $sourcePath = storage_path("app/public/products/{$this->productId}/{$this->basename}");

            // generate manifest for this file
            $manifest = $imageService->generateVariantsAndManifest($sourcePath, $this->productId, $this->basename);

            if (is_array($manifest) && count($manifest) > 0) {
                $product = Product::find($this->productId);
                if ($product) {
                    $existing = (array) $product->image_variants;
                    $merged = array_merge($existing, $manifest);
                    $product->image_variants = $merged;
                    $product->save();

                    Log::info("GenerateImageVariantsJob: manifest merged for product {$this->productId}, file {$this->basename}");
                    Log::info('GenerateImageVariantsJob: product '.$this->productId.' variants keys after merge: ' . implode(',', array_keys((array)$product->image_variants)));
                } else {
                    Log::warning("GenerateImageVariantsJob: product {$this->productId} not found");
                }
            } else {
                Log::warning("GenerateImageVariantsJob: empty manifest returned for product {$this->productId}, file {$this->basename}");
            }
        } catch (\Throwable $e) {
            Log::error("GenerateImageVariantsJob: exception for product {$this->productId}, file {$this->basename}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("GenerateImageVariantsJob failed for product {$this->productId}, file {$this->basename}: " . $exception->getMessage());
    }
}