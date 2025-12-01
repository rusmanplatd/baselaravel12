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
        // Chat backup jobs table
        Schema::create('chat_backups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('conversation_id')->nullable()->constrained('chat_conversations')->onDelete('cascade');
            $table->string('backup_type'); // full_account, conversation, date_range
            $table->string('export_format'); // json, xml, pdf, html
            $table->json('backup_scope'); // What to include: messages, files, polls, etc.
            $table->json('date_range')->nullable(); // For date-based backups
            $table->json('encryption_settings'); // Backup encryption preferences
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('status_message')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->bigInteger('total_items')->nullable();
            $table->bigInteger('processed_items')->default(0);
            $table->string('backup_file_path')->nullable();
            $table->string('backup_file_hash')->nullable();
            $table->bigInteger('backup_file_size')->nullable();
            $table->boolean('include_attachments')->default(true);
            $table->boolean('include_metadata')->default(true);
            $table->boolean('preserve_encryption')->default(true); // Keep E2EE or decrypt for export
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // When backup file will be deleted
            $table->json('error_log')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('expires_at');
        });

        // Backup verification and integrity table
        Schema::create('backup_verification', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('backup_id')->constrained('chat_backups')->onDelete('cascade');
            $table->string('verification_method'); // hash, signature, checksum
            $table->text('verification_data'); // Hash values, signatures, etc.
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->index(['backup_id', 'verification_method']);
        });

        // Export templates for custom backup formats
        Schema::create('export_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('template_name');
            $table->string('template_type'); // json, xml, html, pdf
            $table->text('template_content'); // Template structure/format
            $table->json('template_settings'); // Formatting preferences
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false); // Can other users use this template
            $table->timestamps();

            $table->unique(['user_id', 'template_name']);
            $table->index(['template_type', 'is_shared']);
        });

        // Backup restoration tracking
        Schema::create('backup_restorations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('backup_id')->nullable()->constrained('chat_backups')->onDelete('set null');
            $table->string('restoration_type'); // full, selective, conversation
            $table->string('source_file_path');
            $table->string('source_file_hash');
            $table->json('restoration_scope'); // What to restore
            $table->string('status')->default('pending');
            $table->text('status_message')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->bigInteger('total_items')->nullable();
            $table->bigInteger('restored_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('restoration_log')->nullable();
            $table->json('error_log')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_restorations');
        Schema::dropIfExists('export_templates');
        Schema::dropIfExists('backup_verification');
        Schema::dropIfExists('chat_backups');
    }
};
