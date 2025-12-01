<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event_type');
            $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('info');
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->nullable()->constrained('user_devices')->onDelete('set null');
            $table->foreignUlid('conversation_id')->nullable()->constrained('chat_conversations')->onDelete('set null');
            $table->string('ip_address')->nullable(); // Hashed for privacy
            $table->text('user_agent')->nullable();
            $table->json('location')->nullable(); // Country/region only, never precise location
            $table->json('metadata')->nullable();
            $table->integer('risk_score')->default(0);
            $table->enum('status', ['normal', 'pending', 'investigating', 'resolved', 'false_positive'])->default('normal');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->timestamps();

            // Indexes for performance
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['risk_score', 'status']);
            $table->index(['severity', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
