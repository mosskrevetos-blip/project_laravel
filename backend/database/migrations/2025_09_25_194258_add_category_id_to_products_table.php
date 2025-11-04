<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
        // Добавляем поле для ID категории.
        // `constrained` автоматически свяжет его с таблицей `categories`.
        // `nullOnDelete` означает, что если категория удалится, у товара это поле станет NULL (товар станет "без категории").
        $table->foreignId('category_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
