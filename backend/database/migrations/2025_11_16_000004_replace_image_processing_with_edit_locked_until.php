<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Удаляем image_processing, если есть
            if (Schema::hasColumn('products', 'image_processing')) {
                $table->dropColumn('image_processing');
            }

            // Добавляем поле для блокировки редактирования (nullable timestamp)
            if (!Schema::hasColumn('products', 'edit_locked_until')) {
                $table->timestamp('edit_locked_until')->nullable()->after('image_variants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'edit_locked_until')) {
                $table->dropColumn('edit_locked_until');
            }
            if (!Schema::hasColumn('products', 'image_processing')) {
                $table->boolean('image_processing')->default(false)->after('image_variants');
            }
        });
    }
};