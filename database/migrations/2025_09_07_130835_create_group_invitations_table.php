<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Group invitations table
        Schema::create('group_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('invited_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('invited_user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->string('email')->nullable(); // For inviting users who haven't registered yet
            $table->string('phone_number')->nullable(); // Alternative invitation method
            $table->enum('invitation_type', ['direct', 'link', 'email', 'phone'])->default('direct');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'revoked'])->default('pending');
            $table->string('invitation_token')->unique()->nullable(); // For link-based invitations
            $table->text('invitation_message')->nullable();
            $table->json('permissions')->nullable(); // Pre-set permissions for invited user
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
            $table->index(['invited_user_id', 'status']);
            $table->index(['invited_by_user_id']);
            $table->index(['email', 'status']);
            $table->index(['invitation_token']);
            $table->index('expires_at');
        });

        // Group join requests table (for public/semi-public groups)
        Schema::create('group_join_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
            $table->text('request_message')->nullable();
            $table->foreignUlid('reviewed_by_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('review_message')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['conversation_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['reviewed_by_user_id']);
        });

        // Invite links table (for reusable group invite links)
        Schema::create('group_invite_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('created_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('link_token')->unique();
            $table->string('name')->nullable(); // Optional name for the invite link
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            $table->json('permissions')->nullable();
            $table->integer('usage_limit')->nullable(); // Max number of uses
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'is_active']);
            $table->index(['created_by_user_id']);
            $table->index(['link_token']);
            $table->index('expires_at');
        });

        // Track invite link usages
        Schema::create('group_invite_link_usages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invite_link_id')->constrained('group_invite_links')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();

            $table->index(['invite_link_id']);
            $table->index(['user_id']);
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invite_link_usages');
        Schema::dropIfExists('group_invite_links');
        Schema::dropIfExists('group_join_requests');
        Schema::dropIfExists('group_invitations');
    }
};