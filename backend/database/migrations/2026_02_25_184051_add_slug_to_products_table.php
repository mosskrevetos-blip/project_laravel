<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Додою колонку slug без унікального індексу, щоб уникнути проблем з існуючими даними
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        // Генеруємо slug для існуючих товарів
        $products = DB::table('products')->get();
        
        foreach ($products as $product) {
            $slug = Str::slug($product->title);
            $originalSlug = $slug;
            $count = 1;

            // Перевірка унікальності slug
            while (DB::table('products')->where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }

            DB::table('products')
                ->where('id', $product->id)
                ->update(['slug' => $slug]);
        }

        // Додаємо унікальний індекс ПІСЛЯ генерації slug
        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']); // Видаляємо індекс
            $table->dropColumn('slug');    // Видаляємо колонку
        });
    }
};