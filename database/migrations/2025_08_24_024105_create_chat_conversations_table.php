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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->nullable();
            $table->enum('type', ['direct', 'group'])->default('direct');
            $table->text('description')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('sys_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
