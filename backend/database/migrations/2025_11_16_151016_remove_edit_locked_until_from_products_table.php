<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Удаляет колонку edit_locked_until из таблицы products, если она существует.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'edit_locked_until')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop column safely
                $table->dropColumn('edit_locked_until');
            });
        }
    }

    /**
     * Reverse the migrations.
     * Восстанавливает колонку edit_locked_until как nullable timestamp.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'edit_locked_until')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('edit_locked_until')->nullable()->after('image_variants');
            });
        }
    }
};