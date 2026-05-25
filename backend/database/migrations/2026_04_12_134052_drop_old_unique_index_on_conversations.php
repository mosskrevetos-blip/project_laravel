<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexName = 'conversations_buyer_id_seller_id_unique';

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'conversations')
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            Schema::table('conversations', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    public function down(): void
    {
        $indexName = 'conversations_buyer_id_seller_id_unique';

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'conversations')
            ->where('index_name', $indexName)
            ->exists();

        if (!$exists) {
            Schema::table('conversations', function (Blueprint $table) use ($indexName) {
                $table->unique(['buyer_id', 'seller_id'], $indexName);
            });
        }
    }
};