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
        Schema::table('categories', function (Blueprint $table) {
            // Добавляем поле для ID родительской категории
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');

            // Добавляем новое поле для иконки после поля image_url
            $table->string('icon_url')->nullable()->after('image_url');

            // Добавляем внешний ключ, который ссылается на эту же таблицу
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade'); // Если удаляем родителя, удаляются и все дочерние
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            //
        });
    }
};
