<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class CategoryController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index() // Убираем Request $request, он здесь не нужен
    {
        // Просто возвращаем все категории. 
        // Проверку прав за нас уже сделал middleware в файле routes/api.php.
        // Добавляем ->withCount('products'), чтобы посчитать товары в каждой категории
        return Category::withCount('products')->latest()->get();
    }

    public function store(Request $request)
    {

        // --- НАЧАЛО ДИАГНОСТИЧЕСКОГО КОДА ---
        // Log::info('--- CATEGORY STORE REQUEST START ---');
        // $user = Auth::user(); // Пытаемся получить пользователя
        // if ($user) {
        //     Log::info('User Authenticated!', [
        //         'id' => $user->id, 
        //         'name' => $user->name,
        //         'roles' => $user->roles->pluck('slug')->toArray()
        //     ]);
        // } else {
        //     Log::info('User is NOT Authenticated (Auth::user() is null).');
        // }
        // Log::info('--- CATEGORY STORE REQUEST END ---');
        // --- КОНЕЦ ДИАГНОСТИЧЕСКОГО КОДА ---


        // 1. Проверка прав. 
        // Laravel уже сделал это за нас благодаря middleware в файле routes/api.php,
        // но для ясности можно продублировать здесь.
        // Метод authorize вызовет метод create() из вашей CategoryPolicy.
        $this->authorize('create', Category::class);

        // 2. Валидация входящих данных.
        // Адаптируйте эти правила под поля вашей таблицы 'categories'.
        $validated = $request->validate([
        // Поле 'title' (название)
        // - required: обязательно для заполнения
        // - string: должно быть строкой
        // - max:255: не длиннее 255 символов
        // - unique:categories,title: должно быть уникальным в таблице 'categories' в колонке 'title'
        'title'       => 'required|string|max:255|unique:categories,title',

        // Поле 'description' (описание)
        // - nullable: необязательно для заполнения
        // - string: если передано, должно быть строкой
        'description' => 'nullable|string',

        // Поле 'image_url' (ссылка на изображение)
        // - nullable: необязательно для заполнения
        // - url: если передано, должно быть в формате URL-адреса
        'image_url'   => 'nullable|url',

        'icon_url'    => 'nullable|url',

        'parent_id'   => 'nullable|exists:categories,id',
    ]);

        // 3. Создание категории напрямую через модель Category.
        $category = Category::create($validated);

        // 4. Возврат успешного ответа.
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255|unique:categories,title,'.$category->id,
            'description' => 'nullable|string',
            'image_url'   => 'nullable|url',
            'icon_url'    => 'nullable|url',
            'parent_id'   => 'nullable|exists:categories,id',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }

    public function getAttributes(Category $category)
    {
        // --- НАЧАЛО ДИАГНОСТИЧЕСКОГО КОДА ---
        Log::info('--- GET ATTRIBUTES FOR CATEGORY ---');
        Log::info('Запрошена категория:', ['id' => $category->id, 'title' => $category->title]);

        // Выполняем запрос
        $attributes = $category->attributes()->with('options')->get();

        Log::info('Найдено атрибутов:', ['count' => $attributes->count()]);
        Log::info('Данные атрибутов:', $attributes->toArray());
        Log::info('--- GET ATTRIBUTES END ---');
        // --- КОНЕЦ ДИАГНОСТИЧЕСКОГО КОДА ---

        return $attributes;
    }

    /**
     * Предлагает категории на основе названия товара.
     */
    public function suggest(Request $request)
    {
        $searchTerm = $request->query('search');

        // Если поисковый запрос пуст, возвращаем все категории
        if (empty($searchTerm)) {
            return Category::latest()->get();
        }

        // 1. Находим ID категорий, в которых есть товары с похожим названием
        $categoryIds = Product::where('title', 'LIKE', "%{$searchTerm}%")
            ->distinct()
            ->pluck('category_id');

        // 2. Если нашли хотя бы одну, возвращаем эти категории
        if ($categoryIds->isNotEmpty()) {
            return Category::whereIn('id', $categoryIds)->get();
        }

        // 3. Если ничего не нашли, возвращаем все категории в качестве запасного варианта
        return Category::latest()->get();
    }
}
