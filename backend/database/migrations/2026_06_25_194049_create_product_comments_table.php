<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('author_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('product_comments')
                ->nullOnDelete();

            $table->foreignId('root_id')
                ->nullable()
                ->constrained('product_comments')
                ->nullOnDelete();

            $table->enum('type', ['review', 'question', 'answer']);

            $table->unsignedTinyInteger('rating')->nullable();

            $table->text('body')->nullable();
            $table->text('pros')->nullable();
            $table->text('cons')->nullable();

            $table->boolean('is_verified_purchase')->default(false);

            $table->enum('answer_origin', ['seller', 'administration'])->nullable();

            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('moderation_reject_reason')->nullable();

            $table->foreignId('moderated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('moderated_at')->nullable();

            $table->foreignId('edited_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('deleted_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'type', 'moderation_status']);
            $table->index(['product_id', 'type', 'moderation_status', 'created_at'], 'pc_prod_type_mod_created_idx');
            $table->index(['root_id']);
            $table->index(['parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comments');
    }
};