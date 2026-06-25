<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_message_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_message_id')->constrained('admin_messages')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();

            // unread/read/deleted
            $table->string('status', 20)->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();

            $table->timestamps();

            $table->unique(['admin_message_id', 'recipient_id']);
            $table->index(['recipient_id', 'status']);
            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_recipients');
    }
};