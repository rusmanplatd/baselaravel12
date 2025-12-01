<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Group admin action logs table (like Telegram's admin log)
        Schema::create('group_admin_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('admin_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('target_user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('target_message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->enum('action', [
                'member_added', 'member_removed', 'member_banned', 'member_unbanned',
                'member_promoted', 'member_demoted', 'member_restricted', 'member_unrestricted',
                'message_deleted', 'message_pinned', 'message_unpinned', 'message_edited',
                'group_settings_changed', 'group_info_changed', 'group_photo_changed',
                'invite_link_created', 'invite_link_revoked', 'invite_link_used',
                'topic_created', 'topic_updated', 'topic_deleted',
                'scheduled_message_created', 'scheduled_message_cancelled',
                'auto_moderation_triggered', 'user_joined', 'user_left'
            ]);
            $table->text('description'); // Human-readable description
            $table->json('action_data')->nullable(); // Structured data about the action
            $table->json('previous_values')->nullable(); // Previous values for reversible actions
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        // Group member join/leave history table
        Schema::create('group_member_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('action', ['joined', 'left', 'removed', 'banned', 'unbanned']);
            $table->foreignUlid('performed_by_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['conversation_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['performed_by_user_id']);
            $table->index(['action', 'occurred_at']);
        });

        // Group statistics table (for analytics)
        Schema::create('group_statistics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->date('date');
            $table->integer('message_count')->default(0);
            $table->integer('active_member_count')->default(0);
            $table->integer('new_member_count')->default(0);
            $table->integer('left_member_count')->default(0);
            $table->integer('media_message_count')->default(0);
            $table->integer('file_message_count')->default(0);
            $table->integer('voice_message_count')->default(0);
            $table->integer('link_message_count')->default(0);
            $table->integer('poll_count')->default(0);
            $table->json('additional_metrics')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'date']);
            $table->index(['conversation_id', 'date']);
            $table->index('date');
        });

        // Group banned users table (separate from regular participants)
        Schema::create('group_banned_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('banned_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->json('ban_settings')->nullable(); // Ban duration, type, etc.
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('banned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('unbanned_at')->nullable();
            $table->foreignUlid('unbanned_by_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'unique_banned_user_per_group');
            $table->index(['conversation_id', 'unbanned_at']);
            $table->index(['user_id', 'unbanned_at']);
            $table->index(['banned_by_user_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_banned_users');
        Schema::dropIfExists('group_statistics');
        Schema::dropIfExists('group_member_history');
        Schema::dropIfExists('group_admin_logs');
    }
};