<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        // 1) Удаляем устаревшие колонки, если они существуют
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
            if (Schema::hasColumn('orders', 'customer_email')) {
                $table->dropColumn('customer_email');
            }
            // delivery_price возможно уже присутствует — удалим её (если больше не нужна)
            if (Schema::hasColumn('orders', 'delivery_price')) {
                $table->dropColumn('delivery_price');
            }
        });

        // 2) Добавляем новые колонки для оплаты и трекинга (только если их нет)
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_status')) {
                // Используем enum для удобства, если СУБД поддерживает
                $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('total_price');
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('address');
            }
            if (!Schema::hasColumn('orders', 'carrier')) {
                $table->string('carrier')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'estimated_delivery_date')) {
                $table->dateTime('estimated_delivery_date')->nullable()->after('carrier');
            }
        });
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
            // Удаляем добавленные поля (если есть)
            if (Schema::hasColumn('orders', 'estimated_delivery_date')) {
                $table->dropColumn('estimated_delivery_date');
            }
            if (Schema::hasColumn('orders', 'carrier')) {
                $table->dropColumn('carrier');
            }
            if (Schema::hasColumn('orders', 'tracking_number')) {
                $table->dropColumn('tracking_number');
            }
            if (Schema::hasColumn('orders', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('orders', 'payment_status')) {
                // Для некоторых СУБД dropColumn корректно удалит enum
                $table->dropColumn('payment_status');
            }

            // Восстанавливаем старые колонки (если захотите откатиться)
            if (!Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('orders', 'delivery_price')) {
                $table->decimal('delivery_price', 10, 2)->nullable()->after('delivery_method_id');
            }
        });
    }
};