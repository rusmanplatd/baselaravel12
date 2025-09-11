<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->foreignUlid('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('updated_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->json('settings')->nullable(); // Project-specific settings like auto-archive, etc.
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'visibility']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};