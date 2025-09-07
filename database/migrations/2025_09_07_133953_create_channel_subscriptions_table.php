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
        // Channel subscriptions (separate from conversation participants for channels)
        Schema::create('channel_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('channel_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('status', ['subscribed', 'unsubscribed', 'blocked'])->default('subscribed');
            $table->boolean('has_notifications')->default(true);
            $table->boolean('is_muted')->default(false);
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->ulid('last_viewed_message_id')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['channel_id', 'status']);
            $table->index('last_viewed_at');
        });

        // Channel statistics
        Schema::create('channel_statistics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('channel_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->date('date');
            $table->bigInteger('views')->default(0);
            $table->bigInteger('unique_views')->default(0);
            $table->bigInteger('new_subscribers')->default(0);
            $table->bigInteger('unsubscribes')->default(0);
            $table->bigInteger('shares')->default(0);
            $table->bigInteger('messages_sent')->default(0);
            $table->json('hourly_views')->nullable(); // Views by hour for detailed analytics
            $table->json('demographic_data')->nullable(); // Age, location analytics (if enabled)
            $table->timestamps();

            $table->unique(['channel_id', 'date']);
            $table->index('date');
            $table->index(['channel_id', 'date']);
        });

        // Channel broadcasts (for scheduled messages)
        Schema::create('channel_broadcasts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('channel_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('created_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('content');
            $table->json('media_attachments')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->bigInteger('recipient_count')->default(0);
            $table->bigInteger('delivered_count')->default(0);
            $table->bigInteger('read_count')->default(0);
            $table->json('broadcast_settings')->nullable(); // Targeting, silent mode, etc.
            $table->timestamps();

            $table->index(['channel_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index('created_by_user_id');
        });

        // Channel views tracking
        Schema::create('channel_message_views', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('channel_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('cascade'); // Nullable for anonymous views
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->index(['channel_id', 'message_id']);
            $table->index(['user_id', 'viewed_at']);
            $table->index('viewed_at');
        });

        // Channel categories lookup
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon name or emoji
            $table->string('color', 7)->nullable(); // Hex color code
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_message_views');
        Schema::dropIfExists('channel_broadcasts');
        Schema::dropIfExists('channel_statistics');
        Schema::dropIfExists('channel_subscriptions');
        Schema::dropIfExists('channel_categories');
    }
};