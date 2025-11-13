<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;

    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function index() // Request $request больше не нужен
    {
        return Product::with('category')->forUser()->latest()->get();
    }

    /**
     * Конвертирует загруженный файл в webp и сохраняет в storage в папку продукта.
     *
     * @param UploadedFile $file
     * @param int $productId
     * @return string  — возвращает имя файла (с расширением .webp)
     *
     * @throws \RuntimeException при ошибке конвертации/сохранения
     */
    private function storeImageAsWebp(UploadedFile $file, int $productId): string
    {
        // Генерируем имя и гарантируем расширение .webp
        $baseName = $this->fileService->generateUniqueFilename($file);
        $fileName = preg_replace('/\\.[^.]+$/', '', $baseName) . '.webp';
        $path = "products/{$productId}/" . $fileName;

        // Попробуем создать ресурс изображения
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

        // Включаем альфа-канал (если применимо)
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        @imagealphablending($image, true);
        @imagesavealpha($image, true);

        // Сохраняем как WebP (качество из конфигурации)
        $quality = config('product.image_quality', 80);

        ob_start();
        $ok = @imagewebp($image, null, $quality);
        $webpImageContents = ob_get_clean();
        imagedestroy($image);

        if (!$ok || $webpImageContents === false) {
            throw new \RuntimeException('Failed to encode image to webp.');
        }

        // Перед записью убеждаемся, что директория существует (и если не может быть создана — бросаем читаемую ошибку)
        try {
            // makeDirectory может бросить исключение при недостатке прав
            Storage::disk('public')->makeDirectory("products/{$productId}");
        } catch (\Throwable $e) {
            $realPath = null;
            try {
                $realPath = Storage::disk('public')->path("products/{$productId}");
            } catch (\Throwable $_) {}
            throw new \RuntimeException('Unable to create directory at ' . ($realPath ?: "products/{$productId}") . '. ' . $e->getMessage());
        }

        // Сохраняем в Storage (public disk)
        try {
            Storage::disk('public')->put($path, $webpImageContents);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to write image to storage: ' . $e->getMessage());
        }

        return $fileName;
    }

    public function store(Request $request)
    {
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

        // 1) Создаём запись товара без изображений (чтобы получить id)
        $dataToCreate = $request->except(['images', 'properties', 'video_urls']);
        $dataToCreate['image_url'] = [];
        $dataToCreate['properties'] = json_decode($request->properties, true) ?? [];
        $dataToCreate['video_urls'] = json_decode($request->video_urls, true) ?? [];

        $product = Auth::user()->products()->create($dataToCreate);

        // 2) Обрабатываем изображения и сохраняем в папку продукта
        $imageNames = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                try {
                    $fileName = $this->storeImageAsWebp($file, $product->id);
                    $imageNames[] = $fileName;
                } catch (\Throwable $e) {
                    // при ошибке — стараемся очистить, удалить товар, логировать безопасно и вернуть понятный ответ

                    // попытка удалить файл/папку (обёрнуто в try/catch, т.к. может вызвать исключение при правах)
                    try {
                        Storage::disk('public')->deleteDirectory("products/{$product->id}");
                    } catch (\Throwable $_) {
                        // ничего — не ломаем дальнейшую обработку
                    }

                    try {
                        $product->delete();
                    } catch (\Throwable $_) {
                        // пропускаем
                    }

                    // Безопасное логирование: если logger не сможет писать (проблемы с правами), не ломаем ответ
                    try {
                        logger()->error('Image processing failed while creating product: ' . $e->getMessage(), [
                            'userId' => Auth::id(),
                            'exception' => $e,
                        ]);
                    } catch (\Throwable $logEx) {
                        // fallback — пишем в stderr (в docker/k8s это попадёт в логи контейнера)
                        error_log('Logger failed: ' . $logEx->getMessage());
                        error_log('Original image processing error: ' . $e->getMessage());
                    }

                    return response()->json(['message' => 'Ошибка обработки изображения: ' . $e->getMessage()], 422);
                }
            }
        }

        // 3) Обновляем товар списком изображений
        $product->image_url = $imageNames;
        $product->save();
        $product->load('category');

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product;
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

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
            return response()->json(['message' => 'Общее количество изображений не может превышать ' . config('product.max_images_per_product', 8) . '.'], 422);
        }

        $newImageNames = [];
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $file) {
                try {
                    $fileName = $this->storeImageAsWebp($file, $product->id);
                    $newImageNames[] = $fileName;
                } catch (\Throwable $e) {
                    // При ошибке — удалить только вновь сохранённые для этого запроса
                    try {
                        foreach ($newImageNames as $fn) {
                            Storage::disk('public')->delete("products/{$product->id}/{$fn}");
                        }
                    } catch (\Throwable $_) {}
                    try {
                        logger()->error('Image processing failed while updating product #' . $product->id . ': ' . $e->getMessage(), [
                            'exception' => $e,
                        ]);
                    } catch (\Throwable $logEx) {
                        error_log('Logger failed: ' . $logEx->getMessage());
                        error_log('Original image processing error: ' . $e->getMessage());
                    }
                    return response()->json(['message' => 'Ошибка обработки нового изображения: ' . $e->getMessage()], 422);
                }
            }
        }

        $finalImageArray = array_merge($existingImages, $newImageNames);

        $imagesToDelete = array_diff($product->image_url ?? [], $existingImages);
        foreach ($imagesToDelete as $fileName) {
            try {
                Storage::disk('public')->delete("products/{$product->id}/" . $fileName);
            } catch (\Throwable $_) {}
        }

        $dataToUpdate = $request->except(['new_images', 'existing_images', 'properties', 'video_urls']);
        $dataToUpdate['image_url'] = $finalImageArray;
        $dataToUpdate['properties'] = json_decode($request->properties, true) ?? [];
        $dataToUpdate['video_urls'] = json_decode($request->video_urls, true) ?? [];

        $product->update($dataToUpdate);
        $product->load('category');

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        try {
            Storage::disk('public')->deleteDirectory('products/' . $product->id);
        } catch (\Throwable $_) {
            // ignore
        }

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