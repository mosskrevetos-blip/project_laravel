<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_comment_media', function (Blueprint $table) {
            $table->json('variants')->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('product_comment_media', function (Blueprint $table) {
            $table->dropColumn('variants');
        });
    }
};