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
        Schema::create('document_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id')->index();
            $table->longText('content');
            $table->binary('yjs_state')->nullable();
            $table->ulid('created_by')->index();
            $table->integer('version');
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_auto_save')->default(false);
            $table->boolean('is_milestone')->default(false);
            $table->string('milestone_name')->nullable();
            $table->integer('size')->nullable();
            $table->integer('word_count')->nullable();
            $table->integer('character_count')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['document_id', 'version'], 'doc_revisions_doc_version_idx');
            $table->index('created_by', 'doc_revisions_created_by_idx');
            $table->index('is_auto_save', 'doc_revisions_auto_save_idx');
            $table->index('is_milestone', 'doc_revisions_milestone_idx');
            $table->index('created_at', 'doc_revisions_created_at_idx');

            // Foreign keys
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('sys_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
    }
};
