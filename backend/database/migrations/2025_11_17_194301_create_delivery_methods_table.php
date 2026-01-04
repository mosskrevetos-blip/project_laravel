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
        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Наименование, напр. "Курьер по городу"
            $table->string('slug')->unique(); // machine-friendly
            $table->enum('type', ['courier', 'branch', 'locker'])->default('courier');
            $table->decimal('price', 10, 2)->nullable(); // базовая стоимость (может быть null для расчётов)
            $table->boolean('active')->default(true);
            $table->text('meta')->nullable(); // JSON для доп настроек (например, список отделений)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_methods');
    }
};