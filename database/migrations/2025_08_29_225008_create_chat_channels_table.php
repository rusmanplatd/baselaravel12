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
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->ulid('conversation_id');
            $table->ulid('organization_id')->nullable();
            $table->ulid('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('sys_users')->cascadeOnDelete();

            $table->index(['status', 'visibility']);
            $table->index('organization_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_channels');
    }
};