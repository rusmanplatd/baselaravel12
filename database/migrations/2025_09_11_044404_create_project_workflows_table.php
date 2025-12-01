<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_workflows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('trigger_conditions')->nullable(); // When workflow triggers
            $table->json('actions')->nullable(); // Actions to perform
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('updated_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamps();

            $table->index(['project_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflows');
    }
};