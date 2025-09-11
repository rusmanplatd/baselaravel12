<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', [
                'text', 'number', 'date', 'single_select', 'multi_select', 
                'assignees', 'repository', 'milestone', 'iteration', 'labels',
                'progress', 'estimate', 'url', 'status', 'priority'
            ]);
            $table->json('options')->nullable(); // For select fields, stores available options
            $table->json('settings')->nullable(); // Field-specific settings
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_system')->default(false); // Built-in fields like Status, Assignee
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_fields');
    }
};