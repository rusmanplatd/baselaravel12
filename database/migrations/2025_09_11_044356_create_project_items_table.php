<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['issue', 'pull_request', 'draft_issue'])->default('issue');
            $table->enum('status', ['todo', 'in_progress', 'done', 'archived'])->default('todo');
            $table->integer('sort_order')->default(0);
            $table->json('field_values')->nullable(); // Values for custom fields
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('updated_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'sort_order']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_items');
    }
};