// backend/database/migrations/2026_04_07_181327_create_message_images_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('path'); // storage path in public disk
            $table->string('mime', 50);
            $table->unsignedInteger('size');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_images');
    }
};