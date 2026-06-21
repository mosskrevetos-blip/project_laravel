<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversation_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('reason')->nullable();

            $table->string('status', 20)->default('open'); // open|resolved

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'status', 'created_at'], 'idx_conv_status_created');
            $table->index(['reporter_id', 'created_at'], 'idx_reporter_created');
            $table->index(['resolved_by', 'created_at'], 'idx_resolved_by_created');
        });

        // MySQL 8+ CHECK constraints
        DB::statement("
            ALTER TABLE conversation_reports
            ADD CONSTRAINT chk_report_status
            CHECK (status IN ('open','resolved'))
        ");

        DB::statement("
            ALTER TABLE conversation_reports
            ADD CONSTRAINT chk_report_resolved_at
            CHECK (
                (status = 'open' AND resolved_at IS NULL)
                OR
                (status = 'resolved' AND resolved_at IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reports');
    }
};