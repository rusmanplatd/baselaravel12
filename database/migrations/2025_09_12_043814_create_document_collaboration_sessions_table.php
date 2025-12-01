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
        Schema::create('document_collaboration_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id')->index();
            $table->ulid('user_id')->nullable()->index();
            $table->string('session_id')->index();
            $table->string('socket_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['document_id', 'user_id'], 'doc_sessions_doc_user_idx');
            $table->index(['session_id', 'is_active'], 'doc_sessions_session_active_idx');
            $table->index('is_active', 'doc_sessions_active_idx');
            $table->index('last_activity', 'doc_sessions_last_activity_idx');
            $table->index('started_at', 'doc_sessions_started_at_idx');

            // Foreign keys
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_collaboration_sessions');
    }
};
