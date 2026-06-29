<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comment_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comment_id')
                ->constrained('product_comments')
                ->cascadeOnDelete();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('reason');

            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');

            $table->text('resolution_note')->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['comment_id', 'status']);
            $table->index(['reporter_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comment_reports');
    }
};