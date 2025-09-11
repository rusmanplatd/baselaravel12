<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('role', ['admin', 'write', 'read'])->default('read');
            $table->json('permissions')->nullable(); // Additional specific permissions
            $table->foreignUlid('added_by')->constrained('sys_users')->onDelete('cascade');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'role']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};