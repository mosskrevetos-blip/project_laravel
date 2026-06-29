<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comment_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comment_id')
                ->constrained('product_comments')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('reaction', ['like', 'dislike']);

            $table->timestamps();

            $table->unique(['comment_id', 'user_id'], 'pcr_comment_user_unique');
            $table->index(['comment_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comment_reactions');
    }
};