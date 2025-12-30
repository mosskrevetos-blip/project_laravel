<?php
// Миграция: добавляет JSON столбец image_variants в products
// Запустите: php artisan migrate

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // JSON поле для хранения manifest-объекта вида:
            // {
            //   "photo.webp": {
            //     "150": { "webp": "/storage/products/37/photo_150.webp", "avif": "/storage/products/37/photo_150.avif", "fallback": "/storage/products/37/photo_150.jpg" },
            //     "400": { ... }
            //   }
            // }
            $table->json('image_variants')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_variants');
        });
    }
};