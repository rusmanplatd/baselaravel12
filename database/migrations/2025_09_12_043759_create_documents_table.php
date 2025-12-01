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
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->longText('content')->nullable();
            $table->binary('yjs_state')->nullable();
            $table->morphs('owner');
            $table->ulid('folder_id')->nullable()->index();
            $table->enum('visibility', ['private', 'public', 'restricted'])->default('private');
            $table->boolean('is_shared')->default(false);
            $table->json('share_settings')->nullable();
            $table->ulid('last_edited_by')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_template')->default(false);
            $table->json('template_data')->nullable();
            $table->json('metadata')->nullable();
            $table->json('collaboration_settings')->nullable();
            $table->boolean('is_collaborative')->default(true);
            $table->integer('lock_version')->default(0);
            $table->enum('status', ['draft', 'published', 'archived', 'deleted'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['owner_type', 'owner_id'], 'documents_owner_idx');
            $table->index('visibility', 'documents_visibility_idx');
            $table->index('is_shared', 'documents_is_shared_idx');
            $table->index('is_collaborative', 'documents_is_collaborative_idx');
            $table->index('status', 'documents_status_idx');
            $table->index('created_at', 'documents_created_at_idx');
            $table->index('updated_at', 'documents_updated_at_idx');

            // Foreign keys
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('set null');
            $table->foreign('last_edited_by')->references('id')->on('sys_users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
