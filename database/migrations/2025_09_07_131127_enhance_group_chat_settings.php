<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add additional fields to chat_conversations for enhanced group features
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->enum('privacy', ['private', 'public', 'invite_only'])->default('private')->after('type');
            $table->string('username')->unique()->nullable()->after('name'); // Unique username like @groupname
            $table->text('welcome_message')->nullable()->after('description');
            $table->json('group_settings')->nullable()->after('settings'); // Group-specific settings
            $table->integer('member_limit')->nullable()->after('group_settings');
            $table->boolean('can_members_add_others')->default(false)->after('member_limit');
            $table->boolean('require_approval_to_join')->default(true)->after('can_members_add_others');
            $table->boolean('show_member_count')->default(true)->after('require_approval_to_join');
            $table->boolean('allow_anonymous_viewing')->default(false)->after('show_member_count');
            $table->timestamp('last_message_at')->nullable()->after('last_activity_at');
            $table->ulid('last_message_id')->nullable()->after('last_message_at');
        });

        // Add foreign key constraint for last_message_id (will be added after messages table exists)
        // This will be handled in a separate migration after message table is created

        // Group pinned messages table
        Schema::create('group_pinned_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('pinned_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->integer('pin_order')->default(0); // For ordering pinned messages
            $table->timestamp('pinned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['conversation_id', 'message_id']);
            $table->index(['conversation_id', 'pin_order']);
            $table->index(['pinned_by_user_id']);
        });

        // Group member restrictions table (timeouts, restrictions, etc.)
        Schema::create('group_member_restrictions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('participant_id')->constrained('conversation_participants')->onDelete('cascade');
            $table->foreignUlid('restricted_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('restriction_type', ['timeout', 'mute', 'restrict_messages', 'restrict_media', 'restrict_links', 'restrict_polls'])->default('timeout');
            $table->text('reason')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null means permanent
            $table->boolean('is_active')->default(true);
            $table->timestamp('lifted_at')->nullable();
            $table->foreignUlid('lifted_by_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamps();

            $table->index(['conversation_id', 'is_active']);
            $table->index(['participant_id', 'is_active']);
            $table->index(['restricted_by_user_id']);
            $table->index('expires_at');
        });

        // Group topics/categories table (for organizing messages)
        Schema::create('group_topics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3B82F6'); // Hex color code
            $table->string('emoji')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['conversation_id', 'is_active', 'sort_order']);
            $table->index(['created_by_user_id']);
        });

        // Group scheduled messages table
        Schema::create('group_scheduled_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('message_type', ['text', 'media', 'poll', 'announcement'])->default('text');
            $table->text('content');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable(); // Poll data, formatting, etc.
            $table->timestamp('scheduled_for');
            $table->enum('status', ['scheduled', 'sent', 'failed', 'cancelled'])->default('scheduled');
            $table->foreignUlid('sent_message_id')->nullable()->constrained('chat_messages')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status', 'scheduled_for']);
            $table->index(['user_id', 'status']);
            $table->index('scheduled_for');
        });

        // Group auto-moderation rules table
        Schema::create('group_auto_moderation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('rule_type', ['keyword_filter', 'spam_detection', 'link_filter', 'media_filter', 'caps_filter'])->default('keyword_filter');
            $table->json('rule_config'); // Rule-specific configuration
            $table->enum('action', ['warn', 'delete', 'mute', 'timeout', 'ban'])->default('warn');
            $table->json('action_config')->nullable(); // Action-specific config (timeout duration, etc.)
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['conversation_id', 'is_active']);
            $table->index(['rule_type', 'is_active']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_auto_moderation_rules');
        Schema::dropIfExists('group_scheduled_messages');
        Schema::dropIfExists('group_topics');
        Schema::dropIfExists('group_member_restrictions');
        Schema::dropIfExists('group_pinned_messages');

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'privacy',
                'username',
                'welcome_message',
                'group_settings',
                'member_limit',
                'can_members_add_others',
                'require_approval_to_join',
                'show_member_count',
                'allow_anonymous_viewing',
                'last_message_at',
                'last_message_id',
            ]);
        });
    }
};