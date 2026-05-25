<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) добавить колонку nullable (без constrained сразу)
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('seller_id');
            $table->index(['buyer_id', 'seller_id', 'product_id']);
        });

        // 2) попытаться заполнить product_id из messages (берём MIN(product_id) по conversation)
        // Важно: messages.product_id должен быть не null хотя бы в одном сообщении
        DB::statement("
            UPDATE conversations c
            JOIN (
                SELECT conversation_id, MIN(product_id) AS product_id
                FROM messages
                WHERE product_id IS NOT NULL
                GROUP BY conversation_id
            ) m ON m.conversation_id = c.id
            SET c.product_id = m.product_id
            WHERE c.product_id IS NULL
        ");

        // 3) проверить, остались ли conversations без product_id
        $missing = (int) DB::table('conversations')->whereNull('product_id')->count();
        if ($missing > 0) {
            // Если не хочешь удалять — можно оставить nullable и закончить.
            // Но ты хочешь "product_id всегда", поэтому лучше упасть с понятной ошибкой.
            throw new RuntimeException("Cannot migrate: {$missing} conversations have NULL product_id. Fill or delete them before making product_id required.");
        }

        // 4) теперь можно добавлять FK и сделать NOT NULL
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });

        // MySQL требует отдельного шага для изменения nullable -> not null
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });

        // (опционально, но очень полезно) уникальность "один чат на buyer+seller+product"
        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['buyer_id', 'seller_id', 'product_id'], 'conversations_buyer_seller_product_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_buyer_seller_product_unique');
            $table->dropForeign(['product_id']);
            $table->dropIndex(['buyer_id', 'seller_id', 'product_id']);
            $table->dropColumn('product_id');
        });
    }
};