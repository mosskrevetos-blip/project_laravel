<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_message_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_message_id')->constrained('admin_messages')->cascadeOnDelete();

            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();

            $table->index(['admin_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_attachments');
    }
};