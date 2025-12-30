<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // nullable false, default false — конструктируем как tinyint/bool
            $table->boolean('image_processing')->default(false)->after('image_variants');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image_processing')) {
                $table->dropColumn('image_processing');
            }
        });
    }
};