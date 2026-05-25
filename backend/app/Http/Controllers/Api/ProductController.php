<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Product;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\ImageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class ProductController extends Controller
{
    use AuthorizesRequests;

    protected $fileService;
    protected $imageService;

    public function __construct(FileService $fileService, ImageService $imageService)
    {
        $this->fileService = $fileService;
        $this->imageService = $imageService;
    }

    public function index()
    {
        return Product::with('category')->forUser()->latest()->get();
    }

    /**
     * Конвертує завантажений файл у webp та зберігає у storage в папку продукту.
     * Повертає ім'я файлу (з розширенням .webp)
     */
    private function storeImageAsWebp(UploadedFile $file, int $productId): string
    {
        $baseName = $this->fileService->generateUniqueFilename($file);
        $fileName = preg_replace('/\\.[^.]+$/', '', $baseName) . '.webp';
        $path = "products/{$productId}/" . $fileName;

        $image = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getPathname()),
            'image/png'  => @imagecreatefrompng($file->getPathname()),
            'image/gif'  => @imagecreatefromgif($file->getPathname()),
            'image/bmp'  => @imagecreatefrombmp($file->getPathname()),
            'image/webp' => @imagecreatefromwebp($file->getPathname()),
            default => null,
        };

        if (!$image) {
            throw new \RuntimeException('Unsupported image type or corrupted file.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        @imagealphablending($image, true);
        @imagesavealpha($image, true);

        $quality = config('product.image_quality', 80);

        ob_start();
        $ok = @imagewebp($image, null, $quality);
        $webpImageContents = ob_get_clean();
        imagedestroy($image);

        if (!$ok || $webpImageContents === false || $webpImageContents === '') {
            throw new \RuntimeException('Failed to encode image to webp.');
        }

        try {
            Storage::disk('public')->makeDirectory("products/{$productId}");
        } catch (\Throwable $e) {
            $realPath = null;
            try {
                $realPath = Storage::disk('public')->path("products/{$productId}");
            } catch (\Throwable $_) {}
            throw new \RuntimeException('Unable to create directory at ' . ($realPath ?: "products/{$productId}") . '. ' . $e->getMessage());
        }

        try {
            Storage::disk('public')->put($path, $webpImageContents);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to write image to storage: ' . $e->getMessage());
        }

        return $fileName;
    }

    /**
     * Зберігає основний файл (webp) та диспетчить задачу на генерацію варіантів.
     * Повертає ім'я основного файлу.
     */
    private function storeImageAndDispatchJob(UploadedFile $file, int $productId): string
    {
        // 1) зберегти основний файл
        $fileName = $this->storeImageAsWebp($file, $productId);

        // 2) dispatch job to generate variants asynchronously — ensure dispatch after DB commit
        try {
            // Use afterCommit so job won't be dispatched before transaction commit
            GenerateImageVariantsJob::dispatch($productId, $fileName)
                ->onQueue('images')
                ->afterCommit();

            Log::info("ProductController: dispatched GenerateImageVariantsJob for product {$productId}, file {$fileName}");
        } catch (\Throwable $e) {
            Log::warning("ProductController: failed to dispatch GenerateImageVariantsJob for product {$productId}, file {$fileName}: " . $e->getMessage());
            // don't throw — generation will be attempted next time or manually
        }

        return $fileName;
    }


    // метод для створення товару
    public function store(Request $request)
    {
        // Авторизація: тільки продавці та адміністратори можуть створювати товари
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'title'                 => 'required|string|max:255',
            'description'           => 'nullable|string',
            'price'                 => 'required|numeric|min:0',
            'sku'                   => 'required|string|unique:products,sku',
            'quantity'              => 'required|integer|min:0',
            'city'                  => 'required|string|max:255',
            'category_id'           => 'required|exists:categories,id',
            'secondary_category_id' => 'nullable|exists:categories,id',
            'video_urls'            => 'nullable|json',
            'properties'            => 'nullable|json',
            'images'                => 'nullable|array|max:' . config('product.max_images_per_product', 8),
            'images.*'              => 'image|mimes:jpeg,png,gif,bmp,tiff,webp|max:' . (config('product.max_image_size_mb', 2) * 1024),
        ]);

        DB::beginTransaction();
        try {
            $dataToCreate = $request->except(['images', 'properties', 'video_urls']);
            $dataToCreate['image_url'] = [];
            $dataToCreate['properties'] = json_decode($request->properties, true) ?? [];
            $dataToCreate['video_urls'] = json_decode($request->video_urls, true) ?? [];
            // По замовчуванню потрібне значення 'pending'
            // Зараз стоять данны для тестування без модерації, але в майбутньому це буде важливо для процесу модерації
            $dataToCreate['moderation_status'] = 'approved';
            $dataToCreate['is_paid'] = true;
            $dataToCreate['is_visible'] = false;
            $dataToCreate['deleted_by_user'] = false;
            $dataToCreate['deleted_by_admin'] = false;

            $product = Auth::user()->products()->create($dataToCreate);

            $imageNames = [];

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    try {
                        $fileName = $this->storeImageAndDispatchJob($file, $product->id);
                        $imageNames[] = $fileName;
                    } catch (\Throwable $e) {
                        // cleanup on failure: remove product files and the product record
                        try { Storage::disk('public')->deleteDirectory("products/{$product->id}"); } catch (\Throwable $_) {}
                        try { $product->delete(); } catch (\Throwable $_) {}

                        try {
                            logger()->error('Image processing failed while creating product: ' . $e->getMessage(), [
                                'userId' => Auth::id(),
                                'exception' => $e,
                            ]);
                        } catch (\Throwable $logEx) {
                            error_log('Logger failed: ' . $logEx->getMessage());
                            error_log('Original image processing error: ' . $e->getMessage());
                        }

                        DB::rollBack();
                        return response()->json(['message' => 'Помилка обробки зображення: ' . $e->getMessage()], 422);
                    }
                }
            }

            $product->image_url = $imageNames;
            $product->save();
            $product->load('category');

            DB::commit();
            return response()->json($product, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("ProductController@store: exception: " . $e->getMessage());
            return response()->json(['message' => 'Не вдалося створити товар', 'error' => $e->getMessage()], 500);
        }
    }


    // метод для отримання інформації про конкретний товар
    public function show(Request $request, Product $product, $slug = null)
    {
        // Загружаем отношения
        $product->load('category', 'user');

        // ✅ Если запрос через API — не делаем редирект
        if ($request->expectsJson()) {
            return response()->json($product);
        }

        // ✅ Для браузерных запросов — редирект на правильный URL
        if ($slug !== $product->slug) {
            return redirect("/products/{$product->id}-{$product->slug}", 301);
        }

        return response()->json($product);
    }


    // метод для оновлення товару
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        if ($product->deleted_by_user) {
            return response()->json(['message' => 'Не можна редагувати видалений товар'], 403);
        }

        $validated = $request->validate([
            'title'                 => 'required|string|max:255',
            'sku'                   => 'required|string|unique:products,sku,'.$product->id,
            'price'                 => 'required|numeric|min:0',
            'quantity'              => 'required|integer|min:0',
            'city'                  => 'required|string|max:255',
            'category_id'           => 'required|exists:categories,id',
            'secondary_category_id' => 'nullable|exists:categories,id',
            'description'           => 'nullable|string',
            'properties'            => 'nullable|json',
            'video_urls'            => 'nullable|json',
            'new_images'            => 'nullable|array',
            'new_images.*'          => 'image|mimes:jpeg,png,gif,bmp,tiff,webp|max:' . (config('product.max_image_size_mb', 2) * 1024),
            'existing_images'       => 'nullable|array',
            'existing_images.*'     => 'string',
        ]);

        $existingImages = $request->input('existing_images', []);
        $newFiles = $request->file('new_images') ?: [];
        $totalImages = count($existingImages) + count($newFiles);
        if ($totalImages > config('product.max_images_per_product', 8)) {
            return response()->json(['message' => 'Загальна кількість зображень не може перевищувати ' . config('product.max_images_per_product', 8) . '.'], 422);
        }

        DB::beginTransaction();
        try {
            $newImageNames = [];

            // 1) Save newly uploaded files and dispatch jobs for them
            if ($request->hasFile('new_images')) {
                foreach ($request->file('new_images') as $file) {
                    try {
                        $fileName = $this->storeImageAndDispatchJob($file, $product->id);
                        $newImageNames[] = $fileName;
                    } catch (\Throwable $e) {
                        try {
                            foreach ($newImageNames as $fn) {
                                $this->deleteImageAndVariants($product->id, $fn);
                            }
                        } catch (\Throwable $_) {}
                        DB::rollBack();
                        return response()->json(['message' => 'Помилка обробки нового зображення: ' . $e->getMessage()], 422);
                    }
                }
            }

            // 2) Delete removed images and their manifest entries
            $currentProductImages = is_array($product->image_url) ? $product->image_url : ($product->image_url ? (array)$product->image_url : []);
            $imagesToDelete = array_diff($currentProductImages, $existingImages);

            if (!empty($imagesToDelete)) {
                foreach ($imagesToDelete as $fileName) {
                    try {
                        $this->deleteImageAndVariants($product->id, $fileName);
                    } catch (\Throwable $_) {}
                    try {
                        $variants = (array) $product->image_variants;
                        if (isset($variants[$fileName])) {
                            unset($variants[$fileName]);
                            $product->image_variants = $variants;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Failed to remove manifest entry for {$fileName}: " . $e->getMessage());
                    }
                }
            }

            $finalImageArray = array_merge($existingImages, $newImageNames);

            $dataToUpdate = $request->except(['new_images', 'existing_images', 'properties', 'video_urls']);
            $dataToUpdate['image_url'] = $finalImageArray;
            $dataToUpdate['properties'] = json_decode($request->properties, true) ?? [];
            $dataToUpdate['video_urls'] = json_decode($request->video_urls, true) ?? [];

            // Merge manifests will happen asynchronously by jobs
            $product->update($dataToUpdate);
            $product->load('category');

            DB::commit();
            return response()->json($product);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("ProductController@update: exception: " . $e->getMessage());
            return response()->json(['message' => 'Не вдалося оновити товар', 'error' => $e->getMessage()], 500);
        }
    }

    private function deleteImageAndVariants(int $productId, string $fileName): void
    {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        $sizes = [150, 400, 800, 1200, 2000];

        try { Storage::disk('public')->delete("products/{$productId}/{$fileName}"); } catch (\Throwable $_) {}

        foreach ($sizes as $s) {
            $fn = "{$name}_{$s}.{$ext}";
            try { Storage::disk('public')->delete("products/{$productId}/{$fn}"); } catch (\Throwable $_) {}
        }

        if (strtolower($ext) !== 'webp') {
            foreach ($sizes as $s) {
                $fn = "{$name}_{$s}.webp";
                try { Storage::disk('public')->delete("products/{$productId}/{$fn}"); } catch (\Throwable $_) {}
            }
            try { Storage::disk('public')->delete("products/{$productId}/{$name}.webp"); } catch (\Throwable $_) {}
        }
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        try {
            Storage::disk('public')->deleteDirectory('products/' . $product->id);
        } catch (\Throwable $_) {}

        $product->delete();
        return response()->json(null, 204);
    }

    public function popular()
    {
        return Product::with('category')->inRandomOrder()->take(8)->get();
    }

    public function newest()
    {
        return Product::with('category')->latest()->take(8)->get();
    }
}