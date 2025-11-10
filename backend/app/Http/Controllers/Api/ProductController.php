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
        // Используем `with('category')` для "жадной загрузки".
        // Это решает проблему "N+1" и загружает связанные категории одним запросом.
        // Вызываем наш новый scope `forUser()` и получаем результат
        return Product::with('category')->forUser()->latest()->get();
    }

    private function storeImageAsWebp(UploadedFile $file): ?string
    {
        $fileName = $this->fileService->generateUniqueFilename($file);
        $path = 'products/' . $fileName;

        try {
            // Создаём изображение из загруженного файла
            $image = match ($file->getMimeType()) {
                'image/jpeg' => imagecreatefromjpeg($file->getPathname()),
                'image/png'  => imagecreatefrompng($file->getPathname()),
                'image/gif'  => imagecreatefromgif($file->getPathname()),
                'image/bmp'  => imagecreatefrombmp($file->getPathname()),
                'image/webp' => imagecreatefromwebp($file->getPathname()),
                default => null,
            };

            if (!$image) {
                return null;
            }

            // Включаем альфа-канал для PNG и WebP
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Сохраняем как WebP (качество 80)
            ob_start();
            imagewebp($image, null, 80);
            $webpImageContents = ob_get_clean();
            imagedestroy($image);

            // Сохраняем в Storage
            Storage::disk('public')->put($path, $webpImageContents);

            return $fileName;

        } catch (\Exception $e) {
            // Логируем ошибку, если что-то пошло не так
            logger()->error('File conversion failed: ' . $e->getMessage());
            // Повертаю помилку для користувача
            return response()->json([
                'error' => 'Изображение не удалось обработать: ' . $e->getMessage(),
            ], 422);
        }
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
            
            // Валидация JSON-строк (т.к. они приходят из FormData)
            'video_urls'            => 'nullable|json',
            'properties'            => 'nullable|json',

            // Валидация массива ФАЙЛОВ
            'images'                => 'nullable|array|max:8',
            'images.*'              => 'image|mimes:jpeg,png,gif,bmp,tiff,webp|max:2048', // 2MB
        ]);

        $imageNames = [];
        // 2. Обработка и сохранение изображений
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                // Используем наш конвертер
                $fileName = $this->storeImageAsWebp($file);
                if ($fileName) {
                    $imageNames[] = $fileName;
                }
            }
        }

        // 3. Подготовка данных для создания
        $dataToCreate = $request->except(['images', 'properties', 'video_urls']);
        $dataToCreate['image_url'] = $imageNames;
        $dataToCreate['properties'] = json_decode($request->properties, true) ?? [];
        $dataToCreate['video_urls'] = json_decode($request->video_urls, true) ?? [];

        // 4. Создание товара
        $product = Auth::user()->products()->create($dataToCreate);
        // "Дозагружаем" в ответ связанные данные о категории
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

            // Массив НОВЫХ загружаемых файлов
            'new_images'            => 'nullable|array',
            'new_images.*'          => 'image|mimes:jpeg,png,gif,bmp,tiff,webp|max:2048',
            
            // Массив СТАРЫХ имен файлов, которые нужно сохранить
            'existing_images'       => 'nullable|array',
            'existing_images.*'     => 'string',
        ]);
        
        // Проверяем общее количество
        $totalImages = count($request->input('existing_images', [])) + count($request->file('new_images', []));
        if ($totalImages > 8) {
            return response()->json(['message' => 'Общее количество изображений не может превышать 8.'], 422);
        }
        
        $newImageNames = [];
        // 1. Загружаем новые изображения
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $file) {
                // Используем наш новый конвертер
                $fileName = $this->storeImageAsWebp($file);
                if ($fileName) {
                    $newImageNames[] = $fileName;
                }
            }
        }

        // 2. Собираем финальный список изображений
        $existingImages = $request->input('existing_images', []);
        $finalImageArray = array_merge($existingImages, $newImageNames);

        // 3. Находим и удаляем старые изображения, которых нет в новом списке
        $imagesToDelete = array_diff($product->image_url ?? [], $existingImages);
        foreach ($imagesToDelete as $fileName) {
            // Теперь мы удаляем .webp файлы (и старые .jpg/.png, если они были)
            Storage::disk('public')->delete('products/' . $fileName);
        }

        // 4. Подготовка данных для обновления
        $dataToUpdate = $request->except(['new_images', 'existing_images', 'properties', 'video_urls']);
        $dataToUpdate['image_url'] = $finalImageArray; // Обновляем массив имен
        $dataToUpdate['properties'] = json_decode($request->properties, true) ?? [];
        $dataToUpdate['video_urls'] = json_decode($request->video_urls, true) ?? [];
        
        // 5. Обновляем товар
        $product->update($dataToUpdate);
        $product->load('category');
        
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        // Удаляем все связанные изображения с диска
        if (is_array($product->image_url)) {
            foreach ($product->image_url as $fileName) {
                Storage::disk('public')->delete('products/' . $fileName);
            }
        }

        $product->delete();
        return response()->json(null, 204);
    }

    /**
     * Возвращает список популярных товаров.
     * В реальном проекте здесь была бы сложная логика (по просмотрам, заказам).
     * Для теста мы просто возьмём 8 случайных товаров.
     */
    public function popular()
    {
        return Product::with('category')->inRandomOrder()->take(8)->get();
    }

    /**
     * Возвращает список новинок.
     * Просто берём 8 последних добавленных товаров.
     */
    public function newest()
    {
        return Product::with('category')->latest()->take(8)->get();
    }
}