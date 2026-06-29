<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comment_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comment_id')
                ->constrained('product_comments')
                ->cascadeOnDelete();

            $table->enum('type', ['image', 'youtube']);

            $table->string('path')->nullable();
            $table->string('external_url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['comment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comment_media');
    }
};