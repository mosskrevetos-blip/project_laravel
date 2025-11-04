<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- 1. Добавляем импорт DB

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- ШАГ 1: Конвертируем существующие данные ---
        // Мы используем 'cursor()' для перебора записей по одной,
        // чтобы не загружать в память тысячи товаров на работающем сайте.
        DB::table('products')
            ->whereNotNull('image_url') // Находим все товары, где image_url не пустой
            ->cursor()
            ->each(function ($product) {
                // Проверяем, не является ли значение уже JSON-массивом
                $isJson = json_decode($product->image_url);
                if (is_array($isJson)) {
                    return; // Это уже массив, ничего не делаем
                }

                // Это строка. Оборачиваем её в массив и кодируем в JSON.
                // "http://.../img.jpg"  =>  ["http://.../img.jpg"]
                $newJsonValue = json_encode([$product->image_url]);

                // Обновляем запись в базе
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['image_url' => $newJsonValue]);
            });

        // --- ШАГ 2: Теперь безопасно меняем тип колонки ---
        // Все данные теперь в валидном JSON-формате,
        // и MySQL сможет их преобразовать.
        Schema::table('products', function (Blueprint $table) {
            $table->json('image_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // При откате мы не можем безопасно конвертировать массив обратно в строку,
        // поэтому просто меняем тип колонки.
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }
};