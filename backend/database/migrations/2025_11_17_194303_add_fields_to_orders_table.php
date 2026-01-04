<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Добавляет поля покупателя/получателя, seller_id, delivery/payment и адрес доставки.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        // Для удобства: добавляем колонки только если их ещё нет
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'buyer_first_name')) {
                $table->string('buyer_first_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('orders', 'buyer_last_name')) {
                $table->string('buyer_last_name')->nullable()->after('buyer_first_name');
            }
            if (!Schema::hasColumn('orders', 'buyer_middle_name')) {
                $table->string('buyer_middle_name')->nullable()->after('buyer_last_name');
            }
            if (!Schema::hasColumn('orders', 'buyer_phone')) {
                $table->string('buyer_phone')->nullable()->after('buyer_middle_name');
            }
            if (!Schema::hasColumn('orders', 'buyer_email')) {
                $table->string('buyer_email')->nullable()->after('buyer_phone');
            }

            if (!Schema::hasColumn('orders', 'recipient_first_name')) {
                $table->string('recipient_first_name')->nullable()->after('buyer_email');
            }
            if (!Schema::hasColumn('orders', 'recipient_last_name')) {
                $table->string('recipient_last_name')->nullable()->after('recipient_first_name');
            }
            if (!Schema::hasColumn('orders', 'recipient_middle_name')) {
                $table->string('recipient_middle_name')->nullable()->after('recipient_last_name');
            }
            if (!Schema::hasColumn('orders', 'recipient_phone')) {
                $table->string('recipient_phone')->nullable()->after('recipient_middle_name');
            }

            if (!Schema::hasColumn('orders', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
            }

            if (!Schema::hasColumn('orders', 'delivery_method_id')) {
                $table->unsignedBigInteger('delivery_method_id')->nullable()->after('seller_id');
            }
            if (!Schema::hasColumn('orders', 'delivery_price')) {
                $table->decimal('delivery_price', 10, 2)->nullable()->after('delivery_method_id');
            }
            if (!Schema::hasColumn('orders', 'payment_method_id')) {
                $table->unsignedBigInteger('payment_method_id')->nullable()->after('delivery_price');
            }

            if (!Schema::hasColumn('orders', 'city')) {
                $table->string('city')->nullable()->after('payment_method_id');
            }
            if (!Schema::hasColumn('orders', 'address')) {
                $table->text('address')->nullable()->after('city');
            }
        });

        // Добавляем внешние ключи отдельно и безопасно (если соответствующие столбцы и таблицы существуют).
        // Обёрнуты в try/catch, чтобы не падать если FK уже есть или таблицы/ключи отсутствуют.
        try {
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'delivery_method_id') && Schema::hasTable('delivery_methods')) {
                // Проверим, есть ли уже FK (MySQL даст ошибку, поэтому используем RAW и IGNORE)
                DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_delivery_method_id_foreign` FOREIGN KEY (`delivery_method_id`) REFERENCES `delivery_methods`(`id`) ON DELETE SET NULL');
            }
        } catch (\Throwable $e) {
            // возможно FK уже существует или таблица/ключ не готов — пропускаем
        }

        try {
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'payment_method_id') && Schema::hasTable('payment_methods')) {
                DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL');
            }
        } catch (\Throwable $e) {
            //
        }

        try {
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'seller_id') && Schema::hasTable('users')) {
                DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
            }
        } catch (\Throwable $e) {
            //
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Удаляем FK безопасно (в try/catch)
            try {
                $table->dropForeign(['payment_method_id']);
            } catch (\Throwable $_) {}
            try {
                $table->dropForeign(['delivery_method_id']);
            } catch (\Throwable $_) {}
            try {
                $table->dropForeign(['seller_id']);
            } catch (\Throwable $_) {}

            // Удаляем колонки, если они есть
            if (Schema::hasColumn('orders', 'address')) $table->dropColumn('address');
            if (Schema::hasColumn('orders', 'city')) $table->dropColumn('city');
            if (Schema::hasColumn('orders', 'payment_method_id')) $table->dropColumn('payment_method_id');
            if (Schema::hasColumn('orders', 'delivery_price')) $table->dropColumn('delivery_price');
            if (Schema::hasColumn('orders', 'delivery_method_id')) $table->dropColumn('delivery_method_id');
            if (Schema::hasColumn('orders', 'seller_id')) $table->dropColumn('seller_id');

            if (Schema::hasColumn('orders', 'recipient_phone')) $table->dropColumn('recipient_phone');
            if (Schema::hasColumn('orders', 'recipient_middle_name')) $table->dropColumn('recipient_middle_name');
            if (Schema::hasColumn('orders', 'recipient_last_name')) $table->dropColumn('recipient_last_name');
            if (Schema::hasColumn('orders', 'recipient_first_name')) $table->dropColumn('recipient_first_name');

            if (Schema::hasColumn('orders', 'buyer_email')) $table->dropColumn('buyer_email');
            if (Schema::hasColumn('orders', 'buyer_phone')) $table->dropColumn('buyer_phone');
            if (Schema::hasColumn('orders', 'buyer_middle_name')) $table->dropColumn('buyer_middle_name');
            if (Schema::hasColumn('orders', 'buyer_last_name')) $table->dropColumn('buyer_last_name');
            if (Schema::hasColumn('orders', 'buyer_first_name')) $table->dropColumn('buyer_first_name');
        });
    }
};