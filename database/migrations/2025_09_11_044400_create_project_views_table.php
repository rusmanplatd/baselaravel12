<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_views', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->enum('layout', ['table', 'board', 'timeline', 'roadmap'])->default('table');
            $table->json('filters')->nullable(); // Filter configuration
            $table->json('sort')->nullable(); // Sort configuration
            $table->json('group_by')->nullable(); // Grouping configuration
            $table->json('visible_fields')->nullable(); // Which fields to show
            $table->json('settings')->nullable(); // View-specific settings
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('updated_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamps();

            $table->index(['project_id', 'is_default']);
            $table->index(['project_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_views');
    }
};