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
            $table->string('moderation_status')->default('pending'); // модерація
            $table->boolean('is_paid')->default(false);              // оплата
            $table->boolean('is_visible')->default(true);           // відображення
            $table->boolean('deleted_by_user')->default(false);     // видалено користувачем
            $table->boolean('deleted_by_admin')->default(false);    // видалено адміністратором
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
