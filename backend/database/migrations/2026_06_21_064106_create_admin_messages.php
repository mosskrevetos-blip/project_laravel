<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete(); // admin/manager
            $table->string('subject', 255);
            $table->text('body');

            $table->boolean('is_broadcast')->default(false); // true = всем
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('updated_content_at')->nullable(); // когда редактировали контент
            $table->timestamp('deleted_at_by_sender')->nullable(); // "удалено отправителем"

            $table->timestamps();

            $table->index(['sender_id', 'created_at']);
            $table->index(['is_broadcast', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};