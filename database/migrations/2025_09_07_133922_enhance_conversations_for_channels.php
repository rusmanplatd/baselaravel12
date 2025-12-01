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
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Channel-specific fields
            $table->string('category')->nullable()->after('description'); // Channel category (news, tech, etc.)
            $table->boolean('is_verified')->default(false)->after('category'); // Verified channel badge
            $table->boolean('is_broadcast')->default(false)->after('is_verified'); // Broadcast-only channel
            $table->boolean('allow_anonymous_posts')->default(false)->after('is_broadcast'); // Anonymous posting
            $table->boolean('show_subscriber_count')->default(true)->after('show_member_count'); // Show subscriber count
            $table->boolean('require_join_approval')->default(false)->after('require_approval_to_join'); // Join approval for channels
            $table->json('channel_settings')->nullable()->after('group_settings'); // Channel-specific settings
            $table->bigInteger('view_count')->default(0)->after('channel_settings'); // Total channel views
            $table->bigInteger('subscriber_count')->default(0)->after('view_count'); // Cached subscriber count
            $table->timestamp('last_broadcast_at')->nullable()->after('last_message_at'); // Last broadcast time
            
            // Indexing for performance
            $table->index(['type', 'privacy', 'is_active'], 'idx_channels_discovery');
            $table->index(['category', 'is_verified'], 'idx_channels_category');
            $table->index(['subscriber_count', 'is_active'], 'idx_channels_popularity');
            $table->index('view_count', 'idx_channels_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_channels_discovery');
            $table->dropIndex('idx_channels_category');
            $table->dropIndex('idx_channels_popularity');
            $table->dropIndex('idx_channels_views');
            
            $table->dropColumn([
                'category',
                'is_verified',
                'is_broadcast',
                'allow_anonymous_posts',
                'show_subscriber_count',
                'require_join_approval',
                'channel_settings',
                'view_count',
                'subscriber_count',
                'last_broadcast_at',
            ]);
        });
    }
};