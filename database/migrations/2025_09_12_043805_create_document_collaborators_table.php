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
        Schema::create('document_collaborators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id')->index();
            $table->ulid('user_id')->nullable()->index();
            $table->enum('role', ['viewer', 'commenter', 'editor', 'owner'])->default('editor');
            $table->json('permissions')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->json('cursor_position')->nullable();
            $table->json('selection_range')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('anonymous_name')->nullable();
            $table->string('anonymous_color')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['document_id', 'user_id']);
            $table->index('role');
            $table->index('is_anonymous');
            $table->index('last_seen');

            // Foreign keys
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            // Unique constraint to prevent duplicate collaborators
            $table->unique(['document_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_collaborators');
    }
};
