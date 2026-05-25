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
        Schema::table('users', function (Blueprint $table) {
            // Рейтинг продавця (роздрібний продавець)
            $table->unsignedInteger('seller_rating')->default(1000)->after('email'); // unsignedInteger — целое число без отрицательных значений (0 и выше).
            
            // Рейтинг покупця
            $table->unsignedInteger('buyer_rating')->default(1000)->after('seller_rating');
            
            // Рейтинг оптового продавця
            $table->unsignedInteger('wholesale_seller_rating')->default(1000)->after('buyer_rating');
            
            // Рейтинг виробника
            $table->unsignedInteger('manufacturer_rating')->default(1000)->after('wholesale_seller_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_rating',
                'buyer_rating',
                'wholesale_seller_rating',
                'manufacturer_rating',
            ]);
        });
    }
};