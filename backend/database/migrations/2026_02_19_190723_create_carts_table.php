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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            
            // Зв'язок з користувачем (null для гостей)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');
            
            // Зв'язок з товаром
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            
            // Кількість товару в кошику
            $table->unsignedInteger('quantity')->default(1);
            
            // Ціна на момент додавання в кошик (зберігаємо, щоб знати ціну, якщо вона зміниться)
            $table->decimal('price', 10, 2);
            
            $table->timestamps();

            // Унікальність: один товар один раз у кошику користувача
            // Це означає, що один користувач не може мати два рядки з одним і тим же товаром
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};